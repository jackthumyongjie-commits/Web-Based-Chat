<?php
/**
 * WebConnect — user & admin session authentication
 */

declare(strict_types=1);

function start_app_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    $secure = request_is_https();

    session_name(SESSION_NAME);
    session_set_cookie_params([
        'lifetime' => SESSION_LIFETIME,
        'path'     => '/',
        'secure'   => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

function start_admin_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        // If a user session is already open under a different name, close and reopen
        if (session_name() === ADMIN_SESSION_NAME) {
            return;
        }
        session_write_close();
    }

    $secure = request_is_https();

    session_name(ADMIN_SESSION_NAME);
    session_set_cookie_params([
        'lifetime' => SESSION_LIFETIME,
        'path'     => '/',
        'secure'   => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

function current_user_id(): ?int
{
    return isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
}

function current_user(): ?array
{
    $id = current_user_id();
    if (!$id) {
        return null;
    }
    static $cache = null;
    static $cacheId = null;
    if ($cache !== null && $cacheId === $id) {
        return $cache;
    }
    $user = get_user_by_id($id);
    if (!$user || !(int) $user['is_active']) {
        logout_user();
        return null;
    }
    $cache = $user;
    $cacheId = $id;
    return $cache;
}

function login_user(array $user): void
{
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int) $user['id'];
    $_SESSION['username'] = $user['username'];
    csrf_token();
    touch_presence((int) $user['id'], 'online');
}

function logout_user(): void
{
    $uid = current_user_id();
    if ($uid) {
        try {
            $now = (new DateTimeImmutable('now'))->format('Y-m-d H:i:s');
            $stmt = db()->prepare('UPDATE users SET presence = ?, last_seen_at = ?, last_activity_at = ? WHERE id = ?');
            $stmt->execute(['offline', $now, $now, $uid]);
        } catch (Throwable $e) {
            app_log('auth', 'Logout presence update failed: ' . $e->getMessage());
        }
    }
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'] ?? '', (bool) $p['secure'], (bool) $p['httponly']);
    }
    session_destroy();
}

function require_login(): array
{
    $user = current_user();
    if (!$user) {
        if (is_api_request()) {
            json_error(__('common.unauthorized'), 401);
        }
        redirect('login.php');
    }
    return $user;
}

function require_guest(): void
{
    if (current_user()) {
        redirect('chat.php');
    }
}

function is_api_request(): bool
{
    $uri = $_SERVER['REQUEST_URI'] ?? '';
    return str_contains($uri, '/api/')
        || (isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json'))
        || (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');
}

function current_admin_id(): ?int
{
    return isset($_SESSION['admin_id']) ? (int) $_SESSION['admin_id'] : null;
}

function current_admin(): ?array
{
    $id = current_admin_id();
    if (!$id) {
        return null;
    }
    $stmt = db()->prepare('SELECT * FROM admins WHERE id = ? AND is_active = 1 LIMIT 1');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function login_admin(array $admin): void
{
    session_regenerate_id(true);
    $_SESSION['admin_id'] = (int) $admin['id'];
    $_SESSION['admin_username'] = $admin['username'];
    csrf_token();
    $stmt = db()->prepare('UPDATE admins SET last_login_at = NOW() WHERE id = ?');
    $stmt->execute([(int) $admin['id']]);
}

function logout_admin(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'] ?? '', (bool) $p['secure'], (bool) $p['httponly']);
    }
    session_destroy();
}

function require_admin(): array
{
    $admin = current_admin();
    if (!$admin) {
        redirect('admin/login.php');
    }
    return $admin;
}
