<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/bootstrap.php';

$user = require_login();
$uid = (int) $user['id'];
$action = api_action();

function format_group_message(array $row, int $viewerId): array
{
    $deleted = (int) $row['is_deleted'] === 1;
    return [
        'id' => (int) $row['id'],
        'group_id' => (int) $row['group_id'],
        'sender_id' => (int) $row['sender_id'],
        'sender_username' => $row['sender_username'] ?? '',
        'sender_avatar_url' => avatar_url($row['sender_avatar'] ?? null, $row['sender_username'] ?? ''),
        'message_type' => $row['message_type'],
        'body' => $deleted ? null : $row['body'],
        'file_path' => $deleted ? null : $row['file_path'],
        'file_url' => (!$deleted && !empty($row['file_path'])) ? media_url($row['file_path']) : null,
        'file_name' => $deleted ? null : $row['file_name'],
        'mime_type' => $deleted ? null : $row['mime_type'],
        'file_size' => $deleted ? null : ($row['file_size'] !== null ? (int) $row['file_size'] : null),
        'voice_duration' => $deleted ? null : $row['voice_duration'],
        'reply_to_id' => $row['reply_to_id'] !== null ? (int) $row['reply_to_id'] : null,
        'is_deleted' => $deleted,
        'is_mine' => (int) $row['sender_id'] === $viewerId,
        'created_at' => $row['created_at'],
        'reply' => !empty($row['reply_id']) ? [
            'id' => (int) $row['reply_id'],
            'body' => ((int) $row['reply_deleted'] === 1) ? null : $row['reply_body'],
            'message_type' => $row['reply_type'],
            'is_deleted' => (int) $row['reply_deleted'] === 1,
            'sender_id' => (int) ($row['reply_sender_id'] ?? 0),
        ] : null,
        'reactions' => [],
    ];
}

