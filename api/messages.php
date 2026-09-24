<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/bootstrap.php';

$user = require_login();
$uid = (int) $user['id'];
$action = api_action();

function format_private_message(array $row, int $viewerId): array
{
    $deleted = (int) $row['is_deleted'] === 1;
    $hiddenForViewer = ((int) $row['sender_id'] === $viewerId && (int) $row['deleted_by_sender'] === 1)
        || ((int) $row['receiver_id'] === $viewerId && (int) $row['deleted_by_receiver'] === 1);

    $out = [
        'id' => (int) $row['id'],
        'sender_id' => (int) $row['sender_id'],
        'receiver_id' => (int) $row['receiver_id'],
        'message_type' => $row['message_type'],
        'body' => $deleted ? null : $row['body'],
        'file_path' => $deleted ? null : $row['file_path'],
        'file_url' => (!$deleted && $row['file_path']) ? media_url($row['file_path']) : null,
        'file_name' => $deleted ? null : $row['file_name'],
        'mime_type' => $deleted ? null : $row['mime_type'],
        'file_size' => $deleted ? null : ($row['file_size'] !== null ? (int) $row['file_size'] : null),
        'voice_duration' => $deleted ? null : $row['voice_duration'],
        'reply_to_id' => $row['reply_to_id'] !== null ? (int) $row['reply_to_id'] : null,
        'delivery_status' => $row['delivery_status'],
        'is_deleted' => $deleted,
        'is_mine' => (int) $row['sender_id'] === $viewerId,
        'created_at' => $row['created_at'],
        'read_at' => $row['read_at'],
        'hidden' => $hiddenForViewer,
        'reply' => null,
        'reactions' => [],
        'call' => null,
    ];

    if (!$deleted && $row['message_type'] === 'call' && !empty($row['body'])) {
        $meta = json_decode((string) $row['body'], true);
        if (is_array($meta)) {
            // Ensure caller name exists for older records
            if (empty($meta['caller_username']) && !empty($meta['caller_id'])) {
                $cu = get_user_by_id((int) $meta['caller_id']);
                $meta['caller_username'] = $cu['username'] ?? '';
            } elseif (empty($meta['caller_username'])) {
                $cu = get_user_by_id((int) $row['sender_id']);
                $meta['caller_id'] = (int) $row['sender_id'];
                $meta['caller_username'] = $cu['username'] ?? '';
            }
            $outgoing = (int) $row['sender_id'] === $viewerId;
            $duration = (int) ($meta['duration'] ?? $row['voice_duration'] ?? 0);
            $status = (string) ($meta['status'] ?? 'ended');
            $out['call'] = [
                'call_id' => (int) ($meta['call_id'] ?? 0),
                'call_type' => $meta['call_type'] ?? 'voice',
                'status' => $status,
                'duration' => $duration,
                'caller_id' => (int) ($meta['caller_id'] ?? $row['sender_id']),
                'caller_username' => (string) ($meta['caller_username'] ?? ''),
                'outgoing' => $outgoing,
                'title' => call_title_label($meta, $outgoing),
                'status_label' => call_status_label($status, $duration),
                'label' => call_log_preview((string) $row['body'], $viewerId),
            ];
            $out['body'] = $out['call']['label'];
        }
    }

    if (!empty($row['reply_id'])) {
        $out['reply'] = [
            'id' => (int) $row['reply_id'],
            'body' => ((int) $row['reply_deleted'] === 1) ? null : $row['reply_body'],
            'message_type' => $row['reply_type'],
            'is_deleted' => (int) $row['reply_deleted'] === 1,
            'sender_id' => (int) $row['reply_sender_id'],
        ];
    }

    return $out;
}

