<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/bootstrap.php';
$currentUser = require_login();
$pageTitle = __('profile.title');
$activeNav = 'profile';
$extraJs = [asset('js/profile.js')];
require __DIR__ . '/includes/layout_start.php';
?>
<div class="container wc-page py-3" style="max-width:720px;">
    <h1 class="wc-page-title"><?= e(__('profile.title')) ?></h1>
    <div class="wc-panel p-4">
        <div class="text-center mb-4">
            <img id="profileAvatar" class="wc-avatar-xl mb-2" src="<?= e(avatar_url($currentUser['avatar'] ?? null, $currentUser['username'])) ?>" alt="">
            <div>
                <label class="btn btn-sm btn-outline-secondary">
                    <?= e(__('profile.change_avatar')) ?> <input type="file" id="avatarInput" accept="image/*" hidden>
                </label>
            </div>
        </div>
        <form id="profileForm">
            <div class="mb-3">
                <label class="form-label"><?= e(__('profile.username')) ?></label>
                <input type="text" class="form-control" name="username" id="profileUsername" value="<?= e($currentUser['username']) ?>" required>
            </div>
            <div class="mb-3">
                <label class="form-label"><?= e(__('profile.email')) ?></label>
                <input type="email" class="form-control" value="<?= e($currentUser['email']) ?>" disabled>
            </div>
            <div class="mb-3">
                <label class="form-label"><?= e(__('profile.status')) ?></label>
                <input type="text" class="form-control" name="status_message" id="profileStatus" maxlength="255" value="<?= e($currentUser['status_message'] ?? '') ?>">
            </div>
            <button type="submit" class="btn btn-wc"><?= e(__('profile.save')) ?></button>
        </form>
        <hr>
        <h2 class="h6"><?= e(__('profile.change_password')) ?></h2>
        <form id="passwordForm">
            <div class="mb-2">
                <input type="password" class="form-control" name="current_password" placeholder="<?= e(__('profile.current_password')) ?>" required>
            </div>
            <div class="mb-2">
                <input type="password" class="form-control" name="new_password" placeholder="<?= e(__('profile.new_password')) ?>" required minlength="8">
            </div>
            <div class="mb-3">
                <input type="password" class="form-control" name="confirm_password" placeholder="<?= e(__('profile.confirm_new')) ?>" required>
            </div>
            <button type="submit" class="btn btn-outline-secondary"><?= e(__('profile.update_password')) ?></button>
        </form>
        <hr>
        <p class="small text-muted mb-0"><?= e(__('profile.member_since', ['date' => date('M j, Y', strtotime($currentUser['created_at']))])) ?></p>
    </div>
</div>
<?php require __DIR__ . '/includes/layout_end.php'; ?>
