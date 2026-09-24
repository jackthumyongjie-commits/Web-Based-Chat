<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/bootstrap.php';

$user = require_login();
$uid = (int) $user['id'];
$action = api_action();

switch ($action) {
    case 'list':
        $onlyUnread = (int) input('unread', 0) === 1;
        $sql = 'SELECT * FROM notifications WHERE user_id = ?';
        $params = [$uid];
        if ($onlyUnread) {
            $sql .= ' AND is_read = 0';
        }
        $sql .= ' ORDER BY created_at DESC LIMIT 100';
        $stmt = db()->prepare($sql);
        $stmt->execute($params);
        $items = [];
        while ($row = $stmt->fetch()) {
            $items[] = localize_notification($row);
        }
        json_success(__('common.ok'), ['notifications' => $items]);

    case 'unread_count':
        $stmt = db()->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0');
        $stmt->execute([$uid]);
        json_success(__('common.ok'), ['count' => (int) $stmt->fetchColumn()]);

    case 'mark_read':
        require_csrf();
        $id = int_input('id');
        if ($id > 0) {
            db()->prepare('UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?')->execute([$id, $uid]);
        } else {
            db()->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0')->execute([$uid]);
        }
        json_success(__('notif.marked'));

    default:
        json_error(__('common.unknown_action'), 404);
}
