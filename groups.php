<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/bootstrap.php';
$currentUser = require_login();
$pageTitle = __('groups.title');
$activeNav = 'groups';
$extraJs = [asset('js/groups.js')];
require __DIR__ . '/includes/layout_start.php';
?>
<div class="container wc-page py-3">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="wc-page-title mb-0"><?= e(__('groups.title')) ?></h1>
        <button class="btn btn-wc" data-bs-toggle="modal" data-bs-target="#createGroupModal">
            <i class="fa-solid fa-plus"></i> <?= e(__('groups.create')) ?>
        </button>
    </div>
    <div id="groupsList" class="row g-3"></div>
</div>

<div class="modal fade" id="createGroupModal" tabindex="-1">
    <div class="modal-dialog">
        <form class="modal-content" id="createGroupForm">
            <div class="modal-header">
                <h5 class="modal-title"><?= e(__('groups.create')) ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label"><?= e(__('groups.name')) ?></label>
                    <input type="text" class="form-control" name="name" required maxlength="120">
                </div>
                <div class="mb-3">
                    <label class="form-label"><?= e(__('groups.description')) ?></label>
                    <textarea class="form-control" name="description" rows="2" maxlength="500"></textarea>
                </div>
                <div class="mb-2">
                    <label class="form-label"><?= e(__('groups.add_friends')) ?></label>
                    <div id="friendPickList" class="border rounded p-2" style="max-height:200px;overflow:auto;"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><?= e(__('common.cancel')) ?></button>
                <button type="submit" class="btn btn-wc"><?= e(__('groups.create')) ?></button>
            </div>
        </form>
    </div>
</div>
<?php require __DIR__ . '/includes/layout_end.php'; ?>
