<?php
/**
 * WebConnect i18n
 */

declare(strict_types=1);

const WC_LANGS = ['en', 'zh'];

function init_language(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return;
    }

    if (isset($_GET['lang']) && in_array($_GET['lang'], WC_LANGS, true)) {
        $_SESSION['lang'] = $_GET['lang'];
        $secure = function_exists('request_is_https') ? request_is_https() : false;
        setcookie('wc_lang', $_GET['lang'], [
            'expires'  => time() + 86400 * 365,
            'path'     => '/',
            'secure'   => $secure,
            'httponly' => false,
            'samesite' => 'Lax',
        ]);

        // Redirect to same URL without lang query to avoid re-trigger
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $parts = parse_url($uri);
        $path = $parts['path'] ?? '/';
        $query = [];
        if (!empty($parts['query'])) {
            parse_str($parts['query'], $query);
            unset($query['lang']);
        }
        $redirect = $path . ($query ? ('?' . http_build_query($query)) : '');
        header('Location: ' . $redirect);
        exit;
    }

    if (empty($_SESSION['lang'])) {
        if (!empty($_COOKIE['wc_lang']) && in_array($_COOKIE['wc_lang'], WC_LANGS, true)) {
            $_SESSION['lang'] = $_COOKIE['wc_lang'];
        } else {
            $header = strtolower($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '');
            $_SESSION['lang'] = str_contains($header, 'zh') ? 'zh' : 'en';
        }
    }
}

function current_lang(): string
{
    $lang = $_SESSION['lang'] ?? 'en';
    return in_array($lang, WC_LANGS, true) ? $lang : 'en';
}

function lang_dict(): array
{
    static $cache = [];
    $lang = current_lang();
    if (isset($cache[$lang])) {
        return $cache[$lang];
    }
    $file = APP_ROOT . DIRECTORY_SEPARATOR . 'lang' . DIRECTORY_SEPARATOR . $lang . '.php';
    $cache[$lang] = is_file($file) ? (require $file) : [];
    return $cache[$lang];
}

/**
 * Translate a key. Supports {name} placeholders.
 */
function __(string $key, array $replace = []): string
{
    $dict = lang_dict();
    $text = $dict[$key] ?? $key;
    foreach ($replace as $k => $v) {
        $text = str_replace('{' . $k . '}', (string) $v, $text);
    }
    return $text;
}

function lang_switch_url(string $lang): string
{
    $uri = $_SERVER['REQUEST_URI'] ?? '/';
    $parts = parse_url($uri);
    $path = $parts['path'] ?? '/';
    $query = [];
    if (!empty($parts['query'])) {
        parse_str($parts['query'], $query);
    }
    $query['lang'] = $lang;
    return $path . '?' . http_build_query($query);
}

function js_translations(): array
{
    $keys = [
        'js.loading', 'js.no_conversations', 'js.select_conversation', 'js.message_deleted',
        'js.image', 'js.file', 'js.voice_message', 'js.typing', 'js.typing_many',
        'js.replying_to', 'js.reply', 'js.react', 'js.delete', 'js.report', 'js.cancel',
        'js.send', 'js.read', 'js.contact_info', 'js.no_status', 'js.voice_call', 'js.video_call',
        'js.block', 'js.block_confirm', 'js.user_blocked', 'js.unable_send', 'js.upload_failed',
        'js.mic_required', 'js.voice_unsupported', 'js.voice_empty', 'js.delete_confirm', 'js.report_reason', 'js.report_submitted',
        'js.already_in_call', 'js.unable_start_call', 'js.unable_accept_call', 'js.call_ended',
        'js.call_failed_turn', 'js.calling', 'js.ringing', 'js.connecting', 'js.connected',
        'js.media_unavailable', 'js.media_mic_denied', 'js.media_cam_denied',
        'js.media_mic_missing', 'js.media_cam_missing', 'js.media_mic_busy', 'js.media_cam_busy',
        'js.media_insecure',
        'js.incoming_call', 'js.accept', 'js.reject', 'js.mute', 'js.camera', 'js.end_call',
        'js.friends', 'js.no_friends', 'js.search_users', 'js.add', 'js.accept_req', 'js.reject_req',
        'js.cancel_req', 'js.remove', 'js.unblock', 'js.nobody_blocked', 'js.no_incoming',
        'js.no_outgoing', 'js.request_sent', 'js.accepted', 'js.remove_friend_confirm',
        'js.groups', 'js.no_groups', 'js.create_group', 'js.open', 'js.members', 'js.add_friend_first',
        'js.add_friend', 'js.no_friends_add', 'js.posts_count',
        'js.group_created', 'js.member_added', 'js.avatar_updated', 'js.role_updated',
        'js.leave_group_confirm', 'js.delete_group_confirm', 'js.leave_group', 'js.delete_group',
        'js.add_member', 'js.group_avatar', 'js.settings', 'js.back', 'js.message_group',
        'js.profile_updated', 'js.password_changed', 'js.no_notifications', 'js.mark_all_read',
        'js.community', 'js.categories', 'js.posts', 'js.new_post', 'js.all', 'js.no_posts',
        'auth.fish_story_sail', 'auth.fish_story_cast', 'auth.fish_story_catch', 'auth.fish_story_ready',
        'js.by', 'js.pinned', 'js.comments', 'js.no_comments', 'js.write_comment', 'js.comment',
        'js.like', 'js.share', 'js.share_copied', 'js.post_locked', 'js.publish', 'js.title', 'js.content',
        'js.category', 'js.invalid_response', 'js.request_failed', 'js.search_conversations',
        'js.find_friends', 'js.type_message', 'js.no_users_found', 'js.blocked_badge',
        'js.friends_badge', 'js.owner', 'js.admin', 'js.member', 'js.unable_establish_call',
        'js.online', 'js.offline', 'js.away', 'js.busy', 'nav.chat',
    ];
    $out = [];
    foreach ($keys as $k) {
        $out[$k] = __($k);
    }
    // Also expose common short keys used in JS
    $out['deleted_message'] = __('msg.deleted');
    $out['image_label'] = __('msg.type_image');
    $out['file_label'] = __('msg.type_file');
    $out['voice_label'] = __('msg.type_voice');
    return $out;
}
