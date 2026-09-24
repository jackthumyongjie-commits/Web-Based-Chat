<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/bootstrap.php';
require_guest();

$error = '';
$success = '';

if (request_method() === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? null)) {
        $error = __('auth.invalid_token');
    } else {
        $username = trim((string) ($_POST['username'] ?? ''));
        $email = trim((string) ($_POST['email'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        $confirm = (string) ($_POST['confirm_password'] ?? '');

        $uErr = validate_username($username);
        $pErr = validate_password($password);

        if ($uErr) {
            $error = $uErr;
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = __('auth.valid_email');
        } elseif ($pErr) {
            $error = $pErr;
        } elseif ($password !== $confirm) {
            $error = __('auth.password_mismatch');
        } else {
            try {
                $check = db()->prepare('SELECT id FROM users WHERE username = ? OR email = ? LIMIT 1');
                $check->execute([$username, $email]);
                $existing = $check->fetch();
                if ($existing) {
                    $error = __('auth.username_email_taken');
                } else {
                    $hash = password_hash($password, PASSWORD_DEFAULT);
                    $ins = db()->prepare(
                        'INSERT INTO users (username, email, password_hash, presence, last_seen_at, last_activity_at)
                         VALUES (?, ?, ?, ?, NULL, NULL)'
                    );
                    $ins->execute([$username, $email, $hash, 'offline']);
                    $success = __('auth.account_created');
                    $_POST = [];
                }
            } catch (Throwable $e) {
                app_log('auth', 'Register error: ' . $e->getMessage());
                $error = __('auth.unable_create');
            }
        }
    }
}

$pageTitle = __('auth.create_account');
$bodyClass = 'wc-auth-page';
$currentUser = null;
require __DIR__ . '/includes/layout_start.php';
?>
<div class="wc-auth-screen">
    <div class="wc-auth-lang">
        <?php require __DIR__ . '/includes/lang_switcher.php'; ?>
    </div>
    <div class="wc-auth-stage">
        <div class="wc-auth-hero">
            <div class="wc-logo-mark"><i class="fa-solid fa-comments"></i></div>
            <h1 class="wc-brand-wordmark"><span class="wc-brand-web">Web</span><span class="wc-brand-chat">Chat</span></h1>
            <p><?= e(__('auth.create_hint')) ?></p>
            <div class="wc-auth-features">
                <div class="wc-auth-feature"><i class="fa-regular fa-comment-dots"></i><?= e(__('auth.feat_chat')) ?></div>
                <div class="wc-auth-feature"><i class="fa-solid fa-phone"></i><?= e(__('auth.feat_call')) ?></div>
                <div class="wc-auth-feature"><i class="fa-solid fa-shield-halved"></i><?= e(__('auth.feat_secure')) ?></div>
            </div>
        </div>
        <div class="wc-auth-panel">
            <h2 class="wc-auth-panel-title"><?= e(__('auth.create_account')) ?></h2>
            <?php if ($error): ?>
                <div class="alert alert-danger py-2"><?= e($error) ?></div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div class="alert alert-success py-2">
                    <?= e($success) ?>
                    <a href="<?= e(url('login.php')) ?>"><?= e(__('auth.sign_in')) ?></a>
                </div>
            <?php endif; ?>
            <form method="post" autocomplete="off" novalidate>
                <?= csrf_field() ?>
                <div class="mb-3">
                    <label class="form-label" for="username"><?= e(__('auth.username')) ?></label>
                    <input type="text" class="form-control" id="username" name="username" required
                           value="<?= e($_POST['username'] ?? '') ?>" autocomplete="off" autofocus>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="email"><?= e(__('auth.email')) ?></label>
                    <input type="email" class="form-control" id="email" name="email" required
                           value="<?= e($_POST['email'] ?? '') ?>" autocomplete="off">
                </div>
                <div class="mb-3">
                    <label class="form-label" for="password"><?= e(__('auth.password')) ?></label>
                    <input type="password" class="form-control" id="password" name="password" required
                           minlength="<?= (int) PASSWORD_MIN_LENGTH ?>" autocomplete="new-password" value="">
                    <div class="form-text"><?= e(__('auth.password_hint', ['min' => (string) PASSWORD_MIN_LENGTH])) ?></div>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="confirm_password"><?= e(__('auth.confirm_password')) ?></label>
                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" required
                           autocomplete="new-password" value="">
                </div>
                <button type="submit" class="btn btn-wc w-100"><?= e(__('auth.create_account')) ?></button>
            </form>
            <p class="wc-auth-footer mb-0">
                <?= e(__('auth.have_account')) ?>
                <a href="<?= e(url('login.php')) ?>"><?= e(__('auth.sign_in')) ?></a>
            </p>
        </div>
    </div>
</div>
<?php require __DIR__ . '/includes/layout_end.php'; ?>
