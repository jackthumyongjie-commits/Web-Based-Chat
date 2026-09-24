<?php
/**
 * Private conversation list / open helpers
 */
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/bootstrap.php';

$user = require_login();
$uid = (int) $user['id'];
$action = api_action();

switch ($action) {
    case 'conversations':
        $q = str_input('q');
        // Latest message per peer via subquery
        $sql = "
            SELECT
                peer.id AS peer_id,
                peer.username,
                peer.avatar,
                peer.status_message,
                peer.presence,
                peer.last_seen_at,
                peer.last_activity_at,
                peer.is_active,
                lm.id AS last_message_id,
                lm.body AS last_body,
                lm.message_type AS last_type,
                lm.created_at AS last_at,
                lm.sender_id AS last_sender_id,
                lm.is_deleted AS last_deleted,
                (
                    SELECT COUNT(*) FROM messages m2
                    WHERE m2.sender_id = peer.id AND m2.receiver_id = ? AND m2.is_deleted = 0
                      AND m2.deleted_by_receiver = 0 AND m2.delivery_status <> 'read'
                ) AS unread_count
            FROM (
                SELECT
                    CASE WHEN sender_id = ? THEN receiver_id ELSE sender_id END AS peer_id,
                    MAX(id) AS max_id
                FROM messages
                WHERE (sender_id = ? OR receiver_id = ?)
                  AND NOT (
                    (sender_id = ? AND deleted_by_sender = 1)
                    OR (receiver_id = ? AND deleted_by_receiver = 1)
                  )
                GROUP BY peer_id
            ) conv
            JOIN messages lm ON lm.id = conv.max_id
            JOIN users peer ON peer.id = conv.peer_id
            WHERE peer.is_active = 1
        ";
        $params = [$uid, $uid, $uid, $uid, $uid, $uid];
        if ($q !== '') {
            $sql .= ' AND peer.username LIKE ?';
            $params[] = '%' . $q . '%';
        }
        $sql .= ' ORDER BY lm.created_at DESC LIMIT 100';
        $stmt = db()->prepare($sql);
        $stmt->execute($params);
        $list = [];
        while ($row = $stmt->fetch()) {
            $preview = '';
            if ((int) $row['last_deleted']) {
                $preview = __('msg.deleted');
            } else {
                $preview = match ($row['last_type']) {
                    'image' => __('msg.type_image'),
                    'file'  => __('msg.type_file'),
                    'voice' => __('msg.type_voice'),
                    'call'  => call_log_preview((string) ($row['last_body'] ?? ''), $uid),
                    default => (string) ($row['last_body'] ?? ''),
                };
                if (mb_strlen($preview) > 80) {
                    $preview = mb_substr($preview, 0, 80) . '…';
                }
            }
            $list[] = [
                'peer' => public_user([
                    'id' => $row['peer_id'],
                    'username' => $row['username'],
                    'avatar' => $row['avatar'],
                    'status_message' => $row['status_message'],
                    'presence' => $row['presence'],
                    'last_seen_at' => $row['last_seen_at'],
                    'last_activity_at' => $row['last_activity_at'],
                    'is_active' => $row['is_active'],
                ]),
                'last_message' => [
                    'id' => (int) $row['last_message_id'],
                    'preview' => $preview,
                    'created_at' => $row['last_at'],
                    'sender_id' => (int) $row['last_sender_id'],
                ],
                'unread_count' => (int) $row['unread_count'],
            ];
        }
        json_success(__('common.ok'), ['conversations' => $list]);

    case 'typing':
        require_csrf();
        $peerId = int_input('user_id');
        if ($peerId <= 0 || $peerId === $uid) {
            json_error(__('friends.invalid_user'));
        }
        if (!users_are_friends($uid, $peerId) || is_blocked_either($uid, $peerId)) {
            json_error(__('common.not_allowed'));
        }
        $expires = (new DateTimeImmutable('now'))
            ->modify('+' . TYPING_TTL_SECONDS . ' seconds')
            ->format('Y-m-d H:i:s');
        $stmt = db()->prepare(
            'INSERT INTO typing_indicators (user_id, conversation_type, target_id, expires_at)
             VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE expires_at = VALUES(expires_at)'
        );
        $stmt->execute([$uid, 'private', $peerId, $expires]);
        json_success(__('common.ok'));

    case 'typing_status':
        $peerId = int_input('user_id');
        db()->exec('DELETE FROM typing_indicators WHERE expires_at < NOW()');
        $stmt = db()->prepare(
            'SELECT u.username FROM typing_indicators t
             JOIN users u ON u.id = t.user_id
             WHERE t.conversation_type = ? AND t.target_id = ? AND t.user_id = ? AND t.expires_at >= NOW()
             LIMIT 1'
        );
        $stmt->execute(['private', $uid, $peerId]);
        $row = $stmt->fetch();
        json_success(__('common.ok'), [
            'typing' => (bool) $row,
            'username' => $row['username'] ?? null,
        ]);

    default:
        json_error(__('common.unknown_action'), 404);
}
