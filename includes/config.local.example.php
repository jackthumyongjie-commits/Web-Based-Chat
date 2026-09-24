<?php
/**
 * cPanel / production overrides — copy to config.local.php and edit.
 * Do NOT upload your XAMPP config.local.php (it has local passwords + debug on).
 */
declare(strict_types=1);

define('DB_HOST', 'localhost');          // often "localhost" on cPanel
define('DB_NAME', 'cpaneluser_webchat'); // full DB name from cPanel
define('DB_USER', 'cpaneluser_webchat'); // full DB user from cPanel
define('DB_PASS', 'CHANGE_ME_STRONG_PASSWORD');

// No trailing slash. Subfolder example: https://example.com/webchat
define('APP_URL', 'https://your-domain.com');

define('APP_DEBUG', false);
define('ALLOW_DEMO_ADMIN_SEED', false);

// Keep false on cPanel. (true is only for Cloudflare tunnel testing.)
define('APP_URL_AUTO', false);
// Only used if APP_URL_AUTO=true: '' for domain root, or '/webchat' for a subfolder
define('APP_BASE_PATH', '');
