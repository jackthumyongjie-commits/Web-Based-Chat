<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/bootstrap.php';

$user = require_login();
$uid = (int) $user['id'];
$action = api_action();

switch ($action) {
    case 'search':
        $q = str_input('q');
        if (strlen($q) < 2) {
            json_error(__('friends.enter_2'));
        }
        $like = '%' . $q . '%';
        $stmt = db()->prepare(
            'SELECT id, username, email, avatar, status_message, presence, last_seen_at, last_activity_at, is_active
             FROM users
             WHERE is_active = 1 AND id <> ? AND (username LIKE ? OR email LIKE ?)
             ORDER BY username ASC LIMIT 30'
        );
        $stmt->execute([$uid, $like, $like]);
        $results = [];
        while ($row = $stmt->fetch()) {
            $fid = (int) $row['id'];
            $item = public_user($row);
            $item['email'] = $row['email'];
            $item['is_friend'] = users_are_friends($uid, $fid);
            $item['is_blocked'] = is_blocked_either($uid, $fid);
            $item['request_status'] = null;
            $req = db()->prepare(
                'SELECT id, sender_id, receiver_id, status FROM friend_requests
                 WHERE ((sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?))
                 AND status = ? ORDER BY id DESC LIMIT 1'
            );
            $req->execute([$uid, $fid, $fid, $uid, 'pending']);
            $r = $req->fetch();
            if ($r) {
                $item['request_status'] = $r['status'];
                $item['request_id'] = (int) $r['id'];
                $item['request_direction'] = ((int) $r['sender_id'] === $uid) ? 'outgoing' : 'incoming';
            }
            $results[] = $item;
        }
        json_success(__('common.ok'), ['users' => $results]);

    case 'list':
        $stmt = db()->prepare(
            'SELECT u.id, u.username, u.avatar, u.status_message, u.presence, u.last_seen_at, u.last_activity_at, u.is_active, f.created_at AS friends_since
             FROM friendships f
             JOIN users u ON u.id = IF(f.user_low_id = ?, f.user_high_id, f.user_low_id)
             WHERE (f.user_low_id = ? OR f.user_high_id = ?) AND u.is_active = 1
             ORDER BY u.username ASC'
        );
        $stmt->execute([$uid, $uid, $uid]);
        $friends = [];
        while ($row = $stmt->fetch()) {
            $friends[] = public_user($row) + ['friends_since' => $row['friends_since']];
        }
        json_success(__('common.ok'), ['friends' => $friends]);

    case 'requests':
        $incoming = db()->prepare(
            'SELECT fr.id, fr.sender_id, fr.created_at, u.username, u.avatar, u.status_message, u.presence, u.last_seen_at, u.last_activity_at, u.is_active
             FROM friend_requests fr
             JOIN users u ON u.id = fr.sender_id
             WHERE fr.receiver_id = ? AND fr.status = ? ORDER BY fr.created_at DESC'
        );
        $incoming->execute([$uid, 'pending']);
        $out = [];
        while ($row = $incoming->fetch()) {
            $out[] = [
                'id' => (int) $row['id'],
                'created_at' => $row['created_at'],
                'user' => public_user([
                    'id' => $row['sender_id'],
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
        $outgoing = db()->prepare(
            'SELECT fr.id, fr.receiver_id, fr.created_at, u.username, u.avatar, u.status_message, u.presence, u.last_seen_at, u.last_activity_at, u.is_active
             FROM friend_requests fr
             JOIN users u ON u.id = fr.receiver_id
             WHERE fr.sender_id = ? AND fr.status = ? ORDER BY fr.created_at DESC'
        );
        $outgoing->execute([$uid, 'pending']);
        $outg = [];
        while ($row = $outgoing->fetch()) {
            $outg[] = [
                'id' => (int) $row['id'],
                'created_at' => $row['created_at'],
                'user' => public_user([
                    'id' => $row['receiver_id'],
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
        json_success(__('common.ok'), ['incoming' => $out, 'outgoing' => $outg]);

    case 'send_request':
        require_csrf();
        require_rate_limit('friend', RATE_FRIEND, $uid);
        $targetId = int_input('user_id');
        if ($targetId <= 0 || $targetId === $uid) {
            json_error(__('friends.invalid_user'));
        }
        $target = get_user_by_id($targetId);
        if (!$target || !(int) $target['is_active']) {
            json_error(__('friends.user_not_found'));
        }
        if (is_blocked_either($uid, $targetId)) {
            json_error(__('friends.unable_request'));
        }
        if (users_are_friends($uid, $targetId)) {
            json_error(__('friends.already'));
        }
        $pending = db()->prepare(
            'SELECT id, sender_id, status FROM friend_requests
             WHERE ((sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?))
             AND status = ? LIMIT 1'
        );
        $pending->execute([$uid, $targetId, $targetId, $uid, 'pending']);
        if ($pending->fetch()) {
            json_error(__('friends.pending_exists'));
        }
        // Upsert-like: insert or update cancelled/rejected
        $existing = db()->prepare(
            'SELECT id, status FROM friend_requests WHERE sender_id = ? AND receiver_id = ? LIMIT 1'
        );
        $existing->execute([$uid, $targetId]);
        $ex = $existing->fetch();
        if ($ex) {
            $upd = db()->prepare('UPDATE friend_requests SET status = ?, updated_at = NOW() WHERE id = ?');
            $upd->execute(['pending', $ex['id']]);
            $reqId = (int) $ex['id'];
        } else {
            $ins = db()->prepare(
                'INSERT INTO friend_requests (sender_id, receiver_id, status) VALUES (?, ?, ?)'
            );
            $ins->execute([$uid, $targetId, 'pending']);
            $reqId = (int) db()->lastInsertId();
        }
        create_notification(
            $targetId,
            'friend_request',
            ['name' => $user['username']],
            'friends.php',
            $reqId
        );
        json_success(__('friends.request_sent'), ['request_id' => $reqId]);

    case 'accept':
        require_csrf();
        $reqId = int_input('request_id');
        $stmt = db()->prepare(
            'SELECT * FROM friend_requests WHERE id = ? AND receiver_id = ? AND status = ? LIMIT 1'
        );
        $stmt->execute([$reqId, $uid, 'pending']);
        $req = $stmt->fetch();
        if (!$req) {
            json_error(__('friends.req_not_found'));
        }
        $senderId = (int) $req['sender_id'];
        if (is_blocked_either($uid, $senderId)) {
            json_error(__('friends.unable_accept'));
        }
        $pdo = db();
        $pdo->beginTransaction();
        try {
            $pdo->prepare('UPDATE friend_requests SET status = ?, updated_at = NOW() WHERE id = ?')
                ->execute(['accepted', $reqId]);
            $low = min($uid, $senderId);
            $high = max($uid, $senderId);
            $pdo->prepare(
                'INSERT IGNORE INTO friendships (user_low_id, user_high_id) VALUES (?, ?)'
            )->execute([$low, $high]);
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            app_log('friends', $e->getMessage());
            json_error(__('friends.unable_accept_err'));
        }
        create_notification(
            $senderId,
            'friend_accepted',
            ['name' => $user['username']],
            'friends.php',
            $uid
        );
        json_success(__('friends.accepted'));

    case 'reject':
        require_csrf();
        $reqId = int_input('request_id');
        $stmt = db()->prepare(
            'UPDATE friend_requests SET status = ?, updated_at = NOW()
             WHERE id = ? AND receiver_id = ? AND status = ?'
        );
        $stmt->execute(['rejected', $reqId, $uid, 'pending']);
        if ($stmt->rowCount() === 0) {
            json_error(__('friends.req_not_found'));
        }
        json_success(__('friends.rejected'));

    case 'cancel':
        require_csrf();
        $reqId = int_input('request_id');
        $stmt = db()->prepare(
            'UPDATE friend_requests SET status = ?, updated_at = NOW()
             WHERE id = ? AND sender_id = ? AND status = ?'
        );
        $stmt->execute(['cancelled', $reqId, $uid, 'pending']);
        if ($stmt->rowCount() === 0) {
            json_error(__('friends.req_not_found'));
        }
        json_success(__('friends.cancelled'));

    case 'remove':
        require_csrf();
        $friendId = int_input('user_id');
        if ($friendId <= 0) {
            json_error(__('friends.invalid_user'));
        }
        $low = min($uid, $friendId);
        $high = max($uid, $friendId);
        $stmt = db()->prepare('DELETE FROM friendships WHERE user_low_id = ? AND user_high_id = ?');
        $stmt->execute([$low, $high]);
        json_success(__('friends.removed'));

    default:
        json_error(__('common.unknown_action'), 404);
}
