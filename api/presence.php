<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/bootstrap.php';

$user = require_login();
$action = api_action();
$uid = (int) $user['id'];

switch ($action) {
    case 'heartbeat':
        $presence = str_input('presence', 'online');
        if (!in_array($presence, ['online', 'away', 'busy', 'offline'], true)) {
            $presence = 'online';
        }
        touch_presence($uid, $presence);
        // Clean expired typing
        db()->exec('DELETE FROM typing_indicators WHERE expires_at < NOW()');
        json_success(__('common.ok'), ['presence' => $presence, 'server_time' => date('c')]);

    case 'get':
        $ids = input('ids', []);
        if (is_string($ids)) {
            $ids = array_filter(array_map('intval', explode(',', $ids)));
        }
        if (!is_array($ids) || !$ids) {
            json_success(__('common.ok'), ['users' => []]);
        }
        $ids = array_values(array_unique(array_map('intval', $ids)));
        $ids = array_slice($ids, 0, 50);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = db()->prepare(
            "SELECT id, username, avatar, status_message, presence, last_seen_at, last_activity_at, is_active
             FROM users WHERE id IN ($placeholders)"
        );
        $stmt->execute($ids);
        $rows = [];
        while ($row = $stmt->fetch()) {
            $rows[] = public_user($row);
        }
        json_success(__('common.ok'), ['users' => $rows]);

    default:
        json_error(__('common.unknown_action'), 404);
}
