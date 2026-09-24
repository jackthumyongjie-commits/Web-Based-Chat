<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/bootstrap.php';

$user = require_login();
$uid = (int) $user['id'];
$action = api_action();

switch ($action) {
    case 'toggle':
        require_csrf();
        $scope = str_input('scope', 'private'); // private | group
        $messageId = int_input('message_id');
        $reaction = str_input('reaction');
        if (!in_array($reaction, ALLOWED_REACTIONS, true)) {
            json_error(__('react.invalid'));
        }

        if ($scope === 'group') {
            $stmt = db()->prepare('SELECT gm.*, g.id AS gid FROM group_messages gm JOIN group_chats g ON g.id = gm.group_id WHERE gm.id = ?');
            $stmt->execute([$messageId]);
            $msg = $stmt->fetch();
            if (!$msg || !group_member_role((int) $msg['group_id'], $uid)) {
                json_error(__('common.not_allowed'), 403);
            }
            $table = 'group_message_reactions';
            $ownerId = (int) $msg['sender_id'];
        } else {
            $stmt = db()->prepare('SELECT * FROM messages WHERE id = ?');
            $stmt->execute([$messageId]);
            $msg = $stmt->fetch();
            if (!$msg) {
                json_error(__('msg.not_found'));
            }
            $sid = (int) $msg['sender_id'];
            $rid = (int) $msg['receiver_id'];
            if ($sid !== $uid && $rid !== $uid) {
                json_error(__('common.not_allowed'), 403);
            }
            $table = 'message_reactions';
            $ownerId = $sid;
        }

        $chk = db()->prepare("SELECT id FROM {$table} WHERE message_id = ? AND user_id = ? AND reaction = ?");
        $chk->execute([$messageId, $uid, $reaction]);
        $existing = $chk->fetch();
        if ($existing) {
            db()->prepare("DELETE FROM {$table} WHERE id = ?")->execute([$existing['id']]);
            $state = 'removed';
        } else {
            // One reaction type per user per message: replace others
            db()->prepare("DELETE FROM {$table} WHERE message_id = ? AND user_id = ?")->execute([$messageId, $uid]);
            db()->prepare("INSERT INTO {$table} (message_id, user_id, reaction) VALUES (?, ?, ?)")
                ->execute([$messageId, $uid, $reaction]);
            $state = 'added';
            if ($ownerId !== $uid) {
                create_notification(
                    $ownerId,
                    'message_reaction',
                    ['name' => $user['username'], 'reaction' => $reaction],
                    $scope === 'group' ? ('group_chat.php?id=' . (int) $msg['group_id']) : ('chat.php?user=' . ($sid === $uid ? $rid : $sid)),
                    $messageId
                );
            }
        }

        $agg = db()->prepare(
            "SELECT reaction, COUNT(*) AS cnt, GROUP_CONCAT(user_id) AS user_ids
             FROM {$table} WHERE message_id = ? GROUP BY reaction"
        );
        $agg->execute([$messageId]);
        $reactions = [];
        while ($row = $agg->fetch()) {
            $reactions[] = [
                'reaction' => $row['reaction'],
                'count' => (int) $row['cnt'],
                'user_ids' => array_map('intval', explode(',', (string) $row['user_ids'])),
            ];
        }
        json_success(__('common.ok'), ['state' => $state, 'reactions' => $reactions]);

    default:
        json_error(__('common.unknown_action'), 404);
}
