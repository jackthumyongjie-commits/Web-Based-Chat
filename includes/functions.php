<?php
/**
 * WebConnect — shared helper functions
 */

declare(strict_types=1);

function json_response(array $payload, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function json_success(string $message = 'OK', mixed $data = null, int $status = 200): never
{
    $out = ['success' => true, 'message' => $message];
    if ($data !== null) {
        $out['data'] = $data;
    }
    json_response($out, $status);
}

function json_error(string $message, int $status = 400, mixed $data = null): never
{
    $out = ['success' => false, 'message' => $message];
    if ($data !== null) {
        $out['data'] = $data;
    }
    json_response($out, $status);
}

function request_method(): string
{
    return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
}

function request_json(): array
{
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }
    $raw = file_get_contents('php://input');
    if ($raw === false || $raw === '') {
        $cached = [];
        return $cached;
    }
    $decoded = json_decode($raw, true);
    $cached = is_array($decoded) ? $decoded : [];
    return $cached;
}

function input(string $key, mixed $default = null): mixed
{
    $json = request_json();
    if (array_key_exists($key, $json)) {
        return $json[$key];
    }
    if (array_key_exists($key, $_POST)) {
        return $_POST[$key];
    }
    if (array_key_exists($key, $_GET)) {
        return $_GET[$key];
    }
    return $default;
}

function str_input(string $key, string $default = ''): string
{
    $val = input($key, $default);
    return is_string($val) ? trim($val) : $default;
}

function int_input(string $key, int $default = 0): int
{
    $val = input($key, $default);
    return is_numeric($val) ? (int) $val : $default;
}

function redirect(string $path): never
{
    if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
        header('Location: ' . $path);
    } else {
        header('Location: ' . app_url() . '/' . ltrim($path, '/'));
    }
    exit;
}

function url(string $path = ''): string
{
    $path = ltrim($path, '/');
    return $path === '' ? app_url() : (app_url() . '/' . $path);
}

function asset(string $path): string
{
    $rel = 'assets/' . ltrim($path, '/');
    $full = dirname(__DIR__) . '/' . $rel;
    $url = url($rel);
    if (is_file($full)) {
        $url .= (str_contains($url, '?') ? '&' : '?') . 'v=' . filemtime($full);
    }
    return $url;
}

function media_url(string $relativePath): string
{
    return url('api/media.php?f=' . rawurlencode($relativePath));
}

function avatar_url(?string $avatar, string $username = ''): string
{
    if ($avatar && is_safe_upload_path($avatar)) {
        return media_url($avatar);
    }
    $initial = strtoupper(substr($username !== '' ? $username : 'U', 0, 1));
    return 'https://ui-avatars.com/api/?name=' . rawurlencode($initial) . '&background=0d6e6e&color=fff&size=128';
}

function validate_password(string $password): ?string
{
    if (strlen($password) < PASSWORD_MIN_LENGTH) {
        return __('auth.password_min', ['min' => (string) PASSWORD_MIN_LENGTH]);
    }
    if (!preg_match('/[A-Za-z]/', $password) || !preg_match('/[0-9]/', $password)) {
        return __('auth.password_complexity');
    }
    return null;
}

function validate_username(string $username): ?string
{
    if ($username === '' || strlen($username) < 3 || strlen($username) > 50) {
        return __('auth.username_len');
    }
    if (!preg_match('/^[A-Za-z0-9_\.]+$/', $username)) {
        return __('auth.username_chars');
    }
    return null;
}

function users_are_friends(int $a, int $b): bool
{
    if ($a === $b) {
        return false;
    }
    $low = min($a, $b);
    $high = max($a, $b);
    $stmt = db()->prepare('SELECT id FROM friendships WHERE user_low_id = ? AND user_high_id = ? LIMIT 1');
    $stmt->execute([$low, $high]);
    return (bool) $stmt->fetch();
}

