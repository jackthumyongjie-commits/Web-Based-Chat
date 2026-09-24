<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/bootstrap.php';
start_admin_session();

if (current_admin()) {
    redirect('admin/index.php');
}

const DEMO_ADMIN_USER = 'admin';
const DEMO_ADMIN_PASS = 'admin123';
const DEMO_ADMIN_EMAIL = 'admin@webconnect.local';

$error = '';
$success = '';
$prefillUser = DEMO_ADMIN_USER;
$prefillPass = '';

if (request_method() === 'POST') {
    $action = (string) ($_POST['action'] ?? 'login');

    if (!verify_csrf($_POST['csrf_token'] ?? null)) {
        $error = __('admin.invalid_token');
    } elseif ($action === 'seed_demo') {
        if (!(defined('ALLOW_DEMO_ADMIN_SEED') && ALLOW_DEMO_ADMIN_SEED) && !(defined('APP_DEBUG') && APP_DEBUG)) {
            $error = __('admin.seed_disabled');
        } elseif (!rate_limit('admin_seed', [10, 300])) {
            $error = __('admin.too_many');
        } else {
            try {
                $hash = password_hash(DEMO_ADMIN_PASS, PASSWORD_DEFAULT);
                $exists = db()->prepare('SELECT id FROM admins WHERE username = ? OR email = ? LIMIT 1');
                $exists->execute([DEMO_ADMIN_USER, DEMO_ADMIN_EMAIL]);
                $row = $exists->fetch();
                if ($row) {
                    db()->prepare(
                        'UPDATE admins SET username = ?, email = ?, password_hash = ?, display_name = ?,
                         must_change_password = 0, is_active = 1 WHERE id = ?'
                    )->execute([
                        DEMO_ADMIN_USER,
                        DEMO_ADMIN_EMAIL,
                        $hash,
                        'System Administrator',
                        (int) $row['id'],
                    ]);
                    $success = __('admin.seed_updated');
                } else {
                    db()->prepare(
                        'INSERT INTO admins (username, email, password_hash, display_name, must_change_password, is_active)
                         VALUES (?, ?, ?, ?, 0, 1)'
                    )->execute([DEMO_ADMIN_USER, DEMO_ADMIN_EMAIL, $hash, 'System Administrator']);
                    $success = __('admin.seed_created');
                }
                $prefillUser = DEMO_ADMIN_USER;
                $prefillPass = DEMO_ADMIN_PASS;
            } catch (Throwable $e) {
                app_log('admin', 'Seed demo admin failed: ' . $e->getMessage());
                $error = __('admin.seed_failed');
            }
        }
    } elseif (!rate_limit('admin_login', RATE_LOGIN)) {
        $error = __('admin.too_many');
        $prefillUser = trim((string) ($_POST['login'] ?? DEMO_ADMIN_USER));
    } else {
        $login = trim((string) ($_POST['login'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        $prefillUser = $login;
        $stmt = db()->prepare('SELECT * FROM admins WHERE (username = ? OR email = ?) AND is_active = 1 LIMIT 1');
        $stmt->execute([$login, $login]);
        $admin = $stmt->fetch();
        if (!$admin || !password_verify($password, $admin['password_hash'])) {
            $error = __('admin.invalid_credentials');
            if ($login === DEMO_ADMIN_USER && $password === DEMO_ADMIN_PASS) {
                $error = __('admin.need_seed');
            }
        } else {
            login_admin($admin);
            redirect('admin/index.php');
        }
    }
}

$htmlLang = current_lang() === 'zh' ? 'zh-CN' : 'en';
$csrf = csrf_token();
$allowSeed = (defined('ALLOW_DEMO_ADMIN_SEED') && ALLOW_DEMO_ADMIN_SEED)
    || (defined('APP_DEBUG') && APP_DEBUG);
?>
<!DOCTYPE html>
<html lang="<?= e($htmlLang) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e(__('admin.login')) ?> — <?= e(APP_NAME) ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
</head>
<body class="wc-admin-login-page">
<div class="wc-admin-login">
    <div class="wc-admin-login-lang">
        <?php require dirname(__DIR__) . '/includes/lang_switcher.php'; ?>
    </div>
    <div class="wc-admin-login-card">
        <div class="wc-admin-login-brand">
            <div class="wc-logo-mark"><i class="fa-solid fa-shield-halved"></i></div>
            <h1><?= e(__('admin.panel')) ?></h1>
            <p class="wc-brand-wordmark"><span class="wc-brand-web">Web</span><span class="wc-brand-chat">Chat</span></p>
        </div>
        <?php if ($error): ?>
            <div class="alert alert-danger py-2" id="adminAlert"><?= e($error) ?></div>
        <?php elseif ($success): ?>
            <div class="alert alert-success py-2" id="adminAlert"><?= e($success) ?></div>
        <?php else: ?>
            <div class="alert alert-success py-2 d-none" id="adminAlert"></div>
        <?php endif; ?>
        <form method="post" autocomplete="off" novalidate id="adminLoginForm">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="login">
            <div class="mb-3">
                <label class="form-label" for="login"><?= e(__('auth.username_or_email')) ?></label>
                <input type="text" id="login" name="login" class="form-control" required
                       value="<?= e($prefillUser) ?>" autocomplete="username">
            </div>
            <div class="mb-3">
                <label class="form-label" for="password"><?= e(__('auth.password')) ?></label>
                <input type="password" id="password" name="password" class="form-control" required
                       value="<?= e($prefillPass) ?>" autocomplete="current-password"
                       placeholder="admin123">
            </div>
            <button class="btn btn-wc w-100" type="submit">
                <i class="fa-solid fa-right-to-bracket me-1"></i><?= e(__('auth.sign_in')) ?>
            </button>
        </form>

        <?php if ($allowSeed): ?>
        <div class="wc-admin-seed">
            <p class="wc-admin-login-hint mb-2"><?= e(__('admin.demo_hint')) ?></p>
            <button type="button" class="btn btn-outline-wc w-100" id="btnFillDemo"
                    data-user="<?= e(DEMO_ADMIN_USER) ?>"
                    data-pass="<?= e(DEMO_ADMIN_PASS) ?>"
                    data-csrf="<?= e($csrf) ?>"
                    data-ok="<?= e(__('admin.seed_filled')) ?>"
                    data-fail="<?= e(__('admin.seed_failed')) ?>">
                <i class="fa-solid fa-bolt me-1"></i><?= e(__('admin.seed_btn')) ?>
            </button>
            <p class="wc-admin-seed-creds">
                <span><?= e(__('auth.username')) ?>: <code>admin</code></span>
                <span><?= e(__('auth.password')) ?>: <code>admin123</code></span>
            </p>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php if ($allowSeed): ?>
<script>
(function () {
  const btn = document.getElementById('btnFillDemo');
  const user = document.getElementById('login');
  const pass = document.getElementById('password');
  const alertBox = document.getElementById('adminAlert');
  if (!btn || !user || !pass) return;

  function showMsg(text, ok) {
    if (!alertBox) return;
    alertBox.textContent = text;
    alertBox.classList.remove('d-none', 'alert-danger', 'alert-success');
    alertBox.classList.add(ok ? 'alert-success' : 'alert-danger');
  }

  btn.addEventListener('click', async function () {
    const u = btn.dataset.user || 'admin';
    const p = btn.dataset.pass || 'admin123';
    user.value = u;
    pass.value = p;
    pass.focus();

    btn.disabled = true;
    try {
      const body = new URLSearchParams();
      body.set('csrf_token', btn.dataset.csrf || '');
      body.set('action', 'seed_demo');
      const res = await fetch(location.href, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: body.toString(),
        credentials: 'same-origin',
      });
      user.value = u;
      pass.value = p;
      if (res.ok) {
        showMsg(btn.dataset.ok || 'OK', true);
      } else {
        showMsg(btn.dataset.fail || 'Error', false);
      }
    } catch (e) {
      user.value = u;
      pass.value = p;
      showMsg(btn.dataset.fail || 'Error', false);
    } finally {
      btn.disabled = false;
    }
  });
})();
</script>
<?php endif; ?>
</body>
</html>
