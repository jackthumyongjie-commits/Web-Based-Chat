<?php
/**
 * One-time cPanel deploy checker.
 * Open in browser once after upload, then DELETE this file.
 */
declare(strict_types=1);

header('Content-Type: text/plain; charset=utf-8');

$ok = true;
function line(string $label, bool $pass, string $detail = ''): void
{
    global $ok;
    if (!$pass) {
        $ok = false;
    }
    echo ($pass ? '[OK]   ' : '[FAIL] ') . $label . ($detail !== '' ? " — {$detail}" : '') . PHP_EOL;
}

echo "WebChat deploy check\n====================\n\n";

line('PHP version >= 8.0', version_compare(PHP_VERSION, '8.0.0', '>='), PHP_VERSION);
line('PDO MySQL', extension_loaded('pdo_mysql'));
line('fileinfo', extension_loaded('fileinfo'));
line('json', extension_loaded('json'));
line('mbstring', extension_loaded('mbstring') || function_exists('mb_strlen'));
line('openssl', extension_loaded('openssl'));

$local = __DIR__ . '/includes/config.local.php';
line('config.local.php exists', is_file($local), $local);

require_once __DIR__ . '/includes/bootstrap.php';

line('APP_DEBUG is false (prod)', !(defined('APP_DEBUG') && APP_DEBUG));
line('ALLOW_DEMO_ADMIN_SEED off', !(defined('ALLOW_DEMO_ADMIN_SEED') && ALLOW_DEMO_ADMIN_SEED));
line('APP_URL_AUTO off (prod)', !(defined('APP_URL_AUTO') && APP_URL_AUTO));
line('APP_URL set', defined('APP_URL') && APP_URL !== '' && !str_contains(APP_URL, 'localhost'), APP_URL);
line('APP_URL uses https', defined('APP_URL') && str_starts_with(APP_URL, 'https://'), APP_URL);

$dirs = [
    'uploads' => UPLOAD_ROOT,
    'uploads/avatars' => UPLOAD_ROOT . '/avatars',
    'uploads/images' => UPLOAD_ROOT . '/images',
    'uploads/files' => UPLOAD_ROOT . '/files',
    'uploads/voices' => UPLOAD_ROOT . '/voices',
    'uploads/groups' => UPLOAD_ROOT . '/groups',
    'logs' => LOG_ROOT,
];
foreach ($dirs as $label => $path) {
    if (!is_dir($path)) {
        @mkdir($path, 0755, true);
    }
    line("writable {$label}", is_dir($path) && is_writable($path), $path);
}

try {
    $pdo = db();
    $n = (int) $pdo->query('SELECT COUNT(*) FROM admins')->fetchColumn();
    line('DB connection', true, DB_NAME);
    line('admins table has rows', $n > 0, "count={$n}");
} catch (Throwable $e) {
    line('DB connection', false, $e->getMessage());
}

echo "\n" . ($ok
    ? "All critical checks passed. DELETE deploy_check.php now.\n"
    : "Fix FAIL items, re-run, then DELETE deploy_check.php.\n");