function is_blocked_either(int $a, int $b): bool
{
    $stmt = db()->prepare(
        'SELECT id FROM user_blocks WHERE (blocker_id = ? AND blocked_id = ?) OR (blocker_id = ? AND blocked_id = ?) LIMIT 1'
    );
    $stmt->execute([$a, $b, $b, $a]);
    return (bool) $stmt->fetch();
}

function get_user_by_id(int $id): ?array
{
    $stmt = db()->prepare(
        'SELECT id, username, email, avatar, status_message, presence, last_seen_at, last_activity_at, is_active, created_at
         FROM users WHERE id = ? LIMIT 1'
    );
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function public_user(array $user): array
{
    return [
        'id'             => (int) $user['id'],
        'username'       => $user['username'],
        'avatar'         => $user['avatar'],
        'avatar_url'     => avatar_url($user['avatar'] ?? null, $user['username'] ?? ''),
        'status_message' => $user['status_message'] ?? '',
        'presence'       => compute_presence($user),
        'last_seen_at'   => $user['last_seen_at'] ?? null,
    ];
}

function compute_presence(array $user): string
{
    if (!(int) ($user['is_active'] ?? 1)) {
        return 'offline';
    }
    $stored = $user['presence'] ?? 'offline';
    if ($stored === 'busy') {
        return 'busy';
    }
    $last = $user['last_activity_at'] ?? null;
    if (!$last) {
        return 'offline';
    }
    try {
        // Interpret DB datetime in app timezone (not server default / UTC mismatch)
        $tz = new DateTimeZone(date_default_timezone_get());
        $dt = new DateTimeImmutable((string) $last, $tz);
        $ago = time() - $dt->getTimestamp();
    } catch (Throwable $e) {
        return 'offline';
    }
    // Allow a little slack so a missed poll tick does not flip to offline
    if ($ago <= PRESENCE_ONLINE_SECONDS + 15) {
        return 'online';
    }
    if ($ago <= PRESENCE_AWAY_SECONDS) {
        return 'away';
    }
    return 'offline';
}

function touch_presence(int $userId, ?string $force = null): void
{
    $presence = $force ?? 'online';
    // Write PHP clock (same TZ as compute_presence) — do not rely on MySQL NOW() alone
    $now = (new DateTimeImmutable('now'))->format('Y-m-d H:i:s');
    $stmt = db()->prepare(
        'UPDATE users SET last_activity_at = ?, last_seen_at = ?, presence = ? WHERE id = ?'
    );
    $stmt->execute([$now, $now, $presence, $userId]);
}

function create_notification(
    int $userId,
    string $type,
    array $meta = [],
    ?string $link = null,
    ?int $relatedId = null
): void {
    $payload = json_encode(array_merge(['v' => 1], $meta), JSON_UNESCAPED_UNICODE);
    $stmt = db()->prepare(
        'INSERT INTO notifications (user_id, type, title, body, link, related_id) VALUES (?, ?, ?, ?, ?, ?)'
    );
    // title stores type key; body stores JSON meta — localized when listing
    $stmt->execute([$userId, $type, $type, $payload ?: '{}', $link, $relatedId]);
}

/**
 * Resolve notification title/body in the current UI language.
 */
function localize_notification(array $row): array
{
    $type = (string) ($row['type'] ?? '');
    $decoded = json_decode((string) ($row['body'] ?? ''), true);
    $isStructured = is_array($decoded) && !empty($decoded['v']);
    $meta = $isStructured ? $decoded : notification_meta_legacy($row);

    $name = (string) ($meta['name'] ?? '');
    $group = (string) ($meta['group'] ?? '');
    $reaction = (string) ($meta['reaction'] ?? '');
    $callTypeKey = (string) ($meta['call_type'] ?? 'voice');

    $canLocalize = $isStructured || match ($type) {
        'new_message', 'incoming_call', 'friend_request', 'friend_accepted',
        'forum_comment', 'message_reaction' => $name !== '',
        'group_invite' => $group !== '',
        'group_message' => $name !== '' && $group !== '',
        default => false,
    };

    $title = (string) ($row['title'] ?? '');
    $body = $isStructured ? '' : (string) ($row['body'] ?? '');

    if ($canLocalize) {
        switch ($type) {
            case 'new_message':
                $title = __('notif.new_message');
                $body = __('notif.new_message_body', ['name' => $name]);
                break;
            case 'incoming_call':
                $typeLabel = $callTypeKey === 'video' ? __('call.video') : __('call.voice');
                $title = __('notif.incoming_call', ['type' => $typeLabel]);
                $body = __('notif.incoming_call_body', ['name' => $name]);
                break;
            case 'friend_request':
                $title = __('notif.friend_request');
                $body = __('notif.friend_request_body', ['name' => $name]);
                break;
            case 'friend_accepted':
                $title = __('notif.friend_accepted');
                $body = __('notif.friend_accepted_body', ['name' => $name]);
                break;
            case 'group_invite':
                $title = __('notif.group_invite');
                $body = __('notif.group_invite_body', ['name' => $name, 'group' => $group]);
                break;
            case 'group_message':
                $title = __('notif.group_message');
                $body = __('notif.group_message_body', ['name' => $name, 'group' => $group]);
                break;
            case 'forum_comment':
                $title = __('notif.forum_comment');
                $body = __('notif.forum_comment_body', ['name' => $name]);
                break;
            case 'message_reaction':
                $title = __('notif.reaction');
                $body = __('notif.reaction_body', ['name' => $name, 'reaction' => $reaction]);
                break;
        }
    }

    return [
        'id' => (int) $row['id'],
        'type' => $type,
        'title' => $title,
        'body' => $body,
        'link' => $row['link'] ?? null,
        'related_id' => $row['related_id'] !== null ? (int) $row['related_id'] : null,
        'is_read' => (int) ($row['is_read'] ?? 0) === 1,
        'created_at' => $row['created_at'] ?? null,
        'relative' => relative_time($row['created_at'] ?? null),
    ];
}

function notification_meta_legacy(array $row): array
{
    $type = (string) ($row['type'] ?? '');
    $relatedId = isset($row['related_id']) && $row['related_id'] !== null ? (int) $row['related_id'] : 0;
    $meta = [];

    try {
        switch ($type) {
            case 'new_message':
                if ($relatedId > 0) {
                    $st = db()->prepare(
                        'SELECT u.username FROM messages m JOIN users u ON u.id = m.sender_id WHERE m.id = ? LIMIT 1'
                    );
                    $st->execute([$relatedId]);
                    $name = $st->fetchColumn();
                    if ($name) {
                        $meta['name'] = (string) $name;
                    }
                }
                break;
            case 'incoming_call':
                if ($relatedId > 0) {
                    $st = db()->prepare(
                        'SELECT c.call_type, u.username
                         FROM voice_call_sessions c
                         JOIN users u ON u.id = c.caller_id
                         WHERE c.id = ? LIMIT 1'
                    );
                    $st->execute([$relatedId]);
                    $r = $st->fetch();
                    if ($r) {
                        $meta['name'] = (string) $r['username'];
                        $meta['call_type'] = (string) $r['call_type'];
                    }
                }
                break;
            case 'friend_request':
                if ($relatedId > 0) {
                    $st = db()->prepare(
                        'SELECT u.username FROM friend_requests fr
                         JOIN users u ON u.id = fr.sender_id WHERE fr.id = ? LIMIT 1'
                    );
                    $st->execute([$relatedId]);
                    $name = $st->fetchColumn();
                    if ($name) {
                        $meta['name'] = (string) $name;
                    }
                }
                break;
            case 'friend_accepted':
                if ($relatedId > 0) {
                    $st = db()->prepare('SELECT username FROM users WHERE id = ? LIMIT 1');
                    $st->execute([$relatedId]);
                    $name = $st->fetchColumn();
                    if ($name) {
                        $meta['name'] = (string) $name;
                    }
                }
                break;
            case 'group_invite':
                if ($relatedId > 0) {
                    $st = db()->prepare('SELECT name FROM group_chats WHERE id = ? LIMIT 1');
                    $st->execute([$relatedId]);
                    $g = $st->fetchColumn();
                    if ($g) {
                        $meta['group'] = (string) $g;
                    }
                }
                break;
            case 'group_message':
                if ($relatedId > 0) {
                    $st = db()->prepare(
                        'SELECT u.username, g.name AS group_name
                         FROM group_messages gm
                         JOIN users u ON u.id = gm.sender_id
                         JOIN group_chats g ON g.id = gm.group_id
                         WHERE gm.id = ? LIMIT 1'
                    );
                    $st->execute([$relatedId]);
                    $r = $st->fetch();
                    if ($r) {
                        $meta['name'] = (string) $r['username'];
                        $meta['group'] = (string) $r['group_name'];
                    }
                }
                break;
            case 'forum_comment':
                // related_id is post id — cannot recover commenter from post alone
                break;
            case 'message_reaction':
                break;
        }
    } catch (Throwable $e) {
        // ignore rebuild failures
    }

    return $meta;
}

function format_datetime(?string $dt): ?string
{
    if (!$dt) {
        return null;
    }
    $t = strtotime($dt);
    return $t === false ? null : date('c', $t);
}

/**
 * Insert a one-time chat log for a finished call (voice/video).
 */
function record_call_chat_message(array $call): void
{
    $callId = (int) ($call['id'] ?? 0);
    if ($callId <= 0) {
        return;
    }
    $status = (string) ($call['status'] ?? '');
    if (!in_array($status, ['ended', 'rejected', 'missed', 'cancelled'], true)) {
        return;
    }

    try {
        $chk = db()->prepare(
            "SELECT id FROM messages WHERE message_type = 'call' AND file_name = ? LIMIT 1"
        );
        $chk->execute(['call_' . $callId]);
        if ($chk->fetch()) {
            return;
        }

        $duration = 0;
        if (!empty($call['answered_at']) && !empty($call['ended_at'])) {
            $a = strtotime((string) $call['answered_at']);
            $e = strtotime((string) $call['ended_at']);
            if ($a !== false && $e !== false && $e >= $a) {
                $duration = $e - $a;
            }
        }

        $callerId = (int) $call['caller_id'];
        $calleeId = (int) $call['callee_id'];
        $caller = get_user_by_id($callerId);
        $callerName = $caller['username'] ?? '';

        $payload = json_encode([
            'call_id'         => $callId,
            'call_type'       => $call['call_type'] ?? 'voice',
            'status'          => $status,
            'duration'        => $duration,
            'caller_id'       => $callerId,
            'callee_id'       => $calleeId,
            'caller_username' => $callerName,
        ], JSON_UNESCAPED_UNICODE);

        db()->prepare(
            'INSERT INTO messages
             (sender_id, receiver_id, message_type, body, file_name, voice_duration, delivery_status, delivered_at, read_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())'
        )->execute([
            $callerId,
            $calleeId,
            'call',
            $payload,
            'call_' . $callId,
            $duration,
            'read',
        ]);
    } catch (Throwable $e) {
        app_log('calls', 'record_call_chat_message failed: ' . $e->getMessage());
    }
}

function format_call_duration(int $seconds): string
{
    $seconds = max(0, $seconds);
    $h = intdiv($seconds, 3600);
    $m = intdiv($seconds % 3600, 60);
    $s = $seconds % 60;
    if ($h > 0) {
        return sprintf('%d:%02d:%02d', $h, $m, $s);
    }
    return sprintf('%d:%02d', $m, $s);
}

function call_status_label(string $status, int $duration = 0): string
{
    return match ($status) {
        'ended' => $duration > 0
            ? format_call_duration($duration)
            : __('call.status_ended'),
        'rejected' => __('call.status_rejected'),
        'missed' => __('call.status_missed'),
        'cancelled' => __('call.status_cancelled'),
        default => __('call.status_ended'),
    };
}

/**
 * @param bool $outgoing true if viewer was the caller
 */
function call_title_label(array $meta, bool $outgoing, ?string $fallbackName = null): string
{
    $isVideo = ($meta['call_type'] ?? 'voice') === 'video';
    $typeLabel = $isVideo ? __('call.video') : __('call.voice');
    $name = $meta['caller_username'] ?? $fallbackName ?? '';

    if ($outgoing) {
        return __('call.title_outgoing', ['type' => $typeLabel]);
    }
    if ($name !== '') {
        return __('call.title_incoming_named', ['name' => $name, 'type' => $typeLabel]);
    }
    return __('call.title_incoming', ['type' => $typeLabel]);
}

function call_log_preview(string $bodyJson, ?int $viewerId = null): string
{
    $data = json_decode($bodyJson, true);
    if (!is_array($data)) {
        return __('msg.type_call');
    }
    $outgoing = $viewerId !== null && (int) ($data['caller_id'] ?? 0) === $viewerId;
    $title = call_title_label($data, $outgoing);
    $status = call_status_label((string) ($data['status'] ?? 'ended'), (int) ($data['duration'] ?? 0));
    return $title . ' · ' . $status;
}

function relative_time(?string $dt): string
{
    if (!$dt) {
        return '';
    }
    $t = strtotime($dt);
    if ($t === false) {
        return '';
    }
    $diff = time() - $t;
    if ($diff < 60) {
        return __('time.just_now');
    }
    if ($diff < 3600) {
        return __('time.minutes_ago', ['n' => (string) (int) floor($diff / 60)]);
    }
    if ($diff < 86400) {
        return __('time.hours_ago', ['n' => (string) (int) floor($diff / 3600)]);
    }
    return date('M j, Y', $t);
}

function group_member_role(int $groupId, int $userId): ?string
{
    $stmt = db()->prepare('SELECT role FROM group_members WHERE group_id = ? AND user_id = ? LIMIT 1');
    $stmt->execute([$groupId, $userId]);
    $row = $stmt->fetch();
    return $row ? $row['role'] : null;
}

function can_manage_group_member(string $actorRole, string $targetRole): bool
{
    if ($actorRole === 'owner') {
        return true;
    }
    if ($actorRole === 'admin') {
        return $targetRole === 'member';
    }
    return false;
}

function store_uploaded_file(array $file, string $subdir, array $allowedExt, array $allowedMime, int $maxSize): array
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'error' => __('upload.failed')];
    }
    if (($file['size'] ?? 0) <= 0 || $file['size'] > $maxSize) {
        return ['ok' => false, 'error' => __('upload.size')];
    }

    $original = (string) ($file['name'] ?? 'file');
    $ext = extension_of($original);
    if (!in_array($ext, $allowedExt, true)) {
        return ['ok' => false, 'error' => __('upload.type')];
    }

    $tmp = (string) $file['tmp_name'];
    $mime = detect_mime($tmp);
    if (!in_array($mime, $allowedMime, true)) {
        // Browsers often label voice blobs oddly (webm/mp4/octet-stream)
        $voiceLike = in_array($ext, ['webm', 'ogg', 'mp3', 'wav', 'm4a', 'mp4', 'aac'], true)
            && (
                str_contains($mime, 'webm')
                || str_contains($mime, 'audio')
                || str_contains($mime, 'video')
                || $mime === 'application/octet-stream'
            );
        if (!$voiceLike) {
            return ['ok' => false, 'error' => __('upload.content')];
        }
    }

    $storedName = safe_filename($original);
    $destDir = UPLOAD_ROOT . DIRECTORY_SEPARATOR . $subdir;
    if (!is_dir($destDir)) {
        mkdir($destDir, 0755, true);
    }
    $dest = $destDir . DIRECTORY_SEPARATOR . $storedName;
    if (!move_uploaded_file($tmp, $dest)) {
        return ['ok' => false, 'error' => __('upload.save_fail')];
    }

    return [
        'ok'        => true,
        'path'      => $subdir . '/' . $storedName,
        'file_name' => basename($original),
        'mime_type' => $mime,
        'file_size' => (int) $file['size'],
    ];
}

function api_action(): string
{
    return str_input('action');
}
