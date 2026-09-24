<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/bootstrap.php';
$currentUser = require_login();
$pageTitle = __('friends.title');
$activeNav = 'friends';
$extraJs = [asset('js/friends.js')];
require __DIR__ . '/includes/layout_start.php';
?>
<div class="container wc-page py-3 wc-friends-page">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <h1 class="wc-page-title mb-0"><?= e(__('friends.title')) ?></h1>
    </div>

    <div class="wc-panel wc-friends-search mb-3">
        <div class="wc-friends-section-head">
            <h2 class="h6 mb-0"><i class="fa-solid fa-magnifying-glass me-2"></i><?= e(__('friends.search')) ?></h2>
        </div>
        <div class="p-3">
            <div class="input-group">
                <input type="search" id="friendSearch" class="form-control" placeholder="<?= e(__('friends.search_ph')) ?>" autocomplete="off">
                <button class="btn btn-wc px-3" type="button" id="btnFriendSearch">
                    <i class="fa-solid fa-magnifying-glass"></i>
                    <span class="d-none d-sm-inline ms-1"><?= e(__('common.search')) ?></span>
                </button>
            </div>
            <div id="searchResults" class="wc-friends-results mt-3 d-none"></div>
        </div>
    </div>

    <div class="wc-panel wc-friends-main">
        <ul class="nav nav-tabs wc-friends-tabs px-3 pt-2" id="friendsTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="tab-friends" data-bs-toggle="tab" data-bs-target="#pane-friends" type="button" role="tab">
                    <?= e(__('friends.my')) ?>
                    <span class="badge rounded-pill wc-count-badge" id="countFriends">0</span>
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="tab-incoming" data-bs-toggle="tab" data-bs-target="#pane-incoming" type="button" role="tab">
                    <?= e(__('friends.incoming')) ?>
                    <span class="badge rounded-pill wc-count-badge" id="countIncoming">0</span>
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="tab-outgoing" data-bs-toggle="tab" data-bs-target="#pane-outgoing" type="button" role="tab">
                    <?= e(__('friends.outgoing')) ?>
                    <span class="badge rounded-pill wc-count-badge" id="countOutgoing">0</span>
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="tab-blocked" data-bs-toggle="tab" data-bs-target="#pane-blocked" type="button" role="tab">
                    <?= e(__('friends.blocked')) ?>
                    <span class="badge rounded-pill wc-count-badge" id="countBlocked">0</span>
                </button>
            </li>
        </ul>
        <div class="tab-content">
            <div class="tab-pane fade show active" id="pane-friends" role="tabpanel">
                <div id="friendsList" class="wc-friends-list"></div>
            </div>
            <div class="tab-pane fade" id="pane-incoming" role="tabpanel">
                <div id="incomingRequests" class="wc-friends-list"></div>
            </div>
            <div class="tab-pane fade" id="pane-outgoing" role="tabpanel">
                <div id="outgoingRequests" class="wc-friends-list"></div>
            </div>
            <div class="tab-pane fade" id="pane-blocked" role="tabpanel">
                <div id="blockedList" class="wc-friends-list"></div>
            </div>
        </div>
    </div>
</div>
<?php require __DIR__ . '/includes/layout_end.php'; ?>