switch ($action) {
    case 'list':
        $stmt = db()->prepare(
            'SELECT g.*, m.role,
                (SELECT COUNT(*) FROM group_members gm2 WHERE gm2.group_id = g.id) AS member_count
             FROM group_members m
             JOIN group_chats g ON g.id = m.group_id
             WHERE m.user_id = ? AND g.is_active = 1
             ORDER BY g.updated_at DESC'
        );
        $stmt->execute([$uid]);
        $groups = [];
        while ($row = $stmt->fetch()) {
            $groups[] = [
                'id' => (int) $row['id'],
                'name' => $row['name'],
                'description' => $row['description'],
                'avatar_url' => $row['avatar'] ? media_url($row['avatar']) : null,
                'owner_id' => (int) $row['owner_id'],
                'role' => $row['role'],
                'member_count' => (int) $row['member_count'],
                'created_at' => $row['created_at'],
            ];
        }
        json_success(__('common.ok'), ['groups' => $groups]);

    case 'create':
        require_csrf();
        $name = str_input('name');
        $description = str_input('description');
        $memberIds = input('member_ids', []);
        if (!is_array($memberIds)) {
            $memberIds = [];
        }
        if ($name === '' || mb_strlen($name) > 120) {
            json_error(__('groups.name_required'));
        }
        if (mb_strlen($description) > 500) {
            json_error(__('groups.desc_long'));
        }
        $memberIds = array_values(array_unique(array_map('intval', $memberIds)));
        $memberIds = array_filter($memberIds, static fn($id) => $id > 0 && $id !== $uid);
        foreach ($memberIds as $mid) {
            if (!users_are_friends($uid, $mid) || is_blocked_either($uid, $mid)) {
                json_error(__('groups.friends_only_add'));
            }
        }
        $pdo = db();
        $pdo->beginTransaction();
        try {
            $pdo->prepare(
                'INSERT INTO group_chats (name, description, owner_id) VALUES (?, ?, ?)'
            )->execute([$name, $description, $uid]);
            $gid = (int) $pdo->lastInsertId();
            $pdo->prepare(
                'INSERT INTO group_members (group_id, user_id, role) VALUES (?, ?, ?)'
            )->execute([$gid, $uid, 'owner']);
            $ins = $pdo->prepare('INSERT INTO group_members (group_id, user_id, role) VALUES (?, ?, ?)');
            foreach ($memberIds as $mid) {
                $ins->execute([$gid, $mid, 'member']);
                create_notification(
                    $mid,
                    'group_invite',
                    ['name' => $user['username'], 'group' => $name],
                    'group_chat.php?id=' . $gid,
                    $gid
                );
            }
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            app_log('groups', $e->getMessage());
            json_error(__('groups.unable_create'));
        }
        json_success(__('groups.created'), ['group_id' => $gid]);

    case 'get':
        $gid = int_input('group_id');
        $role = group_member_role($gid, $uid);
        if (!$role) {
            json_error(__('groups.not_member'), 403);
        }
        $g = db()->prepare('SELECT * FROM group_chats WHERE id = ? AND is_active = 1');
        $g->execute([$gid]);
        $group = $g->fetch();
        if (!$group) {
            json_error(__('groups.not_found'), 404);
        }
        $members = db()->prepare(
            'SELECT m.role, m.joined_at, u.id, u.username, u.avatar, u.status_message, u.presence, u.last_seen_at, u.last_activity_at, u.is_active
             FROM group_members m JOIN users u ON u.id = m.user_id
             WHERE m.group_id = ? ORDER BY FIELD(m.role, "owner","admin","member"), u.username'
        );
        $members->execute([$gid]);
        $memberList = [];
        while ($row = $members->fetch()) {
            $memberList[] = public_user($row) + ['role' => $row['role'], 'joined_at' => $row['joined_at']];
        }
        json_success(__('common.ok'), [
            'group' => [
                'id' => (int) $group['id'],
                'name' => $group['name'],
                'description' => $group['description'],
                'avatar_url' => $group['avatar'] ? media_url($group['avatar']) : null,
                'owner_id' => (int) $group['owner_id'],
                'my_role' => $role,
                'created_at' => $group['created_at'],
            ],
            'members' => $memberList,
        ]);

    case 'update':
        require_csrf();
        $gid = int_input('group_id');
        $role = group_member_role($gid, $uid);
        if (!in_array($role, ['owner', 'admin'], true)) {
            json_error(__('common.not_allowed'), 403);
        }
        $name = str_input('name');
        $description = str_input('description');
        if ($name === '' || mb_strlen($name) > 120) {
            json_error(__('groups.invalid_name'));
        }
        db()->prepare('UPDATE group_chats SET name = ?, description = ?, updated_at = NOW() WHERE id = ?')
            ->execute([$name, $description, $gid]);
        json_success(__('groups.updated'));

    case 'upload_avatar':
        require_csrf();
        $gid = int_input('group_id');
        $role = group_member_role($gid, $uid);
        if (!in_array($role, ['owner', 'admin'], true)) {
            json_error(__('common.not_allowed'), 403);
        }
        if (empty($_FILES['avatar'])) {
            json_error(__('upload.no_file'));
        }
        $up = store_uploaded_file($_FILES['avatar'], 'groups', ALLOWED_IMAGE_EXT, ALLOWED_IMAGE_MIME, MAX_AVATAR_SIZE);
        if (!$up['ok']) {
            json_error($up['error']);
        }
        db()->prepare('UPDATE group_chats SET avatar = ?, updated_at = NOW() WHERE id = ?')
            ->execute([$up['path'], $gid]);
        json_success(__('groups.avatar_updated'), ['avatar_url' => media_url($up['path'])]);

    case 'add_member':
        require_csrf();
        $gid = int_input('group_id');
        $memberId = int_input('user_id');
        $role = group_member_role($gid, $uid);
        if (!in_array($role, ['owner', 'admin'], true)) {
            json_error(__('common.not_allowed'), 403);
        }
        if (!users_are_friends($uid, $memberId) || is_blocked_either($uid, $memberId)) {
            json_error(__('groups.only_friends'));
        }
        if (group_member_role($gid, $memberId)) {
            json_error(__('groups.already_member'));
        }
        db()->prepare('INSERT INTO group_members (group_id, user_id, role) VALUES (?, ?, ?)')
            ->execute([$gid, $memberId, 'member']);
        $g = db()->prepare('SELECT name FROM group_chats WHERE id = ?');
        $g->execute([$gid]);
        $gn = $g->fetchColumn();
        create_notification(
            $memberId,
            'group_invite',
            ['name' => $user['username'], 'group' => (string) $gn],
            'group_chat.php?id=' . $gid,
            $gid
        );
        json_success(__('groups.member_added'));

    case 'remove_member':
        require_csrf();
        $gid = int_input('group_id');
        $memberId = int_input('user_id');
        $actorRole = group_member_role($gid, $uid);
        $targetRole = group_member_role($gid, $memberId);
        if (!$actorRole || !$targetRole) {
            json_error(__('common.not_found'));
        }
        if ($targetRole === 'owner') {
            json_error(__('groups.cannot_remove_owner'));
        }
        if (!can_manage_group_member($actorRole, $targetRole)) {
            json_error(__('common.not_allowed'), 403);
        }
        db()->prepare('DELETE FROM group_members WHERE group_id = ? AND user_id = ?')->execute([$gid, $memberId]);
        json_success(__('groups.member_removed'));

    case 'set_role':
        require_csrf();
        $gid = int_input('group_id');
        $memberId = int_input('user_id');
        $newRole = str_input('role');
        if (group_member_role($gid, $uid) !== 'owner') {
            json_error(__('groups.only_owner_roles'), 403);
        }
        if (!in_array($newRole, ['admin', 'member'], true)) {
            json_error(__('groups.invalid_role'));
        }
        $targetRole = group_member_role($gid, $memberId);
        if (!$targetRole || $targetRole === 'owner') {
            json_error(__('groups.cannot_change_member'));
        }
        db()->prepare('UPDATE group_members SET role = ? WHERE group_id = ? AND user_id = ?')
            ->execute([$newRole, $gid, $memberId]);
        json_success(__('groups.role_updated'));

    case 'leave':
        require_csrf();
        $gid = int_input('group_id');
        $role = group_member_role($gid, $uid);
        if (!$role) {
            json_error(__('groups.not_member'));
        }
        if ($role === 'owner') {
            json_error(__('groups.owner_leave'));
        }
        db()->prepare('DELETE FROM group_members WHERE group_id = ? AND user_id = ?')->execute([$gid, $uid]);
        json_success(__('groups.left'));

    case 'delete':
        require_csrf();
        $gid = int_input('group_id');
        if (group_member_role($gid, $uid) !== 'owner') {
            json_error(__('groups.only_owner_delete'), 403);
        }
        db()->prepare('UPDATE group_chats SET is_active = 0 WHERE id = ?')->execute([$gid]);
        json_success(__('groups.deleted'));

    case 'history':
        $gid = int_input('group_id');
        $afterId = int_input('after_id');
        $limit = min(100, max(1, int_input('limit', 50)));
        if (!group_member_role($gid, $uid)) {
            json_error(__('groups.not_member'), 403);
        }
        $sql = 'SELECT gm.*, u.username AS sender_username, u.avatar AS sender_avatar,
                       r.id AS reply_id, r.body AS reply_body, r.message_type AS reply_type,
                       r.is_deleted AS reply_deleted, r.sender_id AS reply_sender_id
                FROM group_messages gm
                JOIN users u ON u.id = gm.sender_id
                LEFT JOIN group_messages r ON r.id = gm.reply_to_id
                WHERE gm.group_id = ?';
        $params = [$gid];
        if ($afterId > 0) {
            $sql .= ' AND gm.id > ? ORDER BY gm.id ASC LIMIT ' . (int) $limit;
            $params[] = $afterId;
        } else {
            $sql .= ' ORDER BY gm.id DESC LIMIT ' . (int) $limit;
        }
        $stmt = db()->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();
        if ($afterId <= 0) {
            $rows = array_reverse($rows);
        }
        $ids = array_map(static fn($r) => (int) $r['id'], $rows);
        $reactions = [];
        if ($ids) {
            $ph = implode(',', array_fill(0, count($ids), '?'));
            $agg = db()->prepare(
                "SELECT message_id, reaction, COUNT(*) AS cnt, GROUP_CONCAT(user_id) AS user_ids
                 FROM group_message_reactions WHERE message_id IN ($ph) GROUP BY message_id, reaction"
            );
            $agg->execute($ids);
            while ($row = $agg->fetch()) {
                $reactions[(int) $row['message_id']][] = [
                    'reaction' => $row['reaction'],
                    'count' => (int) $row['cnt'],
                    'user_ids' => array_map('intval', explode(',', (string) $row['user_ids'])),
                ];
            }
        }
        $messages = [];
        foreach ($rows as $row) {
            $msg = format_group_message($row, $uid);
            $msg['reactions'] = $reactions[(int) $row['id']] ?? [];
            $messages[] = $msg;
            // mark read
            db()->prepare('INSERT IGNORE INTO group_message_reads (message_id, user_id) VALUES (?, ?)')
                ->execute([(int) $row['id'], $uid]);
        }
        json_success(__('common.ok'), ['messages' => $messages]);

    case 'send':
        require_csrf();
        require_rate_limit('message', RATE_MESSAGE, $uid);
        $gid = int_input('group_id');
        if (!group_member_role($gid, $uid)) {
            json_error(__('groups.not_member'), 403);
        }
        $type = str_input('message_type', 'text');
        if (!in_array($type, ['text', 'image', 'file', 'voice'], true)) {
            json_error(__('msg.invalid_type'));
        }
        $body = str_input('body');
        $replyTo = int_input('reply_to_id');
        $filePath = $fileName = $mime = null;
        $fileSize = null;
        $voiceDuration = null;

        if ($type === 'text') {
            if ($body === '' || mb_strlen($body) > 5000) {
                json_error(__('msg.text_required'));
            }
        } else {
            if (!empty($_FILES['file'])) {
                $subdir = match ($type) { 'image' => 'images', 'voice' => 'voices', default => 'files' };
                $ext = match ($type) { 'image' => ALLOWED_IMAGE_EXT, 'voice' => ALLOWED_VOICE_EXT, default => ALLOWED_FILE_EXT };
                $mimes = match ($type) { 'image' => ALLOWED_IMAGE_MIME, 'voice' => ALLOWED_VOICE_MIME, default => ALLOWED_FILE_MIME };
                $max = match ($type) { 'image' => MAX_IMAGE_SIZE, 'voice' => MAX_VOICE_SIZE, default => MAX_FILE_SIZE };
                $up = store_uploaded_file($_FILES['file'], $subdir, $ext, $mimes, $max);
                if (!$up['ok']) {
                    json_error($up['error']);
                }
                $filePath = $up['path'];
                $fileName = $up['file_name'];
                $mime = $up['mime_type'];
                $fileSize = $up['file_size'];
            } else {
                $filePath = str_input('file_path');
                $fileName = str_input('file_name');
                $mime = str_input('mime_type');
                $fileSize = int_input('file_size');
                if (!$filePath || !is_safe_upload_path($filePath) || !absolute_upload_path($filePath)) {
                    json_error(__('msg.invalid_attachment'));
                }
            }
            if ($type === 'voice') {
                $vd = input('voice_duration');
                $voiceDuration = is_numeric($vd) ? round((float) $vd, 2) : null;
            }
            if ($body === '') {
                $body = null;
            }
        }

        if ($replyTo > 0) {
            $chk = db()->prepare('SELECT id FROM group_messages WHERE id = ? AND group_id = ?');
            $chk->execute([$replyTo, $gid]);
            if (!$chk->fetch()) {
                json_error(__('msg.reply_missing'));
            }
        } else {
            $replyTo = null;
        }

        db()->prepare(
            'INSERT INTO group_messages
             (group_id, sender_id, message_type, body, file_path, file_name, mime_type, file_size, voice_duration, reply_to_id)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([$gid, $uid, $type, $body, $filePath, $fileName, $mime, $fileSize, $voiceDuration, $replyTo]);
        $msgId = (int) db()->lastInsertId();
        db()->prepare('UPDATE group_chats SET updated_at = NOW() WHERE id = ?')->execute([$gid]);

        $members = db()->prepare('SELECT user_id FROM group_members WHERE group_id = ? AND user_id <> ?');
        $members->execute([$gid, $uid]);
        $gname = db()->prepare('SELECT name FROM group_chats WHERE id = ?');
        $gname->execute([$gid]);
        $name = (string) $gname->fetchColumn();
        while ($m = $members->fetch()) {
            create_notification(
                (int) $m['user_id'],
                'group_message',
                ['name' => $user['username'], 'group' => $name],
                'group_chat.php?id=' . $gid,
                $msgId
            );
        }

        $stmt = db()->prepare(
            'SELECT gm.*, u.username AS sender_username, u.avatar AS sender_avatar,
                    r.id AS reply_id, r.body AS reply_body, r.message_type AS reply_type,
                    r.is_deleted AS reply_deleted, r.sender_id AS reply_sender_id
             FROM group_messages gm
             JOIN users u ON u.id = gm.sender_id
             LEFT JOIN group_messages r ON r.id = gm.reply_to_id
             WHERE gm.id = ?'
        );
        $stmt->execute([$msgId]);
        json_success(__('msg.sent'), ['message' => format_group_message($stmt->fetch(), $uid)]);

    case 'delete_message':
        require_csrf();
        $msgId = int_input('message_id');
        $stmt = db()->prepare('SELECT * FROM group_messages WHERE id = ?');
        $stmt->execute([$msgId]);
        $msg = $stmt->fetch();
        if (!$msg) {
            json_error(__('common.not_found'));
        }
        $role = group_member_role((int) $msg['group_id'], $uid);
        if ((int) $msg['sender_id'] !== $uid && !in_array($role, ['owner', 'admin'], true)) {
            json_error(__('common.not_allowed'), 403);
        }
        db()->prepare('UPDATE group_messages SET is_deleted = 1, body = NULL WHERE id = ?')->execute([$msgId]);
        json_success(__('common.deleted'));

    case 'typing':
        require_csrf();
        $gid = int_input('group_id');
        if (!group_member_role($gid, $uid)) {
            json_error(__('common.not_allowed'), 403);
        }
        $expires = (new DateTimeImmutable('now'))->modify('+' . TYPING_TTL_SECONDS . ' seconds')->format('Y-m-d H:i:s');
        db()->prepare(
            'INSERT INTO typing_indicators (user_id, conversation_type, target_id, expires_at)
             VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE expires_at = VALUES(expires_at)'
        )->execute([$uid, 'group', $gid, $expires]);
        json_success(__('common.ok'));

    case 'typing_status':
        $gid = int_input('group_id');
        if (!group_member_role($gid, $uid)) {
            json_error(__('common.not_allowed'), 403);
        }
        db()->exec('DELETE FROM typing_indicators WHERE expires_at < NOW()');
        $stmt = db()->prepare(
            'SELECT u.username FROM typing_indicators t
             JOIN users u ON u.id = t.user_id
             WHERE t.conversation_type = ? AND t.target_id = ? AND t.user_id <> ? AND t.expires_at >= NOW()'
        );
        $stmt->execute(['group', $gid, $uid]);
        $names = array_column($stmt->fetchAll(), 'username');
        json_success(__('common.ok'), ['typing' => $names]);

    default:
        json_error(__('common.unknown_action'), 404);
}
