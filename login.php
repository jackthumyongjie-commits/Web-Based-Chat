<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/bootstrap.php';
require_guest();

$error = '';
if (request_method() === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? null)) {
        $error = __('auth.invalid_token');
    } elseif (!rate_limit('login', RATE_LOGIN)) {
        $error = __('auth.too_many_login');
    } else {
        $login = trim((string) ($_POST['login'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        if ($login === '' || $password === '') {
            $error = __('auth.enter_credentials');
        } else {
            try {
                $stmt = db()->prepare(
                    'SELECT id, username, email, password_hash, is_active, avatar, status_message, presence, last_seen_at, last_activity_at, created_at
                     FROM users WHERE username = ? OR email = ? LIMIT 1'
                );
                $stmt->execute([$login, $login]);
                $user = $stmt->fetch();
                if (!$user || !password_verify($password, $user['password_hash'])) {
                    $error = __('auth.invalid_credentials');
                } elseif (!(int) $user['is_active']) {
                    $error = __('auth.disabled');
                } else {
                    login_user($user);
                    redirect('chat.php');
                }
            } catch (Throwable $e) {
                app_log('auth', 'Login error: ' . $e->getMessage());
                $error = __('auth.unable_signin');
            }
        }
    }
}

$pageTitle = __('auth.sign_in');
$bodyClass = 'wc-auth-page wc-fish-login-page';
$currentUser = null;
$extraJs = [asset('js/login-fish.js')];
$skipIntro = $error !== '';
require __DIR__ . '/includes/layout_start.php';
?>
<div class="wc-fish-login <?= $skipIntro ? 'phase-idle is-skip' : 'phase-sail' ?>" id="fishLogin"
     data-skip="<?= $skipIntro ? '1' : '0' ?>">
    <div class="wc-auth-lang">
        <?php require __DIR__ . '/includes/lang_switcher.php'; ?>
    </div>

    <div class="wc-fish-atmos" aria-hidden="true">
        <div class="wc-fish-sun"></div>
        <div class="wc-fish-sun-glow"></div>
        <div class="wc-fish-cloud c1"></div>
        <div class="wc-fish-cloud c2"></div>
        <div class="wc-fish-cloud c3"></div>
        <div class="wc-fish-ray r1"></div>
        <div class="wc-fish-ray r2"></div>
        <div class="wc-fish-birds"><span></span><span></span><span></span></div>
    </div>

    <header class="wc-fish-brand">
        <div class="wc-logo-mark"><i class="fa-solid fa-comments"></i></div>
        <div>
            <h1 class="wc-brand-wordmark"><span class="wc-brand-web">Web</span><span class="wc-brand-chat">Chat</span></h1>
            <p><?= e(__('auth.fish_tagline')) ?></p>
        </div>
    </header>

    <p class="wc-fish-story" id="fishStory" aria-live="polite"><?= e(__('auth.fish_story_sail')) ?></p>

    <svg class="wc-fish-line-canvas" id="fishLineSvg" aria-hidden="true">
        <path id="fishLinePath" fill="none" stroke="rgba(55,40,30,.75)" stroke-width="2.4" stroke-linecap="round" stroke-dasharray="8 6"/>
    </svg>

    <div class="wc-fish-stage">
        <!-- Boat sails left → center -->
        <div class="wc-fish-boat-wrap" id="fishBoat" aria-hidden="true">
            <svg class="wc-fish-boat" viewBox="0 0 480 320" xmlns="http://www.w3.org/2000/svg">
                <defs>
                    <linearGradient id="hillGrad" x1="0" y1="0" x2="0" y2="1">
                        <stop offset="0%" stop-color="#8ec9a8"/><stop offset="100%" stop-color="#5a9a78"/>
                    </linearGradient>
                    <linearGradient id="boatGrad" x1="0" y1="0" x2="0" y2="1">
                        <stop offset="0%" stop-color="#c8956a"/><stop offset="100%" stop-color="#7a4a28"/>
                    </linearGradient>
                    <linearGradient id="shirtGrad" x1="0" y1="0" x2="1" y2="1">
                        <stop offset="0%" stop-color="#2ecc71"/><stop offset="100%" stop-color="#1a7a4a"/>
                    </linearGradient>
                </defs>
                <path d="M0 210 C80 185 160 200 240 195 C320 188 400 200 480 192" fill="none" stroke="#6aab8a" stroke-width="14" opacity=".25"/>
                <g class="wc-rod">
                    <path d="M195 130 C250 75 320 45 400 32" fill="none" stroke="#4a3222" stroke-width="3.5" stroke-linecap="round"/>
                    <circle id="rodTip" cx="400" cy="32" r="4" fill="#d4af37"/>
                    <circle cx="195" cy="130" r="5" fill="#3d2b1f"/>
                </g>
                <g class="wc-fisher">
                    <ellipse cx="175" cy="188" rx="28" ry="12" fill="#000" opacity=".15"/>
                    <path d="M155 185 C148 205 140 218 132 224" fill="none" stroke="#34495e" stroke-width="11" stroke-linecap="round"/>
                    <path d="M178 185 C188 205 205 218 218 222" fill="none" stroke="#2c3e50" stroke-width="11" stroke-linecap="round"/>
                    <path d="M148 168 C152 140 170 132 188 140 C200 148 202 170 192 188 L150 188 Z" fill="url(#shirtGrad)"/>
                    <path d="M185 150 C205 138 215 132 195 130" fill="none" stroke="#e8b896" stroke-width="8" stroke-linecap="round"/>
                    <circle cx="174" cy="122" r="18" fill="#f2c4a0"/>
                    <ellipse cx="168" cy="124" rx="2.5" ry="3" fill="#5d4037" opacity=".35"/>
                    <ellipse cx="182" cy="124" rx="2.5" ry="3" fill="#5d4037" opacity=".35"/>
                    <path d="M168 134 Q174 138 180 134" fill="none" stroke="#c07850" stroke-width="1.5" stroke-linecap="round"/>
                    <ellipse cx="174" cy="110" rx="24" ry="8" fill="#e6b422"/>
                    <path d="M156 110 Q174 92 192 110" fill="#d4a017"/>
                </g>
                <g class="wc-boat-hull">
                    <path d="M70 200 C110 186 220 182 320 192 C348 196 360 208 345 218 C300 242 120 244 80 224 C58 212 52 204 70 200 Z" fill="url(#boatGrad)"/>
                    <path d="M85 202 C120 192 220 188 310 196 C335 200 342 208 330 214 C290 230 135 232 98 218 C82 210 76 204 85 202 Z" fill="#d4a574"/>
                    <path d="M190 192 L198 140 L206 192" fill="#5c3a21"/>
                    <path d="M198 140 L255 165 L198 172 Z" fill="#fff8e7" opacity=".95"/>
                    <ellipse cx="200" cy="228" rx="110" ry="10" fill="#fff" opacity=".12"/>
                </g>
            </svg>
            <div class="wc-boat-wake" aria-hidden="true"><span></span><span></span><span></span></div>
        </div>

        <!-- Login starts underwater, then gets reeled up -->
        <div class="wc-fish-catch" id="fishCatch">
            <div class="wc-fish-hook" id="fishHook" aria-hidden="true">
                <span class="wc-hook-twinkle"></span>
                <svg viewBox="0 0 48 64" width="42" height="56">
                    <defs>
                        <linearGradient id="hookMetal" x1="0" y1="0" x2="1" y2="1">
                            <stop offset="0%" stop-color="#f0f0f0"/>
                            <stop offset="50%" stop-color="#9a9a9a"/>
                            <stop offset="100%" stop-color="#6a6a6a"/>
                        </linearGradient>
                    </defs>
                    <path d="M24 4 L24 26" stroke="#5c4030" stroke-width="2.8" stroke-linecap="round"/>
                    <circle id="hookTop" cx="24" cy="4" r="4" fill="#e8c547"/>
                    <path d="M24 26 C8 26 6 44 16 52 C24 58 36 52 36 40" fill="none" stroke="url(#hookMetal)" stroke-width="4" stroke-linecap="round"/>
                </svg>
            </div>
            <div class="wc-fish-ripples" aria-hidden="true"><span></span><span></span><span></span></div>
            <div class="wc-auth-panel wc-fish-panel">
                <div class="wc-fish-panel-badge">
                    <i class="fa-solid fa-fish"></i>
                    <span><?= e(__('auth.fish_catch')) ?></span>
                </div>
                <h2 class="wc-auth-panel-title"><?= e(__('auth.sign_in')) ?></h2>
                <?php if ($error): ?>
                    <div class="alert alert-danger py-2"><?= e($error) ?></div>
                <?php endif; ?>
                <form method="post" autocomplete="off" novalidate>
                    <?= csrf_field() ?>
                    <div class="mb-3">
                        <label class="form-label" for="login"><?= e(__('auth.username_or_email')) ?></label>
                        <input type="text" class="form-control" id="login" name="login" required
                               value="<?= e($_POST['login'] ?? '') ?>" autocomplete="off">
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="password"><?= e(__('auth.password')) ?></label>
                        <input type="password" class="form-control" id="password" name="password" required
                               autocomplete="new-password" value="">
                    </div>
                    <button type="submit" class="btn btn-wc w-100 wc-fish-submit">
                        <span><?= e(__('auth.sign_in')) ?></span>
                        <i class="fa-solid fa-arrow-right-long"></i>
                    </button>
                </form>
                <p class="wc-auth-footer mb-0">
                    <?= e(__('auth.new_here')) ?>
                    <a href="<?= e(url('register.php')) ?>"><?= e(__('auth.create_account')) ?></a>
                </p>
            </div>
        </div>
    </div>

    <div class="wc-fish-water" aria-hidden="true">
        <svg class="wc-water-svg" viewBox="0 0 1440 320" preserveAspectRatio="none">
            <path class="wc-water-path p1" fill="rgba(255,255,255,.18)" d="M0,160 C240,100 480,220 720,160 C960,100 1200,200 1440,140 L1440,320 L0,320 Z"/>
            <path class="wc-water-path p2" fill="rgba(40,120,150,.35)" d="M0,200 C320,140 560,240 880,180 C1120,140 1280,220 1440,180 L1440,320 L0,320 Z"/>
            <path class="wc-water-path p3" fill="rgba(20,70,95,.55)" d="M0,240 C280,200 520,280 800,230 C1080,180 1280,260 1440,230 L1440,320 L0,320 Z"/>
        </svg>
        <div class="wc-bubbles"><span></span><span></span><span></span><span></span><span></span><span></span></div>
        <div class="wc-swim-fish">
            <svg class="sf1" viewBox="0 0 64 28" width="48"><ellipse cx="28" cy="14" rx="22" ry="10" fill="#0a3a4a" opacity=".45"/><polygon points="50,14 64,4 64,24" fill="#0a3a4a" opacity=".45"/><circle cx="16" cy="12" r="2" fill="#7ec8d8"/></svg>
            <svg class="sf2" viewBox="0 0 64 28" width="36"><ellipse cx="28" cy="14" rx="22" ry="10" fill="#0a3a4a" opacity=".35"/><polygon points="50,14 64,4 64,24" fill="#0a3a4a" opacity=".35"/><circle cx="16" cy="12" r="2" fill="#7ec8d8"/></svg>
            <svg class="sf3" viewBox="0 0 64 28" width="28"><ellipse cx="28" cy="14" rx="22" ry="10" fill="#0a3a4a" opacity=".3"/><polygon points="50,14 64,4 64,24" fill="#0a3a4a" opacity=".3"/><circle cx="16" cy="12" r="2" fill="#7ec8d8"/></svg>
        </div>
    </div>
</div>
<?php require __DIR__ . '/includes/layout_end.php'; ?>
