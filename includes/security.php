<?php
/**
 * WebConnect — security helpers
 */

declare(strict_types=1);

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function app_log(string $channel, string $message): void
{
    $dir = defined('LOG_ROOT') ? LOG_ROOT : (dirname(__DIR__) . '/logs');
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    $file = $dir . DIRECTORY_SEPARATOR . $channel . '-' . date('Y-m-d') . '.log';
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL;
    @file_put_contents($file, $line, FILE_APPEND | LOCK_EX);
}

function csrf_token(): string
{
    if (empty($_SESSION[CSRF_TOKEN_KEY])) {
        $_SESSION[CSRF_TOKEN_KEY] = bin2hex(random_bytes(32));
    }
    return $_SESSION[CSRF_TOKEN_KEY];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function verify_csrf(?string $token): bool
{
    if ($token === null || $token === '' || empty($_SESSION[CSRF_TOKEN_KEY])) {
        return false;
    }
    return hash_equals($_SESSION[CSRF_TOKEN_KEY], $token);
}

function require_csrf(): void
{
    $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
    if (!verify_csrf(is_string($token) ? $token : null)) {
        json_error(__('common.invalid_token'), 419);
    }
}

function client_ip(): string
{
    // Cloudflare passes the visitor IP here when proxied / tunneled
    $cf = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? '';
    if (is_string($cf) && filter_var($cf, FILTER_VALIDATE_IP)) {
        return $cf;
    }
    $xff = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '';
    if (is_string($xff) && $xff !== '') {
        $first = trim(explode(',', $xff)[0]);
        if (filter_var($first, FILTER_VALIDATE_IP)) {
            return $first;
        }
    }
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

/** True when the public request is HTTPS (incl. Cloudflare / reverse proxy). */
function request_is_https(): bool
{
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        return true;
    }
    if ((int) ($_SERVER['SERVER_PORT'] ?? 80) === 443) {
        return true;
    }
    $fwd = strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''));
    if ($fwd === 'https') {
        return true;
    }
    $cfVisitor = (string) ($_SERVER['HTTP_CF_VISITOR'] ?? '');
    if ($cfVisitor !== '' && str_contains($cfVisitor, '"scheme":"https"')) {
        return true;
    }
    return false;
}

/**
 * Public base URL for links / redirects.
 * With APP_URL_AUTO=true (Cloudflare Tunnel testing), Host header is used.
 */
function app_url(): string
{
    if (defined('APP_URL_AUTO') && APP_URL_AUTO && !empty($_SERVER['HTTP_HOST'])) {
        $scheme = request_is_https() ? 'https' : 'http';
        $base = defined('APP_BASE_PATH') ? (string) APP_BASE_PATH : '';
        $base = '/' . trim(str_replace('\\', '/', $base), '/');
        if ($base === '/') {
            $base = '';
        }
        return $scheme . '://' . $_SERVER['HTTP_HOST'] . $base;
    }
    return rtrim((string) APP_URL, '/');
}

/**
 * Simple DB-backed rate limiter.
 * @param array{0:int,1:int} $limit [maxHits, windowSeconds]
 */
function rate_limit(string $action, array $limit, ?int $userId = null): bool
{
    [$maxHits, $window] = $limit;
    $key = $action . ':' . ($userId !== null ? 'u' . $userId : client_ip());

    try {
        $pdo = db();
        $pdo->beginTransaction();

        $stmt = $pdo->prepare('SELECT id, hits, window_start FROM rate_limits WHERE rate_key = ? FOR UPDATE');
        $stmt->execute([$key]);
        $row = $stmt->fetch();

        $now = new DateTimeImmutable('now');

        if (!$row) {
            $ins = $pdo->prepare('INSERT INTO rate_limits (rate_key, hits, window_start) VALUES (?, 1, ?)');
            $ins->execute([$key, $now->format('Y-m-d H:i:s')]);
            $pdo->commit();
            return true;
        }

        $windowStart = new DateTimeImmutable($row['window_start']);
        $elapsed = $now->getTimestamp() - $windowStart->getTimestamp();

        if ($elapsed >= $window) {
            $upd = $pdo->prepare('UPDATE rate_limits SET hits = 1, window_start = ? WHERE id = ?');
            $upd->execute([$now->format('Y-m-d H:i:s'), $row['id']]);
            $pdo->commit();
            return true;
        }

        if ((int) $row['hits'] >= $maxHits) {
            $pdo->commit();
            return false;
        }

        $upd = $pdo->prepare('UPDATE rate_limits SET hits = hits + 1 WHERE id = ?');
        $upd->execute([$row['id']]);
        $pdo->commit();
        return true;
    } catch (Throwable $e) {
        if (db()->inTransaction()) {
            db()->rollBack();
        }
        app_log('security', 'Rate limit error: ' . $e->getMessage());
        return true; // fail open to avoid locking users out on DB issues
    }
}

function require_rate_limit(string $action, array $limit, ?int $userId = null): void
{
    if (!rate_limit($action, $limit, $userId)) {
        json_error(__('common.too_many'), 429);
    }
}

function safe_filename(string $original): string
{
    $ext = strtolower(pathinfo($original, PATHINFO_EXTENSION));
    $ext = preg_replace('/[^a-z0-9]/', '', $ext) ?? '';
    $rand = bin2hex(random_bytes(16));
    return $ext !== '' ? ($rand . '.' . $ext) : $rand;
}

function detect_mime(string $tmpPath): string
{
    if (!is_file($tmpPath)) {
        return 'application/octet-stream';
    }
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($tmpPath);
    return is_string($mime) ? $mime : 'application/octet-stream';
}

function extension_of(string $filename): string
{
    return strtolower(pathinfo($filename, PATHINFO_EXTENSION));
}

function is_safe_upload_path(string $relativePath): bool
{
    if ($relativePath === '' || str_contains($relativePath, '..') || str_starts_with($relativePath, '/') || str_starts_with($relativePath, '\\')) {
        return false;
    }
    $normalized = str_replace('\\', '/', $relativePath);
    if (!preg_match('#^(avatars|images|files|voices|groups)/[a-zA-Z0-9._-]+$#', $normalized)) {
        return false;
    }
    return true;
}

function absolute_upload_path(string $relativePath): ?string
{
    if (!is_safe_upload_path($relativePath)) {
        return null;
    }
    $full = UPLOAD_ROOT . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
    $realUpload = realpath(UPLOAD_ROOT);
    $realFile = realpath($full);
    if ($realUpload === false || $realFile === false) {
        return null;
    }
    if (!str_starts_with($realFile, $realUpload)) {
        return null;
    }
    return $realFile;
}

function set_security_headers(): void
{
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(self), microphone=(self), geolocation=()');
}
