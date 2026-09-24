<?php
/**
 * Admin layout start
 * @var string $pageTitle
 * @var string $adminNav
 */
declare(strict_types=1);

$admin = $admin ?? require_admin();
$pageTitle = $pageTitle ?? __('admin.panel');
$adminNav = $adminNav ?? '';
$htmlLang = current_lang() === 'zh' ? 'zh-CN' : 'en';
?>
<!DOCTYPE html>
<html lang="<?= e($htmlLang) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?> — <?= e(__('admin.panel')) ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
</head>
<body class="wc-admin-body">
<div class="wc-admin-shell">
    <?php require __DIR__ . '/admin_nav.php'; ?>
    <div class="wc-admin-main">
        <header class="wc-admin-topbar">
            <div class="wc-admin-topbar-title">
                <h1><?= e($pageTitle) ?></h1>
            </div>
            <div class="wc-admin-topbar-actions">
                <?php require dirname(__DIR__) . '/includes/lang_switcher.php'; ?>
                <div class="wc-admin-who">
                    <i class="fa-solid fa-shield-halved"></i>
                    <span><?= e((string) ($admin['username'] ?? 'admin')) ?></span>
                </div>
            </div>
        </header>
        <div class="wc-admin-content">
