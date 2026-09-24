<?php
/**
 * WebChat — central configuration
 * Override locally via includes/config.local.php (loaded first)
 */

declare(strict_types=1);

// Local overrides first (so constants can be set before defaults)
$localConfig = __DIR__ . DIRECTORY_SEPARATOR . 'config.local.php';
if (is_file($localConfig)) {
    require $localConfig;
}

if (!defined('APP_NAME')) {
    define('APP_NAME', 'WebChat');
}
if (!defined('APP_TAGLINE')) {
    define('APP_TAGLINE', 'Chat · Voice · Video');
}
if (!defined('APP_VERSION')) {
    define('APP_VERSION', '1.0.0');
}

// Default placeholders — ALWAYS override via config.local.php on cPanel
if (!defined('DB_HOST')) {
    define('DB_HOST', 'localhost');
}
if (!defined('DB_NAME')) {
    define('DB_NAME', 'webchat');
}
if (!defined('DB_USER')) {
    define('DB_USER', 'root');
}
if (!defined('DB_PASS')) {
    define('DB_PASS', '');
}
if (!defined('DB_CHARSET')) {
    define('DB_CHARSET', 'utf8mb4');
}

// Adjust to your install path (no trailing slash)
if (!defined('APP_URL')) {
    define('APP_URL', 'http://localhost/project/WebChat');
}

if (!defined('APP_ROOT')) {
    define('APP_ROOT', dirname(__DIR__));
}
if (!defined('UPLOAD_ROOT')) {
    define('UPLOAD_ROOT', APP_ROOT . DIRECTORY_SEPARATOR . 'uploads');
}
if (!defined('LOG_ROOT')) {
    define('LOG_ROOT', APP_ROOT . DIRECTORY_SEPARATOR . 'logs');
}

// Sessions
if (!defined('SESSION_NAME')) {
    define('SESSION_NAME', 'webconnect_sess');
}
if (!defined('ADMIN_SESSION_NAME')) {
    define('ADMIN_SESSION_NAME', 'webconnect_admin_sess');
}
if (!defined('SESSION_LIFETIME')) {
    define('SESSION_LIFETIME', 60 * 60 * 8); // 8 hours
}

// Security
if (!defined('CSRF_TOKEN_KEY')) {
    define('CSRF_TOKEN_KEY', '_csrf_token');
}
if (!defined('PASSWORD_MIN_LENGTH')) {
    define('PASSWORD_MIN_LENGTH', 8);
}

// Uploads
if (!defined('MAX_IMAGE_SIZE')) {
    define('MAX_IMAGE_SIZE', 5 * 1024 * 1024);
}
if (!defined('MAX_FILE_SIZE')) {
    define('MAX_FILE_SIZE', 20 * 1024 * 1024);
}
if (!defined('MAX_VOICE_SIZE')) {
    define('MAX_VOICE_SIZE', 10 * 1024 * 1024);
}
if (!defined('MAX_AVATAR_SIZE')) {
    define('MAX_AVATAR_SIZE', 2 * 1024 * 1024);
}

// Polling / presence
if (!defined('TYPING_TTL_SECONDS')) {
    define('TYPING_TTL_SECONDS', 4);
}
if (!defined('PRESENCE_ONLINE_SECONDS')) {
    define('PRESENCE_ONLINE_SECONDS', 90);
}
if (!defined('PRESENCE_AWAY_SECONDS')) {
    define('PRESENCE_AWAY_SECONDS', 300);
}

// Rate limits: [max hits, window seconds]
if (!defined('RATE_LOGIN')) {
    define('RATE_LOGIN', [10, 300]);
}
if (!defined('RATE_MESSAGE')) {
    define('RATE_MESSAGE', [60, 60]);
}
if (!defined('RATE_FRIEND')) {
    define('RATE_FRIEND', [20, 300]);
}
if (!defined('RATE_FORUM')) {
    define('RATE_FORUM', [15, 300]);
}
if (!defined('RATE_CALL')) {
    define('RATE_CALL', [10, 120]);
}
if (!defined('RATE_UPLOAD')) {
    define('RATE_UPLOAD', [30, 60]);
}

// WebRTC ICE servers (STUN + public TURN so phone ↔ PC can connect across NAT)
if (!defined('RTC_ICE_SERVERS')) {
    define('RTC_ICE_SERVERS', [
        ['urls' => 'stun:stun.l.google.com:19302'],
        ['urls' => 'stun:stun1.l.google.com:19302'],
        ['urls' => 'stun:stun.cloudflare.com:3478'],
        [
            'urls' => [
                'turn:openrelay.metered.ca:80',
                'turn:openrelay.metered.ca:80?transport=tcp',
                'turn:openrelay.metered.ca:443',
                'turn:openrelay.metered.ca:443?transport=tcp',
            ],
            'username' => 'openrelayproject',
            'credential' => 'openrelayproject',
        ],
    ]);
}

if (!defined('ALLOWED_IMAGE_EXT')) {
    define('ALLOWED_IMAGE_EXT', ['jpg', 'jpeg', 'png', 'gif', 'webp']);
}
if (!defined('ALLOWED_FILE_EXT')) {
    define('ALLOWED_FILE_EXT', ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'txt', 'zip']);
}
if (!defined('ALLOWED_VOICE_EXT')) {
    define('ALLOWED_VOICE_EXT', ['webm', 'ogg', 'mp3', 'wav', 'm4a', 'mp4', 'aac']);
}

if (!defined('ALLOWED_IMAGE_MIME')) {
    define('ALLOWED_IMAGE_MIME', [
        'image/jpeg', 'image/png', 'image/gif', 'image/webp',
    ]);
}
if (!defined('ALLOWED_FILE_MIME')) {
    define('ALLOWED_FILE_MIME', [
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'text/plain',
        'application/zip',
        'application/x-zip-compressed',
    ]);
}
if (!defined('ALLOWED_VOICE_MIME')) {
    define('ALLOWED_VOICE_MIME', [
        'audio/webm', 'audio/ogg', 'audio/mpeg', 'audio/mp3',
        'audio/wav', 'audio/x-wav', 'audio/mp4', 'audio/m4a',
        'audio/aac', 'audio/x-m4a', 'audio/3gpp',
        'video/webm', 'video/mp4',
        'application/octet-stream',
    ]);
}

if (!defined('ALLOWED_REACTIONS')) {
    define('ALLOWED_REACTIONS', ['👍', '❤️', '😂', '😮', '😢', '👏']);
}

date_default_timezone_set('Asia/Kuala_Lumpur');

if (!defined('APP_DEBUG')) {
    define('APP_DEBUG', false);
}
if (!defined('ALLOW_DEMO_ADMIN_SEED')) {
    define('ALLOW_DEMO_ADMIN_SEED', false);
}
if (!defined('APP_URL_AUTO')) {
    // true only for Cloudflare tunnel / local Host-header testing — keep false on cPanel
    define('APP_URL_AUTO', false);
}
if (!defined('APP_BASE_PATH')) {
    // Used only when APP_URL_AUTO=true. Domain root = ''; subfolder e.g. '/webchat'
    define('APP_BASE_PATH', '');
}
