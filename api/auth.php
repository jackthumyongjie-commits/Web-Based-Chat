<?php
/**
 * Auth API (JSON helpers for SPA-like flows)
 */
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/bootstrap.php';

$action = api_action();

switch ($action) {
    case 'me':
        $user = require_login();
        json_success(__('common.ok'), public_user($user) + ['email' => $user['email']]);

    case 'csrf':
        start_app_session();
        json_success(__('common.ok'), ['csrf_token' => csrf_token()]);

    default:
        json_error(__('common.unknown_action'), 404);
}
