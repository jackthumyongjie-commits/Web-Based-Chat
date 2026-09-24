<?php
/**
 * WebConnect — application bootstrap
 */

declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/security.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/i18n.php';

set_security_headers();

// Default to user session unless admin area already started one
if (session_status() !== PHP_SESSION_ACTIVE) {
    $script = $_SERVER['SCRIPT_NAME'] ?? '';
    if (str_contains(str_replace('\\', '/', $script), '/admin/')) {
        start_admin_session();
    } else {
        start_app_session();
    }
}

init_language();

if (defined('APP_DEBUG') && APP_DEBUG) {
    error_reporting(E_ALL);
    ini_set('display_errors', '0');
} else {
    error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED);
    ini_set('display_errors', '0');
}

set_exception_handler(static function (Throwable $e): void {
    app_log('app', $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    if (is_api_request()) {
        json_error(__('common.error'), 500);
    }
    http_response_code(500);
    echo e(__('common.unexpected'));
    exit;
});
