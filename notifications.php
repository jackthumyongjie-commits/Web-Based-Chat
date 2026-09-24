<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/bootstrap.php';
$currentUser = require_login();
$pageTitle = __('notif.title');
$activeNav = 'notifications';
$extraJs = [asset('js/notifications.js')];
require __DIR__ . '/includes/layout_start.php';
?>
<div class="container wc-page py-3" style="max-width:800px;">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="wc-page-title mb-0"><?= e(__('notif.title')) ?></h1>
        <button class="btn btn-sm btn-outline-secondary" id="btnMarkAllRead"><?= e(__('notif.mark_all')) ?></button>
    </div>
    <div class="wc-panel" id="notifList"><div class="wc-empty"><?= e(__('common.loading')) ?></div></div>
</div>
<?php require __DIR__ . '/includes/layout_end.php'; ?>
