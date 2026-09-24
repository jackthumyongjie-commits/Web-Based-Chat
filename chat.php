<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/bootstrap.php';
$currentUser = require_login();
$pageTitle = __('chat.title');
$activeNav = 'chat';
$bodyClass = 'wc-chat-page';
$extraJs = [asset('js/chat.js')];
require __DIR__ . '/includes/layout_start.php';
?>
<div id="chatApp" class="wc-chat-shell">
    <aside class="wc-chat-col wc-chat-sidebar" id="chatSidebar">
        <div class="wc-chat-col-head d-flex justify-content-between align-items-center">
            <span><?= e(__('chat.conversations')) ?></span>
            <a href="<?= e(url('friends.php')) ?>" class="btn btn-sm btn-outline-secondary" title="<?= e(__('chat.find_friends')) ?>">
                <i class="fa-solid fa-user-plus"></i>
            </a>
        </div>
        <div class="p-2 border-bottom">
            <input type="search" id="convSearch" class="form-control form-control-sm" placeholder="<?= e(__('chat.search')) ?>">
        </div>
        <div class="wc-conv-list" id="convList">
            <div class="wc-empty"><?= e(__('common.loading')) ?></div>
        </div>
    </aside>

    <section class="wc-chat-col wc-chat-main" id="chatMain">
        <div id="chatEmpty" class="wc-chat-empty">
            <div class="wc-empty">
                <i class="fa-regular fa-comments fa-2x mb-2"></i>
                <div><?= e(__('chat.select')) ?></div>
            </div>
        </div>
        <div id="chatActive" class="d-none flex-column flex-grow-1" style="min-height:0;display:none;">
            <div class="wc-chat-col-head d-flex align-items-center gap-2">
                <button type="button" class="btn btn-sm btn-light d-md-none" id="btnBackToList"><i class="fa-solid fa-arrow-left"></i></button>
                <div class="flex-grow-1 min-w-0">
                    <div id="chatTitle"><?= e(__('js.select_conversation')) ?></div>
                    <div class="small text-muted text-truncate" id="chatSubtitle"></div>
                </div>
                <div class="wc-chat-header-actions d-flex align-items-center gap-1">
                    <button type="button" class="btn btn-sm btn-light wc-header-call-btn" id="btnHeaderVoiceCall"
                            title="<?= e(__('chat.voice_call')) ?>">
                        <i class="fa-solid fa-phone"></i>
                    </button>
                    <button type="button" class="btn btn-sm btn-light wc-header-call-btn" id="btnHeaderVideoCall"
                            title="<?= e(__('chat.video_call')) ?>">
                        <i class="fa-solid fa-video"></i>
                    </button>
                </div>
            </div>
            <div class="wc-msg-list" id="msgList"></div>
            <div class="wc-composer">
                <div class="wc-typing" id="typingIndicator"></div>
                <div class="wc-reply-bar" id="replyBar">
                    <span><?= e(__('chat.replying_to')) ?> <strong id="replyText"></strong></span>
                    <button type="button" class="btn btn-sm btn-link" id="btnCancelReply"><?= e(__('common.cancel')) ?></button>
                </div>
                <div class="wc-record-bar" id="recordBar">
                    <span class="wc-rec-dot"></span>
                    <span id="recordTimer">00:00</span>
                    <button type="button" class="btn btn-sm btn-outline-danger" id="btnCancelVoice"><?= e(__('common.cancel')) ?></button>
                    <button type="button" class="btn btn-sm btn-wc" id="btnSendVoice"><?= e(__('common.send')) ?></button>
                </div>
                <div class="wc-composer-row">
                    <label class="btn btn-light mb-0" title="Image">
                        <i class="fa-regular fa-image"></i>
                        <input type="file" id="btnAttachImage" accept="image/*" hidden>
                    </label>
                    <label class="btn btn-light mb-0" title="File">
                        <i class="fa-solid fa-paperclip"></i>
                        <input type="file" id="btnAttachFile" accept=".pdf,.doc,.docx,.xls,.xlsx,.txt,.zip" hidden>
                    </label>
                    <button type="button" class="btn btn-light" id="btnStartVoice" title="Voice">
                        <i class="fa-solid fa-microphone"></i>
                    </button>
                    <div class="wc-emoji-wrap">
                        <button type="button" class="btn btn-light" id="btnEmoji" title="<?= e(__('js.react')) ?>">
                            <i class="fa-regular fa-face-smile"></i>
                        </button>
                        <div class="wc-emoji-panel" id="emojiPanel" hidden></div>
                    </div>
                    <textarea id="composerText" class="form-control" rows="1" placeholder="<?= e(__('chat.type_message')) ?>"></textarea>
                    <button type="button" class="btn btn-wc" id="btnSend"><i class="fa-solid fa-paper-plane"></i></button>
                </div>
            </div>
        </div>
    </section>

    <aside class="wc-chat-col wc-chat-info" id="chatInfo">
        <div class="wc-chat-col-head"><?= e(__('chat.contact_info')) ?></div>
        <div class="wc-info-body" id="infoPanel">
            <p class="text-muted"><?= e(__('chat.select_contact')) ?></p>
        </div>
    </aside>
</div>
<?php require __DIR__ . '/includes/layout_end.php'; ?>
