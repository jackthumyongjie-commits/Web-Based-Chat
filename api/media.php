<?php
/**
 * Secure media delivery with auth + range support
 */
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/bootstrap.php';

$user = require_login();
$uid = (int) $user['id'];

$rel = (string) ($_GET['f'] ?? '');
$rel = str_replace('\\', '/', $rel);
$rel = ltrim($rel, '/');

if (!is_safe_upload_path($rel)) {
    http_response_code(400);
    exit(__('media.invalid'));
}

$full = absolute_upload_path($rel);
if ($full === null || !is_file($full)) {
    http_response_code(404);
    exit(__('common.not_found'));
}

// Authorization by path category
$allowed = false;
[$category, $filename] = explode('/', $rel, 2);

if ($category === 'avatars' || $category === 'groups') {
    $allowed = true; // avatars/group avatars are semi-public to logged-in users
} else {
    // images/files/voices — must be referenced by a message the user can access
    $stmt = db()->prepare(
        'SELECT id FROM messages WHERE file_path = ? AND (sender_id = ? OR receiver_id = ?) LIMIT 1'
    );
    $stmt->execute([$rel, $uid, $uid]);
    if ($stmt->fetch()) {
        $allowed = true;
    } else {
        $stmt = db()->prepare(
            'SELECT gm.id FROM group_messages gm
             JOIN group_members m ON m.group_id = gm.group_id AND m.user_id = ?
             WHERE gm.file_path = ? LIMIT 1'
        );
        $stmt->execute([$uid, $rel]);
        if ($stmt->fetch()) {
            $allowed = true;
        }
    }
}

if (!$allowed) {
    http_response_code(403);
    exit(__('media.forbidden'));
}

$mime = detect_mime($full);
// Prefer playback-friendly types by extension when finfo is vague
$ext = strtolower(pathinfo($full, PATHINFO_EXTENSION));
$extMime = [
    'webm' => 'audio/webm',
    'ogg' => 'audio/ogg',
    'mp3' => 'audio/mpeg',
    'wav' => 'audio/wav',
    'm4a' => 'audio/mp4',
    'mp4' => 'audio/mp4',
    'aac' => 'audio/aac',
];
if ($category === 'voices' && isset($extMime[$ext])) {
    if ($mime === 'application/octet-stream' || $mime === 'text/plain' || !str_contains($mime, '/')) {
        $mime = $extMime[$ext];
    } elseif ($ext === 'webm' && str_starts_with($mime, 'video/')) {
        // Keep video/webm — browsers still play it in <audio>
        $mime = 'audio/webm';
    }
}
$size = filesize($full);
if ($size === false) {
    http_response_code(404);
    exit(__('common.not_found'));
}

$inlineTypes = ['image/', 'audio/', 'video/', 'text/plain'];
$disposition = 'attachment';
foreach ($inlineTypes as $prefix) {
    if (str_starts_with($mime, $prefix)) {
        $disposition = 'inline';
        break;
    }
}

header('Content-Type: ' . $mime);
header('X-Content-Type-Options: nosniff');
header('Content-Disposition: ' . $disposition . '; filename="' . basename($filename) . '"');
header('Accept-Ranges: bytes');
header('Cache-Control: private, max-age=3600');

$start = 0;
$end = $size - 1;
$httpRange = $_SERVER['HTTP_RANGE'] ?? null;

if ($httpRange && preg_match('/bytes=(\d*)-(\d*)/', $httpRange, $m)) {
    if ($m[1] !== '') {
        $start = (int) $m[1];
    }
    if ($m[2] !== '') {
        $end = (int) $m[2];
    }
    if ($start > $end || $start >= $size) {
        http_response_code(416);
        header("Content-Range: bytes */$size");
        exit;
    }
    $end = min($end, $size - 1);
    $length = $end - $start + 1;
    http_response_code(206);
    header("Content-Range: bytes $start-$end/$size");
    header('Content-Length: ' . $length);
    $fp = fopen($full, 'rb');
    fseek($fp, $start);
    $remaining = $length;
    while ($remaining > 0 && !feof($fp)) {
        $chunk = fread($fp, min(8192, $remaining));
        if ($chunk === false) {
            break;
        }
        echo $chunk;
        $remaining -= strlen($chunk);
    }
    fclose($fp);
    exit;
}

header('Content-Length: ' . $size);
readfile($full);
exit;
