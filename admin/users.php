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
        $userId = (int) ($_POST['user_id'] ?? 0);
        if ($action === 'disable' && $userId > 0) {
            db()->prepare('UPDATE users SET is_active = 0 WHERE id = ?')->execute([$userId]);
            $message = __('admin.user_disabled');
        } elseif ($action === 'enable' && $userId > 0) {
            db()->prepare('UPDATE users SET is_active = 1 WHERE id = ?')->execute([$userId]);
            $message = __('admin.user_enabled');
        } elseif ($action === 'delete' && $userId > 0) {
            db()->prepare('DELETE FROM users WHERE id = ?')->execute([$userId]);
            $message = __('admin.user_deleted');
        }
    }
}

$q = trim((string) ($_GET['q'] ?? ''));
if ($q !== '') {
    $stmt = db()->prepare('SELECT * FROM users WHERE username LIKE ? OR email LIKE ? ORDER BY id DESC LIMIT 100');
    $like = '%' . $q . '%';
    $stmt->execute([$like, $like]);
} else {
    $stmt = db()->query('SELECT * FROM users ORDER BY id DESC LIMIT 100');
}
$users = $stmt->fetchAll();

$pageTitle = __('admin.users');
$adminNav = 'users';
require __DIR__ . '/layout_start.php';
?>
<?php if ($message): ?><div class="alert alert-success"><?= e($message) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>

<div class="wc-admin-card mb-3">
    <div class="wc-admin-card-body">
        <form class="row g-2 align-items-center" method="get">
            <div class="col flex-grow-1">
                <input type="search" name="q" class="form-control" placeholder="<?= e(__('admin.search_users')) ?>" value="<?= e($q) ?>">
            </div>
            <div class="col-auto">
                <button class="btn btn-wc"><i class="fa-solid fa-magnifying-glass me-1"></i><?= e(__('common.search')) ?></button>
            </div>
        </form>
    </div>
</div>

<div class="wc-admin-card">
    <div class="table-responsive">
        <table class="table wc-admin-table mb-0 align-middle">
            <thead>
            <tr>
                <th><?= e(__('admin.id')) ?></th>
                <th><?= e(__('profile.username')) ?></th>
                <th><?= e(__('profile.email')) ?></th>
                <th><?= e(__('admin.status')) ?></th>
                <th><?= e(__('admin.created')) ?></th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($users as $u): ?>
                <tr>
                    <td class="text-muted"><?= (int) $u['id'] ?></td>
                    <td class="fw-semibold"><?= e($u['username']) ?></td>
                    <td><?= e($u['email']) ?></td>
                    <td>
                        <?php if ((int) $u['is_active']): ?>
                            <span class="wc-badge-soft success"><?= e(__('admin.active')) ?></span>
                        <?php else: ?>
                            <span class="wc-badge-soft muted"><?= e(__('admin.disabled')) ?></span>
                        <?php endif; ?>
                    </td>
                    <td class="small text-muted"><?= e((string) $u['created_at']) ?></td>
                    <td class="text-end text-nowrap">
                        <form method="post" class="d-inline"><?= csrf_field() ?>
                            <input type="hidden" name="user_id" value="<?= (int) $u['id'] ?>">
                            <?php if ((int) $u['is_active']): ?>
                                <button name="action" value="disable" class="btn btn-sm btn-outline-warning"><?= e(__('admin.disable')) ?></button>
                            <?php else: ?>
                                <button name="action" value="enable" class="btn btn-sm btn-outline-success"><?= e(__('admin.enable')) ?></button>
                            <?php endif; ?>
                            <button name="action" value="delete" class="btn btn-sm btn-outline-danger"
                                    onclick="return confirm('<?= e(__('admin.delete_user_confirm')) ?>')"><?= e(__('common.delete')) ?></button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$users): ?>
                <tr><td colspan="6" class="text-center text-muted py-4"><?= e(__('common.none')) ?></td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php require __DIR__ . '/layout_end.php'; ?>
