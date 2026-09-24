<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/bootstrap.php';

$user = require_login();
$uid = (int) $user['id'];
$action = api_action();

switch ($action) {
    case 'list':
        $stmt = db()->prepare(
            'SELECT b.id, b.blocked_id, b.created_at, u.username, u.avatar, u.status_message, u.presence, u.last_seen_at, u.last_activity_at, u.is_active
             FROM user_blocks b
             JOIN users u ON u.id = b.blocked_id
             WHERE b.blocker_id = ?
             ORDER BY b.created_at DESC'
        );
        $stmt->execute([$uid]);
        $list = [];
        while ($row = $stmt->fetch()) {
            $list[] = [
                'id' => (int) $row['id'],
                'created_at' => $row['created_at'],
                'user' => public_user([
                    'id' => $row['blocked_id'],
                    'username' => $row['username'],
                    'avatar' => $row['avatar'],
                    'status_message' => $row['status_message'],
                    'presence' => $row['presence'],
                    'last_seen_at' => $row['last_seen_at'],
                    'last_activity_at' => $row['last_activity_at'],
                    'is_active' => $row['is_active'],
                ]),
            ];
        }
        json_success(__('common.ok'), ['blocks' => $list]);

    case 'block':
        require_csrf();
        $targetId = int_input('user_id');
        if ($targetId <= 0 || $targetId === $uid) {
            json_error(__('friends.invalid_user'));
        }
        $target = get_user_by_id($targetId);
        if (!$target) {
            json_error(__('friends.user_not_found'));
        }
        $ins = db()->prepare('INSERT IGNORE INTO user_blocks (blocker_id, blocked_id) VALUES (?, ?)');
        $ins->execute([$uid, $targetId]);
        // Remove friendship
        $low = min($uid, $targetId);
        $high = max($uid, $targetId);
        db()->prepare('DELETE FROM friendships WHERE user_low_id = ? AND user_high_id = ?')->execute([$low, $high]);
        db()->prepare(
            "UPDATE friend_requests SET status = 'cancelled', updated_at = NOW()
             WHERE ((sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?)) AND status = 'pending'"
        )->execute([$uid, $targetId, $targetId, $uid]);
        json_success(__('block.ok'));

    case 'unblock':
        require_csrf();
        $targetId = int_input('user_id');
        $stmt = db()->prepare('DELETE FROM user_blocks WHERE blocker_id = ? AND blocked_id = ?');
        $stmt->execute([$uid, $targetId]);
        json_success(__('block.unblock_ok'));

    default:
        json_error(__('common.unknown_action'), 404);
}
