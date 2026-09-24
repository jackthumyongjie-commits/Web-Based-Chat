<?php
/**
 * Layout end
 * @var array $extraJs
 */
$extraJs = $extraJs ?? [];
$currentUser = $currentUser ?? current_user();
?>
</main>

<!-- Incoming call modal -->
<div class="modal fade" id="incomingCallModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content wc-call-modal">
            <div class="modal-body text-center p-4">
                <img id="incomingCallAvatar" src="" alt="" class="wc-avatar-xl mb-3">
                <h5 id="incomingCallName" class="mb-1"><?= e(__('call.incoming')) ?></h5>
                <p id="incomingCallType" class="text-muted mb-4"><?= e(__('call.voice')) ?></p>
                <div class="d-flex justify-content-center gap-3">
                    <button type="button" class="btn btn-success btn-lg rounded-circle" id="btnAcceptCall" title="<?= e(__('call.accept')) ?>">
                        <i class="fa-solid fa-phone"></i>
                    </button>
                    <button type="button" class="btn btn-danger btn-lg rounded-circle" id="btnRejectCall" title="<?= e(__('call.reject')) ?>">
                        <i class="fa-solid fa-phone-slash"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Active call overlay -->
<div id="callOverlay" class="wc-call-overlay d-none" aria-live="polite">
    <div class="wc-call-stage">
        <video id="remoteVideo" autoplay playsinline webkit-playsinline class="wc-remote-video"></video>
        <video id="localVideo" autoplay playsinline webkit-playsinline muted class="wc-local-video"></video>
        <audio id="remoteAudio" autoplay playsinline></audio>
        <div id="voiceCallPanel" class="wc-voice-panel text-center">
            <img id="activeCallAvatar" src="" alt="" class="wc-avatar-xl mb-3">
            <h4 id="activeCallName"><?= e(__('call.calling')) ?></h4>
            <p id="activeCallStatus" class="wc-call-status"><?= e(__('call.connecting')) ?></p>
        </div>
        <div class="wc-call-controls">
            <button type="button" class="btn btn-light rounded-circle" id="btnToggleMute" title="<?= e(__('call.mute')) ?>">
                <i class="fa-solid fa-microphone"></i>
            </button>
            <button type="button" class="btn btn-light rounded-circle d-none" id="btnToggleCamera" title="<?= e(__('call.camera')) ?>">
                <i class="fa-solid fa-video"></i>
            </button>
            <button type="button" class="btn btn-danger rounded-circle" id="btnEndCall" title="<?= e(__('call.end')) ?>">
                <i class="fa-solid fa-phone-slash"></i>
            </button>
        </div>
    </div>
</div>

<script>
window.WebConnect = window.WebConnect || {};
window.WebConnect.APP_URL = <?= json_encode(app_url(), JSON_UNESCAPED_SLASHES) ?>;
window.WebConnect.CSRF = <?= json_encode(csrf_token()) ?>;
window.WebConnect.USER_ID = <?= json_encode((int) ($currentUser['id'] ?? 0)) ?>;
window.WebConnect.RTC_CONFIG = <?= json_encode(['iceServers' => RTC_ICE_SERVERS], JSON_UNESCAPED_SLASHES) ?>;
window.WebConnect.REACTIONS = <?= json_encode(ALLOWED_REACTIONS, JSON_UNESCAPED_UNICODE) ?>;
window.WebConnect.LANG = <?= json_encode(current_lang()) ?>;
window.WebConnect.I18N = <?= json_encode(js_translations(), JSON_UNESCAPED_UNICODE) ?>;
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= e(asset('js/app.js')) ?>"></script>
<?php if ($currentUser): ?>
<script src="<?= e(asset('js/calls.js')) ?>"></script>
<?php endif; ?>
<?php foreach ($extraJs as $js): ?>
    <script src="<?= e($js) ?>"></script>
<?php endforeach; ?>
</body>
</html>
