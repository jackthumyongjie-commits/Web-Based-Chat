<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/bootstrap.php';

$user = require_login();
require_csrf();
require_rate_limit('upload', RATE_UPLOAD, (int) $user['id']);

$kind = str_input('kind', 'file'); // image|file|voice|avatar|group_avatar

$map = [
    'image' => ['images', ALLOWED_IMAGE_EXT, ALLOWED_IMAGE_MIME, MAX_IMAGE_SIZE],
    'file' => ['files', ALLOWED_FILE_EXT, ALLOWED_FILE_MIME, MAX_FILE_SIZE],
    'voice' => ['voices', ALLOWED_VOICE_EXT, ALLOWED_VOICE_MIME, MAX_VOICE_SIZE],
    'avatar' => ['avatars', ALLOWED_IMAGE_EXT, ALLOWED_IMAGE_MIME, MAX_AVATAR_SIZE],
    'group_avatar' => ['groups', ALLOWED_IMAGE_EXT, ALLOWED_IMAGE_MIME, MAX_AVATAR_SIZE],
];

if (!isset($map[$kind]) || empty($_FILES['file'])) {
    json_error(__('upload.invalid'));
}

[$subdir, $ext, $mimes, $max] = $map[$kind];
$result = store_uploaded_file($_FILES['file'], $subdir, $ext, $mimes, $max);
if (!$result['ok']) {
    json_error($result['error']);
}

json_success(__('upload.ok'), [
    'path' => $result['path'],
    'file_name' => $result['file_name'],
    'mime_type' => $result['mime_type'],
    'file_size' => $result['file_size'],
    'url' => media_url($result['path']),
]);
