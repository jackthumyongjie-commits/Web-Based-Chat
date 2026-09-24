<?php
/**
 * Layout start — shared chrome
 * @var string $pageTitle
 * @var string $activeNav
 * @var array|null $currentUser
 */

declare(strict_types=1);

$currentUser = $currentUser ?? current_user();
$pageTitle = $pageTitle ?? APP_NAME;
$activeNav = $activeNav ?? '';
$bodyClass = $bodyClass ?? '';
$extraCss = $extraCss ?? [];
$htmlLang = current_lang() === 'zh' ? 'zh-CN' : 'en';
$curLang = current_lang();
?>
<!DOCTYPE html>
<html lang="<?= e($htmlLang) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title><?= e($pageTitle) ?> — <?= e(APP_NAME) ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
    <?php foreach ($extraCss as $css): ?>
        <link rel="stylesheet" href="<?= e($css) ?>">
    <?php endforeach; ?>
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <meta name="app-url" content="<?= e(APP_URL) ?>">
</head>
<body class="wc-body <?= e($bodyClass) ?>" data-user-id="<?= e((string) ($currentUser['id'] ?? '')) ?>" data-lang="<?= e($curLang) ?>">
<?php if ($currentUser): ?>
<nav class="navbar navbar-expand-lg wc-navbar sticky-top">
    <div class="container-fluid px-3">
        <a class="navbar-brand wc-brand" href="<?= e(url('chat.php')) ?>">
            <span class="wc-brand-mark"><i class="fa-solid fa-comments"></i></span>
            <span class="wc-brand-text"><span class="wc-brand-web">Web</span><span class="wc-brand-chat">Chat</span></span>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#wcNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="wcNav">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <li class="nav-item">
                    <a class="nav-link <?= $activeNav === 'chat' ? 'active' : '' ?>" href="<?= e(url('chat.php')) ?>">
                        <i class="fa-regular fa-comment-dots"></i> <?= e(__('nav.chat')) ?>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $activeNav === 'friends' ? 'active' : '' ?>" href="<?= e(url('friends.php')) ?>">
                        <i class="fa-solid fa-user-group"></i> <?= e(__('nav.friends')) ?>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $activeNav === 'groups' ? 'active' : '' ?>" href="<?= e(url('groups.php')) ?>">
                        <i class="fa-solid fa-users"></i> <?= e(__('nav.groups')) ?>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $activeNav === 'forums' ? 'active' : '' ?>" href="<?= e(url('forums.php')) ?>">
                        <i class="fa-solid fa-earth-asia"></i> <?= e(__('nav.community')) ?>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $activeNav === 'notifications' ? 'active' : '' ?>" href="<?= e(url('notifications.php')) ?>">
                        <i class="fa-regular fa-bell"></i> <?= e(__('nav.alerts')) ?>
                        <span id="navNotifBadge" class="badge rounded-pill wc-badge d-none">0</span>
                    </a>
                </li>
            </ul>
            <div class="d-flex align-items-center gap-2">
                <?php require __DIR__ . '/lang_switcher.php'; ?>
                <a class="wc-nav-user text-decoration-none" href="<?= e(url('profile.php')) ?>" title="<?= e(__('nav.profile')) ?>">
                    <img src="<?= e(avatar_url($currentUser['avatar'] ?? null, $currentUser['username'])) ?>"
                         alt="" class="wc-avatar-sm">
                    <span><?= e($currentUser['username']) ?></span>
                </a>
                <a class="btn btn-sm btn-outline-light" href="<?= e(url('logout.php')) ?>" title="<?= e(__('nav.logout')) ?>">
                    <i class="fa-solid fa-right-from-bracket"></i>
                </a>
            </div>
        </div>
    </div>
</nav>
<?php else: ?>
<?php /* Auth pages render their own chrome; keep a minimal bar only for non-auth guests */ ?>
<?php if (strpos($bodyClass, 'wc-auth-page') === false): ?>
<nav class="navbar navbar-expand-lg wc-navbar sticky-top">
    <div class="container-fluid px-3">
        <a class="navbar-brand wc-brand" href="<?= e(url('login.php')) ?>">
            <span class="wc-brand-mark"><i class="fa-solid fa-comments"></i></span>
            <span class="wc-brand-text"><span class="wc-brand-web">Web</span><span class="wc-brand-chat">Chat</span></span>
        </a>
        <div class="ms-auto">
            <?php require __DIR__ . '/lang_switcher.php'; ?>
        </div>
    </div>
</nav>
<?php endif; ?>
<?php endif; ?>
<main class="wc-main">
