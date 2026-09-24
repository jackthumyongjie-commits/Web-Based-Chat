<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/bootstrap.php';
start_admin_session();
$admin = require_admin();

$message = '';
$error = '';

if (request_method() === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? null)) {
        $error = __('admin.invalid_token');
    } else {
        $action = (string) ($_POST['action'] ?? '');
        if ($action === 'create') {
            $name = trim((string) ($_POST['name'] ?? ''));
            $desc = trim((string) ($_POST['description'] ?? ''));
            $slug = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $name) ?? '');
            $slug = trim($slug, '-');
            if ($name === '' || $slug === '') {
                $error = __('admin.name_required');
            } else {
                try {
                    db()->prepare('INSERT INTO forums (name, description, slug, sort_order, is_active) VALUES (?, ?, ?, ?, 1)')
                        ->execute([$name, $desc, $slug, (int) ($_POST['sort_order'] ?? 0)]);
                    $message = __('admin.forum_created');
                } catch (Throwable $e) {
                    $error = __('admin.slug_exists');
                }
            }
        } elseif ($action === 'toggle') {
            $id = (int) ($_POST['forum_id'] ?? 0);
            db()->prepare('UPDATE forums SET is_active = IF(is_active=1,0,1) WHERE id = ?')->execute([$id]);
            $message = __('admin.forum_updated');
        } elseif ($action === 'hide_post') {
            $id = (int) ($_POST['post_id'] ?? 0);
            db()->prepare('UPDATE forum_posts SET is_hidden = 1 WHERE id = ?')->execute([$id]);
            $message = __('admin.post_hidden');
        } elseif ($action === 'lock_post') {
            $id = (int) ($_POST['post_id'] ?? 0);
            db()->prepare('UPDATE forum_posts SET is_locked = IF(is_locked=1,0,1) WHERE id = ?')->execute([$id]);
            $message = __('admin.lock_toggled');
        } elseif ($action === 'delete_forum') {
            $id = (int) ($_POST['forum_id'] ?? 0);
            db()->prepare('DELETE FROM forums WHERE id = ?')->execute([$id]);
            $message = __('admin.forum_deleted');
        }
    }
}

$forums = db()->query('SELECT * FROM forums ORDER BY sort_order, name')->fetchAll();
$posts = db()->query(
    'SELECT p.id, p.title, p.is_hidden, p.is_locked, p.created_at, u.username, f.name AS forum_name
     FROM forum_posts p
     JOIN users u ON u.id = p.user_id
     JOIN forums f ON f.id = p.forum_id
     ORDER BY p.id DESC LIMIT 50'
)->fetchAll();

$pageTitle = __('admin.forum_mgmt');
$adminNav = 'forums';
require __DIR__ . '/layout_start.php';
?>
<?php if ($message): ?><div class="alert alert-success"><?= e($message) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>

<div class="wc-admin-card mb-3">
    <div class="wc-admin-card-head"><h2><?= e(__('admin.create_category')) ?></h2></div>
    <div class="wc-admin-card-body">
        <form method="post" class="row g-2">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="create">
            <div class="col-md-4"><input class="form-control" name="name" placeholder="<?= e(__('groups.name')) ?>" required></div>
            <div class="col-md-4"><input class="form-control" name="description" placeholder="<?= e(__('groups.description')) ?>"></div>
            <div class="col-md-2"><input class="form-control" name="sort_order" type="number" value="0"></div>
            <div class="col-md-2"><button class="btn btn-wc w-100"><?= e(__('admin.add')) ?></button></div>
        </form>
    </div>
</div>

<div class="wc-admin-card mb-3">
    <div class="table-responsive">
        <table class="table wc-admin-table mb-0 align-middle">
            <thead>
            <tr>
                <th><?= e(__('groups.name')) ?></th>
                <th><?= e(__('admin.slug')) ?></th>
                <th><?= e(__('admin.column_active')) ?></th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($forums as $f): ?>
                <tr>
                    <td>
                        <div class="fw-semibold"><?= e($f['name']) ?></div>
                        <div class="small text-muted"><?= e((string) $f['description']) ?></div>
                    </td>
                    <td><code><?= e($f['slug']) ?></code></td>
                    <td>
                        <?php if ((int) $f['is_active']): ?>
                            <span class="wc-badge-soft success"><?= e(__('common.yes')) ?></span>
                        <?php else: ?>
                            <span class="wc-badge-soft muted"><?= e(__('common.no')) ?></span>
                        <?php endif; ?>
                    </td>
                    <td class="text-end text-nowrap">
                        <form method="post" class="d-inline"><?= csrf_field() ?>
                            <input type="hidden" name="forum_id" value="<?= (int) $f['id'] ?>">
                            <button name="action" value="toggle" class="btn btn-sm btn-outline-secondary"><?= e(__('admin.toggle')) ?></button>
                            <button name="action" value="delete_forum" class="btn btn-sm btn-outline-danger"
                                    onclick="return confirm('<?= e(__('admin.delete_cat_confirm')) ?>')"><?= e(__('common.delete')) ?></button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="wc-admin-card">
    <div class="wc-admin-card-head"><h2><?= e(__('admin.recent_posts')) ?></h2></div>
    <div class="table-responsive">
        <table class="table wc-admin-table mb-0 align-middle">
            <thead>
            <tr>
                <th><?= e(__('forum.post_title')) ?></th>
                <th><?= e(__('admin.forum_col')) ?></th>
                <th><?= e(__('admin.author')) ?></th>
                <th><?= e(__('admin.flags')) ?></th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($posts as $p): ?>
                <tr>
                    <td><?= e($p['title']) ?></td>
                    <td><?= e($p['forum_name']) ?></td>
                    <td><?= e($p['username']) ?></td>
                    <td>
                        <?= (int) $p['is_hidden'] ? '<span class="wc-badge-soft muted">' . e(__('admin.hidden')) . '</span> ' : '' ?>
                        <?= (int) $p['is_locked'] ? '<span class="wc-badge-soft warn">' . e(__('admin.locked')) . '</span>' : '' ?>
                    </td>
                    <td class="text-end text-nowrap">
                        <form method="post" class="d-inline"><?= csrf_field() ?>
                            <input type="hidden" name="post_id" value="<?= (int) $p['id'] ?>">
                            <button name="action" value="lock_post" class="btn btn-sm btn-outline-secondary"><?= e(__('admin.lock_unlock')) ?></button>
                            <button name="action" value="hide_post" class="btn btn-sm btn-outline-danger"><?= e(__('admin.hide')) ?></button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$posts): ?>
                <tr><td colspan="5" class="text-center text-muted py-4"><?= e(__('common.none')) ?></td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php require __DIR__ . '/layout_end.php'; ?>
