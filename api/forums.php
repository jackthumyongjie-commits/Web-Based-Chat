<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/bootstrap.php';

$user = require_login();
$uid = (int) $user['id'];
$action = api_action();

switch ($action) {
    case 'categories':
        $stmt = db()->query(
            'SELECT f.*,
                (SELECT COUNT(*) FROM forum_posts p WHERE p.forum_id = f.id AND p.is_hidden = 0) AS post_count
             FROM forums f WHERE f.is_active = 1 ORDER BY f.sort_order ASC, f.name ASC'
        );
        $cats = [];
        while ($row = $stmt->fetch()) {
            $cats[] = [
                'id' => (int) $row['id'],
                'name' => $row['name'],
                'description' => $row['description'],
                'slug' => $row['slug'],
                'post_count' => (int) $row['post_count'],
            ];
        }
        json_success(__('common.ok'), ['forums' => $cats]);

    case 'posts':
        $forumId = int_input('forum_id');
        $sql = 'SELECT p.*, u.username, u.avatar,
                    (SELECT COUNT(*) FROM post_comments c WHERE c.post_id = p.id AND c.is_hidden = 0) AS comment_count,
                    (SELECT COUNT(*) FROM post_likes l WHERE l.post_id = p.id) AS like_count,
                    (SELECT COUNT(*) FROM post_shares s WHERE s.post_id = p.id) AS share_count,
                    EXISTS(SELECT 1 FROM post_likes pl WHERE pl.post_id = p.id AND pl.user_id = ?) AS liked
                FROM forum_posts p
                JOIN users u ON u.id = p.user_id
                WHERE p.is_hidden = 0';
        $params = [$uid];
        if ($forumId > 0) {
            $sql .= ' AND p.forum_id = ?';
            $params[] = $forumId;
        }
        $sql .= ' ORDER BY p.is_pinned DESC, p.created_at DESC LIMIT 50';
        $stmt = db()->prepare($sql);
        $stmt->execute($params);
        $posts = [];
        while ($row = $stmt->fetch()) {
            $posts[] = [
                'id' => (int) $row['id'],
                'forum_id' => (int) $row['forum_id'],
                'title' => $row['title'],
                'content' => $row['content'],
                'is_pinned' => (int) $row['is_pinned'] === 1,
                'is_locked' => (int) $row['is_locked'] === 1,
                'view_count' => (int) $row['view_count'],
                'comment_count' => (int) $row['comment_count'],
                'like_count' => (int) $row['like_count'],
                'share_count' => (int) $row['share_count'],
                'liked' => (int) $row['liked'] === 1,
                'created_at' => $row['created_at'],
                'author' => public_user([
                    'id' => $row['user_id'],
                    'username' => $row['username'],
                    'avatar' => $row['avatar'],
                    'status_message' => '',
                    'presence' => 'offline',
                    'last_seen_at' => null,
                    'last_activity_at' => null,
                    'is_active' => 1,
                ]),
            ];
        }
        json_success(__('common.ok'), ['posts' => $posts]);

    case 'get_post':
        $postId = int_input('post_id');
        $stmt = db()->prepare(
            'SELECT p.*, u.username, u.avatar,
                (SELECT COUNT(*) FROM post_likes l WHERE l.post_id = p.id) AS like_count,
                EXISTS(SELECT 1 FROM post_likes pl WHERE pl.post_id = p.id AND pl.user_id = ?) AS liked
             FROM forum_posts p JOIN users u ON u.id = p.user_id
             WHERE p.id = ? AND p.is_hidden = 0'
        );
        $stmt->execute([$uid, $postId]);
        $row = $stmt->fetch();
        if (!$row) {
            json_error(__('forum.post_not_found'), 404);
        }
        db()->prepare('UPDATE forum_posts SET view_count = view_count + 1 WHERE id = ?')->execute([$postId]);
        $comments = db()->prepare(
            'SELECT c.*, u.username, u.avatar FROM post_comments c
             JOIN users u ON u.id = c.user_id
             WHERE c.post_id = ? AND c.is_hidden = 0 ORDER BY c.created_at ASC'
        );
        $comments->execute([$postId]);
        $clist = [];
        while ($c = $comments->fetch()) {
            $clist[] = [
                'id' => (int) $c['id'],
                'content' => $c['content'],
                'created_at' => $c['created_at'],
                'author' => [
                    'id' => (int) $c['user_id'],
                    'username' => $c['username'],
                    'avatar_url' => avatar_url($c['avatar'], $c['username']),
                ],
            ];
        }
        json_success(__('common.ok'), [
            'post' => [
                'id' => (int) $row['id'],
                'forum_id' => (int) $row['forum_id'],
                'title' => $row['title'],
                'content' => $row['content'],
                'is_locked' => (int) $row['is_locked'] === 1,
                'like_count' => (int) $row['like_count'],
                'liked' => (int) $row['liked'] === 1,
                'created_at' => $row['created_at'],
                'author' => [
                    'id' => (int) $row['user_id'],
                    'username' => $row['username'],
                    'avatar_url' => avatar_url($row['avatar'], $row['username']),
                ],
            ],
            'comments' => $clist,
        ]);

    case 'create_post':
        require_csrf();
        require_rate_limit('forum', RATE_FORUM, $uid);
        $forumId = int_input('forum_id');
        $title = str_input('title');
        $content = str_input('content');
        if ($title === '' || mb_strlen($title) > 200) {
            json_error(__('forum.title_required'));
        }
        if ($content === '' || mb_strlen($content) > 20000) {
            json_error(__('forum.content_required'));
        }
        $f = db()->prepare('SELECT id FROM forums WHERE id = ? AND is_active = 1');
        $f->execute([$forumId]);
        if (!$f->fetch()) {
            json_error(__('forum.not_found'));
        }
        db()->prepare(
            'INSERT INTO forum_posts (forum_id, user_id, title, content) VALUES (?, ?, ?, ?)'
        )->execute([$forumId, $uid, $title, $content]);
        json_success(__('forum.created'), ['post_id' => (int) db()->lastInsertId()]);

    case 'comment':
        require_csrf();
        require_rate_limit('forum', RATE_FORUM, $uid);
        $postId = int_input('post_id');
        $content = str_input('content');
        if ($content === '' || mb_strlen($content) > 5000) {
            json_error(__('forum.comment_required'));
        }
        $p = db()->prepare('SELECT * FROM forum_posts WHERE id = ? AND is_hidden = 0');
        $p->execute([$postId]);
        $post = $p->fetch();
        if (!$post) {
            json_error(__('forum.post_not_found'));
        }
        if ((int) $post['is_locked']) {
            json_error(__('forum.locked'));
        }
        db()->prepare('INSERT INTO post_comments (post_id, user_id, content) VALUES (?, ?, ?)')
            ->execute([$postId, $uid, $content]);
        if ((int) $post['user_id'] !== $uid) {
            create_notification(
                (int) $post['user_id'],
                'forum_comment',
                ['name' => $user['username']],
                'forums.php?post=' . $postId,
                $postId
            );
        }
        json_success(__('forum.comment_added'));

    case 'like':
        require_csrf();
        $postId = int_input('post_id');
        $chk = db()->prepare('SELECT id FROM post_likes WHERE post_id = ? AND user_id = ?');
        $chk->execute([$postId, $uid]);
        if ($chk->fetch()) {
            db()->prepare('DELETE FROM post_likes WHERE post_id = ? AND user_id = ?')->execute([$postId, $uid]);
            $liked = false;
        } else {
            db()->prepare('INSERT INTO post_likes (post_id, user_id) VALUES (?, ?)')->execute([$postId, $uid]);
            $liked = true;
        }
        $cnt = db()->prepare('SELECT COUNT(*) FROM post_likes WHERE post_id = ?');
        $cnt->execute([$postId]);
        json_success(__('common.ok'), ['liked' => $liked, 'like_count' => (int) $cnt->fetchColumn()]);

    case 'share':
        require_csrf();
        $postId = int_input('post_id');
        $p = db()->prepare('SELECT id FROM forum_posts WHERE id = ? AND is_hidden = 0');
        $p->execute([$postId]);
        if (!$p->fetch()) {
            json_error(__('forum.post_not_found'));
        }
        db()->prepare('INSERT INTO post_shares (post_id, user_id) VALUES (?, ?)')->execute([$postId, $uid]);
        $cnt = db()->prepare('SELECT COUNT(*) FROM post_shares WHERE post_id = ?');
        $cnt->execute([$postId]);
        json_success(__('forum.shared'), [
            'share_count' => (int) $cnt->fetchColumn(),
            'share_url' => url('forums.php?post=' . $postId),
        ]);

    case 'report':
        require_csrf();
        $targetType = str_input('target_type');
        $targetId = int_input('target_id');
        $reason = str_input('reason');
        if (!in_array($targetType, ['user', 'private_message', 'group_message', 'forum_post'], true)) {
            json_error(__('report.invalid_target'));
        }
        if ($reason === '' || mb_strlen($reason) > 500) {
            json_error(__('report.reason'));
        }
        db()->prepare(
            'INSERT INTO reports (reporter_id, target_type, target_id, reason, status) VALUES (?, ?, ?, ?, ?)'
        )->execute([$uid, $targetType, $targetId, $reason, 'open']);
        json_success(__('report.ok'));

    default:
        json_error(__('common.unknown_action'), 404);
}