function load_reactions_for_messages(array $messageIds): array
{
    if (!$messageIds) {
        return [];
    }
    $ph = implode(',', array_fill(0, count($messageIds), '?'));
    $stmt = db()->prepare(
        "SELECT message_id, reaction, COUNT(*) AS cnt, GROUP_CONCAT(user_id) AS user_ids
         FROM message_reactions WHERE message_id IN ($ph)
         GROUP BY message_id, reaction"
    );
    $stmt->execute($messageIds);
    $map = [];
    while ($row = $stmt->fetch()) {
        $mid = (int) $row['message_id'];
        $map[$mid][] = [
            'reaction' => $row['reaction'],
            'count' => (int) $row['cnt'],
            'user_ids' => array_map('intval', explode(',', (string) $row['user_ids'])),
        ];
    }
    return $map;
}

function assert_can_message(int $uid, int $peerId): array
{
    if ($peerId <= 0 || $peerId === $uid) {
        json_error(__('msg.invalid_recipient'));
    }
    $peer = get_user_by_id($peerId);
    if (!$peer || !(int) $peer['is_active']) {
        json_error(__('msg.user_disabled'));
    }
    if (is_blocked_either($uid, $peerId)) {
        json_error(__('msg.unable_message'));
    }
    if (!users_are_friends($uid, $peerId)) {
        json_error(__('msg.friends_only'));
    }
    return $peer;
}

