<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/bootstrap.php';
$currentUser = require_login();
$groupId = (int) ($_GET['id'] ?? 0);
if ($groupId <= 0) {
    redirect('groups.php');
}
$pageTitle = __('nav.groups');
$activeNav = 'groups';
$bodyClass = 'wc-chat-page';
$extraJs = [asset('js/group_chat.js')];
require __DIR__ . '/includes/layout_start.php';
?>
<div id="groupChatApp" class="wc-chat-shell wc-group-chat-shell" data-group-id="<?= (int) $groupId ?>">
    <section class="wc-chat-col wc-group-chat-main" id="groupChatMain">
        <div class="wc-chat-col-head d-flex justify-content-between align-items-center gap-2">
            <div class="wc-group-chat-title min-w-0 flex-grow-1">
                <div id="groupTitle" class="text-truncate"><?= e(__('nav.groups')) ?></div>
                <div class="small text-muted text-truncate" id="groupSubtitle"></div>
            </div>
            <div class="d-flex align-items-center gap-1 flex-shrink-0">
                <button type="button" class="btn btn-sm btn-outline-secondary d-md-none" id="btnGroupInfo" title="<?= e(__('groups.settings')) ?>" aria-label="<?= e(__('groups.settings')) ?>">
                    <i class="fa-solid fa-ellipsis"></i>
                </button>
                <a href="<?= e(url('groups.php')) ?>" class="btn btn-sm btn-outline-secondary"><?= e(__('common.back')) ?></a>
            </div>
        </div>
        <div class="wc-msg-list" id="gMsgList"></div>
        <div class="wc-composer">
            <div class="wc-typing" id="gTyping"></div>
            <div class="wc-reply-bar" id="gReplyBar">
                <span><?= e(__('chat.replying_to')) ?> <strong id="gReplyText"></strong></span>
                <button type="button" class="btn btn-sm btn-link" id="gCancelReply"><?= e(__('common.cancel')) ?></button>
            </div>
            <div class="wc-record-bar" id="gRecordBar">
                <span class="wc-rec-dot"></span>
                <span id="gRecordTimer">00:00</span>
                <button type="button" class="btn btn-sm btn-outline-danger" id="gCancelVoice"><?= e(__('common.cancel')) ?></button>
                <button type="button" class="btn btn-sm btn-wc" id="gSendVoice"><?= e(__('common.send')) ?></button>
            </div>
            <div class="wc-composer-row">
                <label class="btn btn-light mb-0"><i class="fa-regular fa-image"></i>
                    <input type="file" id="gImage" accept="image/*" hidden></label>
                <label class="btn btn-light mb-0"><i class="fa-solid fa-paperclip"></i>
                    <input type="file" id="gFile" accept=".pdf,.doc,.docx,.xls,.xlsx,.txt,.zip" hidden></label>
                <button type="button" class="btn btn-light" id="gStartVoice"><i class="fa-solid fa-microphone"></i></button>
                <div class="wc-emoji-wrap">
                    <button type="button" class="btn btn-light" id="gBtnEmoji" title="<?= e(__('js.react')) ?>">
                        <i class="fa-regular fa-face-smile"></i>
                    </button>
                    <div class="wc-emoji-panel" id="gEmojiPanel" hidden></div>
                </div>
                <textarea id="gComposer" class="form-control" rows="1" placeholder="<?= e(__('groups.message_ph')) ?>"></textarea>
                <button type="button" class="btn btn-wc" id="gSend"><i class="fa-solid fa-paper-plane"></i></button>
            </div>
        </div>
    </section>
    <aside class="wc-chat-col wc-group-chat-side" id="groupChatSide">
        <div class="wc-chat-col-head d-flex justify-content-between align-items-center">
            <span><?= e(__('groups.settings')) ?></span>
            <button type="button" class="btn btn-sm btn-outline-secondary d-md-none" id="btnCloseGroupInfo" aria-label="<?= e(__('common.back')) ?>">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <div class="p-3 wc-group-side-body" id="groupSide"></div>
    </aside>
</div>
<?php require __DIR__ . '/includes/layout_end.php'; ?>
