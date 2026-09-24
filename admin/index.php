<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/bootstrap.php';
start_admin_session();
$admin = require_admin();

$stats = [
    ['key' => 'users', 'label' => __('admin.total_users'), 'icon' => 'fa-users', 'tone' => 'green',
     'num' => (int) db()->query('SELECT COUNT(*) FROM users')->fetchColumn()],
    ['key' => 'active', 'label' => __('admin.active_users'), 'icon' => 'fa-user-check', 'tone' => 'green',
     'num' => (int) db()->query('SELECT COUNT(*) FROM users WHERE is_active = 1')->fetchColumn()],
    ['key' => 'disabled', 'label' => __('admin.disabled_users'), 'icon' => 'fa-user-slash', 'tone' => 'muted',
     'num' => (int) db()->query('SELECT COUNT(*) FROM users WHERE is_active = 0')->fetchColumn()],
    ['key' => 'messages', 'label' => __('admin.total_messages'), 'icon' => 'fa-comment-dots', 'tone' => 'blue',
     'num' => (int) db()->query('SELECT COUNT(*) FROM messages')->fetchColumn()],
    ['key' => 'groups', 'label' => __('admin.total_groups'), 'icon' => 'fa-users-rectangle', 'tone' => 'blue',
     'num' => (int) db()->query('SELECT COUNT(*) FROM group_chats WHERE is_active = 1')->fetchColumn()],
    ['key' => 'posts', 'label' => __('admin.forum_posts'), 'icon' => 'fa-newspaper', 'tone' => 'blue',
     'num' => (int) db()->query('SELECT COUNT(*) FROM forum_posts')->fetchColumn()],
    ['key' => 'reports', 'label' => __('admin.open_reports'), 'icon' => 'fa-flag', 'tone' => 'warn',
     'num' => (int) db()->query("SELECT COUNT(*) FROM reports WHERE status = 'open'")->fetchColumn()],
];

$pageTitle = __('admin.dashboard');
$adminNav = 'dashboard';
require __DIR__ . '/layout_start.php';
?>
<?php if ((int) $admin['must_change_password'] === 1): ?>
    <div class="alert alert-warning d-flex align-items-center gap-2">
        <i class="fa-solid fa-triangle-exclamation"></i>
        <span><?= e(__('admin.change_pass_warn')) ?></span>
    </div>
<?php endif; ?>

<div class="wc-admin-stats">
    <?php foreach ($stats as $card): ?>
        <div class="wc-stat wc-stat-<?= e($card['tone']) ?>">
            <div class="wc-stat-icon"><i class="fa-solid <?= e($card['icon']) ?>"></i></div>
            <div>
                <div class="wc-stat-label"><?= e($card['label']) ?></div>
                <div class="wc-stat-num"><?= (int) $card['num'] ?></div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<div class="row g-3 mt-1">
    <div class="col-md-6">
        <div class="wc-admin-card">
            <div class="wc-admin-card-head">
                <h2><?= e(__('admin.quick_links')) ?></h2>
            </div>
            <div class="wc-admin-quick">
                <a href="users.php"><i class="fa-solid fa-users"></i><?= e(__('admin.users')) ?></a>
                <a href="forums.php"><i class="fa-solid fa-earth-asia"></i><?= e(__('admin.forums')) ?></a>
                <a href="reports.php"><i class="fa-solid fa-flag"></i><?= e(__('admin.reports')) ?></a>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="wc-admin-card">
            <div class="wc-admin-card-head">
                <h2><?= e(__('admin.panel')) ?></h2>
            </div>
            <p class="text-muted mb-0 small px-3 pb-3"><?= e(__('admin.welcome_hint')) ?></p>
        </div>
    </div>
</div>
<?php require __DIR__ . '/layout_end.php'; ?>