switch ($action) {
    case 'history':
        $peerId = int_input('user_id');
        $afterId = int_input('after_id');
        $beforeId = int_input('before_id');
        $limit = min(100, max(1, int_input('limit', 50)));
        assert_can_message($uid, $peerId);

        $sql = 'SELECT m.*,
                       r.id AS reply_id, r.body AS reply_body, r.message_type AS reply_type,
                       r.is_deleted AS reply_deleted, r.sender_id AS reply_sender_id
                FROM messages m
                LEFT JOIN messages r ON r.id = m.reply_to_id
                WHERE ((m.sender_id = ? AND m.receiver_id = ?) OR (m.sender_id = ? AND m.receiver_id = ?))
                  AND NOT ((m.sender_id = ? AND m.deleted_by_sender = 1) OR (m.receiver_id = ? AND m.deleted_by_receiver = 1))';
        $params = [$uid, $peerId, $peerId, $uid, $uid, $uid];

        if ($afterId > 0) {
            $sql .= ' AND m.id > ?';
            $params[] = $afterId;
            $sql .= ' ORDER BY m.id ASC LIMIT ' . (int) $limit;
        } elseif ($beforeId > 0) {
            $sql .= ' AND m.id < ?';
            $params[] = $beforeId;
            $sql .= ' ORDER BY m.id DESC LIMIT ' . (int) $limit;
        } else {
            $sql .= ' ORDER BY m.id DESC LIMIT ' . (int) $limit;
        }

        $stmt = db()->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();
        if ($afterId <= 0) {
            $rows = array_reverse($rows);
        }

        $ids = array_map(static fn($r) => (int) $r['id'], $rows);
        $reactions = load_reactions_for_messages($ids);
        $messages = [];
        foreach ($rows as $row) {
            $msg = format_private_message($row, $uid);
            $msg['reactions'] = $reactions[(int) $row['id']] ?? [];
            $messages[] = $msg;
        }

        // Mark delivered/read for incoming
        $mark = db()->prepare(
            "UPDATE messages SET delivery_status = 'delivered', delivered_at = COALESCE(delivered_at, NOW())
             WHERE sender_id = ? AND receiver_id = ? AND is_deleted = 0 AND delivery_status = 'sent'"
        );
        $mark->execute([$peerId, $uid]);

        $markRead = db()->prepare(
            "UPDATE messages SET delivery_status = 'read', read_at = COALESCE(read_at, NOW()),
                delivered_at = COALESCE(delivered_at, NOW())
             WHERE sender_id = ? AND receiver_id = ? AND is_deleted = 0 AND delivery_status <> 'read'"
        );
        $markRead->execute([$peerId, $uid]);

        json_success(__('common.ok'), [
            'messages' => $messages,
            'peer' => public_user(get_user_by_id($peerId) ?? []),
        ]);

    case 'send':
        require_csrf();
        require_rate_limit('message', RATE_MESSAGE, $uid);
        $peerId = int_input('user_id');
        $peer = assert_can_message($uid, $peerId);
        $type = str_input('message_type', 'text');
        if (!in_array($type, ['text', 'image', 'file', 'voice'], true)) {
            json_error(__('msg.invalid_type'));
        }
        $body = str_input('body');
        $replyTo = int_input('reply_to_id');
        $filePath = null;
        $fileName = null;
        $mime = null;
        $fileSize = null;
        $voiceDuration = null;

        if ($type === 'text') {
            if ($body === '' || mb_strlen($body) > 5000) {
                json_error(__('msg.text_required'));
            }
        } else {
            // Expect prior upload via upload API, or multipart here
            if (!empty($_FILES['file'])) {
                $subdir = match ($type) {
                    'image' => 'images',
                    'voice' => 'voices',
                    default => 'files',
                };
                $ext = match ($type) {
                    'image' => ALLOWED_IMAGE_EXT,
                    'voice' => ALLOWED_VOICE_EXT,
                    default => ALLOWED_FILE_EXT,
                };
                $mimes = match ($type) {
                    'image' => ALLOWED_IMAGE_MIME,
                    'voice' => ALLOWED_VOICE_MIME,
                    default => ALLOWED_FILE_MIME,
                };
                $max = match ($type) {
                    'image' => MAX_IMAGE_SIZE,
                    'voice' => MAX_VOICE_SIZE,
                    default => MAX_FILE_SIZE,
                };
                require_rate_limit('upload', RATE_UPLOAD, $uid);
                $up = store_uploaded_file($_FILES['file'], $subdir, $ext, $mimes, $max);
                if (!$up['ok']) {
                    json_error($up['error']);
                }
                $filePath = $up['path'];
                $fileName = $up['file_name'];
                $mime = $up['mime_type'];
                $fileSize = $up['file_size'];
            } else {
                $filePath = str_input('file_path');
                $fileName = str_input('file_name');
                $mime = str_input('mime_type');
                $fileSize = int_input('file_size');
                if (!$filePath || !is_safe_upload_path($filePath) || !absolute_upload_path($filePath)) {
                    json_error(__('msg.invalid_attachment'));
                }
            }
            if ($type === 'voice') {
                $voiceDuration = input('voice_duration');
                $voiceDuration = is_numeric($voiceDuration) ? round((float) $voiceDuration, 2) : null;
            }
            if ($body === '') {
                $body = null;
            }
        }

        if ($replyTo > 0) {
            $chk = db()->prepare(
                'SELECT id FROM messages WHERE id = ? AND ((sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?)) LIMIT 1'
            );
            $chk->execute([$replyTo, $uid, $peerId, $peerId, $uid]);
            if (!$chk->fetch()) {
                json_error(__('msg.reply_missing'));
            }
        } else {
            $replyTo = null;
        }

        $ins = db()->prepare(
            'INSERT INTO messages
             (sender_id, receiver_id, message_type, body, file_path, file_name, mime_type, file_size, voice_duration, reply_to_id, delivery_status)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $ins->execute([
            $uid, $peerId, $type, $body, $filePath, $fileName, $mime, $fileSize, $voiceDuration, $replyTo, 'sent',
        ]);
        $msgId = (int) db()->lastInsertId();

        create_notification(
            $peerId,
            'new_message',
            ['name' => $user['username']],
            'chat.php?user=' . $uid,
            $msgId
        );

        $stmt = db()->prepare(
            'SELECT m.*, r.id AS reply_id, r.body AS reply_body, r.message_type AS reply_type,
                    r.is_deleted AS reply_deleted, r.sender_id AS reply_sender_id
             FROM messages m
             LEFT JOIN messages r ON r.id = m.reply_to_id
             WHERE m.id = ?'
        );
        $stmt->execute([$msgId]);
        $row = $stmt->fetch();
        json_success(__('msg.sent'), ['message' => format_private_message($row, $uid)]);

    case 'poll':
        $peerId = int_input('user_id');
        $afterId = int_input('after_id');
        assert_can_message($uid, $peerId);
        $sql = 'SELECT m.*,
                       r.id AS reply_id, r.body AS reply_body, r.message_type AS reply_type,
                       r.is_deleted AS reply_deleted, r.sender_id AS reply_sender_id
                FROM messages m
                LEFT JOIN messages r ON r.id = m.reply_to_id
                WHERE ((m.sender_id = ? AND m.receiver_id = ?) OR (m.sender_id = ? AND m.receiver_id = ?))
                  AND m.id > ?
                  AND NOT ((m.sender_id = ? AND m.deleted_by_sender = 1) OR (m.receiver_id = ? AND m.deleted_by_receiver = 1))
                ORDER BY m.id ASC LIMIT 100';
        $stmt = db()->prepare($sql);
        $stmt->execute([$uid, $peerId, $peerId, $uid, $afterId, $uid, $uid]);
        $rows = $stmt->fetchAll();
        $ids = array_map(static fn($r) => (int) $r['id'], $rows);
        $reactions = load_reactions_for_messages($ids);
        $messages = [];
        foreach ($rows as $row) {
            $msg = format_private_message($row, $uid);
            $msg['reactions'] = $reactions[(int) $row['id']] ?? [];
            $messages[] = $msg;
        }
        // Mark read
        db()->prepare(
            "UPDATE messages SET delivery_status = 'read', read_at = COALESCE(read_at, NOW()),
                delivered_at = COALESCE(delivered_at, NOW())
             WHERE sender_id = ? AND receiver_id = ? AND is_deleted = 0 AND delivery_status <> 'read'"
        )->execute([$peerId, $uid]);

        // Return updated statuses for own messages
        $statusStmt = db()->prepare(
            "SELECT id, delivery_status, read_at FROM messages
             WHERE sender_id = ? AND receiver_id = ? AND id > ? - 200 ORDER BY id DESC LIMIT 50"
        );
        $statusStmt->execute([$uid, $peerId, $afterId]);
        $statuses = $statusStmt->fetchAll();

        json_success(__('common.ok'), ['messages' => $messages, 'statuses' => $statuses]);

    case 'delete':
        require_csrf();
        $msgId = int_input('message_id');
        $mode = str_input('mode', 'me'); // me | everyone
        $stmt = db()->prepare('SELECT * FROM messages WHERE id = ? LIMIT 1');
        $stmt->execute([$msgId]);
        $msg = $stmt->fetch();
        if (!$msg) {
            json_error(__('msg.not_found'));
        }
        $sid = (int) $msg['sender_id'];
        $rid = (int) $msg['receiver_id'];
        if ($sid !== $uid && $rid !== $uid) {
            json_error(__('common.not_allowed'), 403);
        }
        if ($mode === 'everyone') {
            if ($sid !== $uid) {
                json_error(__('msg.only_sender_delete'), 403);
            }
            db()->prepare('UPDATE messages SET is_deleted = 1, body = NULL WHERE id = ?')->execute([$msgId]);
        } else {
            if ($sid === $uid) {
                db()->prepare('UPDATE messages SET deleted_by_sender = 1 WHERE id = ?')->execute([$msgId]);
            } else {
                db()->prepare('UPDATE messages SET deleted_by_receiver = 1 WHERE id = ?')->execute([$msgId]);
            }
        }
        json_success(__('msg.delete_ok'));

    case 'report':
        require_csrf();
        $msgId = int_input('message_id');
        $reason = str_input('reason');
        if ($reason === '' || mb_strlen($reason) > 500) {
            json_error(__('msg.provide_reason'));
        }
        $stmt = db()->prepare('SELECT id FROM messages WHERE id = ? LIMIT 1');
        $stmt->execute([$msgId]);
        if (!$stmt->fetch()) {
            json_error(__('msg.not_found'));
        }
        $ins = db()->prepare(
            'INSERT INTO reports (reporter_id, target_type, target_id, reason, status) VALUES (?, ?, ?, ?, ?)'
        );
        $ins->execute([$uid, 'private_message', $msgId, $reason, 'open']);
        json_success(__('report.ok'));

    default:
        json_error(__('common.unknown_action'), 404);
}
