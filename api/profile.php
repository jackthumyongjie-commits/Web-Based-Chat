<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/bootstrap.php';

$user = require_login();
$action = api_action();
$uid = (int) $user['id'];

switch ($action) {
    case 'get':
        json_success(__('common.ok'), [
            'id'             => $uid,
            'username'       => $user['username'],
            'email'          => $user['email'],
            'avatar'         => $user['avatar'],
            'avatar_url'     => avatar_url($user['avatar'], $user['username']),
            'status_message' => $user['status_message'],
            'presence'       => compute_presence($user),
            'created_at'     => $user['created_at'],
        ]);

    case 'update':
        require_csrf();
        $username = str_input('username', $user['username']);
        $status = str_input('status_message');
        if (strlen($status) > 255) {
            json_error(__('profile.status_long'));
        }
        $uErr = validate_username($username);
        if ($uErr) {
            json_error($uErr);
        }
        if (strcasecmp($username, $user['username']) !== 0) {
            $chk = db()->prepare('SELECT id FROM users WHERE username = ? AND id <> ? LIMIT 1');
            $chk->execute([$username, $uid]);
            if ($chk->fetch()) {
                json_error(__('profile.username_taken'));
            }
        }
        $stmt = db()->prepare('UPDATE users SET username = ?, status_message = ? WHERE id = ?');
        $stmt->execute([$username, $status, $uid]);
        $_SESSION['username'] = $username;
        json_success(__('profile.updated'));

    case 'change_password':
        require_csrf();
        $current = (string) (input('current_password') ?? '');
        $new = (string) (input('new_password') ?? '');
        $confirm = (string) (input('confirm_password') ?? '');
        $stmt = db()->prepare('SELECT password_hash FROM users WHERE id = ?');
        $stmt->execute([$uid]);
        $row = $stmt->fetch();
        if (!$row || !password_verify($current, $row['password_hash'])) {
            json_error(__('profile.wrong_password'));
        }
        $pErr = validate_password($new);
        if ($pErr) {
            json_error($pErr);
        }
        if ($new !== $confirm) {
            json_error(__('auth.password_mismatch'));
        }
        $hash = password_hash($new, PASSWORD_DEFAULT);
        $upd = db()->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
        $upd->execute([$hash, $uid]);
        session_regenerate_id(true);
        json_success(__('profile.password_changed'));

    case 'upload_avatar':
        require_csrf();
        require_rate_limit('upload', RATE_UPLOAD, $uid);
        if (empty($_FILES['avatar'])) {
            json_error(__('profile.no_avatar'));
        }
        $result = store_uploaded_file(
            $_FILES['avatar'],
            'avatars',
            ALLOWED_IMAGE_EXT,
            ALLOWED_IMAGE_MIME,
            MAX_AVATAR_SIZE
        );
        if (!$result['ok']) {
            json_error($result['error']);
        }
        // Remove old avatar if local
        if (!empty($user['avatar'])) {
            $old = absolute_upload_path($user['avatar']);
            if ($old && is_file($old)) {
                @unlink($old);
            }
        }
        $stmt = db()->prepare('UPDATE users SET avatar = ? WHERE id = ?');
        $stmt->execute([$result['path'], $uid]);
        json_success(__('profile.avatar_updated'), [
            'avatar' => $result['path'],
            'avatar_url' => media_url($result['path']),
        ]);

    default:
        json_error(__('common.unknown_action'), 404);
}
