<?php
declare(strict_types=1);
/** @var string $adminNav */
$adminNav = $adminNav ?? '';
$navItems = [
    ['id' => 'dashboard', 'href' => 'index.php', 'icon' => 'fa-gauge-high', 'label' => __('admin.dashboard')],
    ['id' => 'users', 'href' => 'users.php', 'icon' => 'fa-users', 'label' => __('admin.users')],
    ['id' => 'forums', 'href' => 'forums.php', 'icon' => 'fa-earth-asia', 'label' => __('admin.forums')],
    ['id' => 'reports', 'href' => 'reports.php', 'icon' => 'fa-flag', 'label' => __('admin.reports')],
];
?>
<aside class="wc-admin-sidebar">
    <div class="wc-admin-brand">
        <span class="wc-brand-mark"><i class="fa-solid fa-comments"></i></span>
        <div>
            <strong class="wc-brand-wordmark"><span class="wc-brand-web">Web</span><span class="wc-brand-chat">Chat</span></strong>
            <small><?= e(__('admin.panel')) ?></small>
        </div>
    </div>
    <nav class="wc-admin-nav">
        <?php foreach ($navItems as $item): ?>
            <a class="wc-admin-nav-link <?= $adminNav === $item['id'] ? 'active' : '' ?>"
               href="<?= e($item['href']) ?>">
                <i class="fa-solid <?= e($item['icon']) ?>"></i>
                <span><?= e($item['label']) ?></span>
            </a>
        <?php endforeach; ?>
    </nav>
    <div class="wc-admin-sidebar-foot">
        <a class="wc-admin-nav-link danger" href="logout.php">
            <i class="fa-solid fa-right-from-bracket"></i>
            <span><?= e(__('admin.logout')) ?></span>
        </a>
    </div>
</aside>
