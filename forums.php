<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/bootstrap.php';
$currentUser = require_login();
$pageTitle = __('forum.title');
$activeNav = 'forums';
$extraJs = [asset('js/forums.js')];
$postId = (int) ($_GET['post'] ?? 0);
require __DIR__ . '/includes/layout_start.php';
?>
<div class="container wc-page py-3" data-post-id="<?= (int) $postId ?>">
    <h1 class="wc-page-title"><?= e(__('forum.title')) ?></h1>
    <div class="row g-3">
        <div class="col-lg-3">
            <div class="wc-panel p-3">
                <h2 class="h6"><?= e(__('forum.categories')) ?></h2>
                <div id="forumCats"></div>
            </div>
        </div>
        <div class="col-lg-9">
            <div id="forumMain">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h2 class="h5 mb-0" id="forumHeading"><?= e(__('forum.posts')) ?></h2>
                    <button class="btn btn-wc btn-sm" data-bs-toggle="modal" data-bs-target="#newPostModal"><?= e(__('forum.new_post')) ?></button>
                </div>
                <div id="postsList"></div>
            </div>
            <div id="postDetail" class="d-none"></div>
        </div>
    </div>
</div>

<div class="modal fade" id="newPostModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <form class="modal-content" id="newPostForm">
            <div class="modal-header">
                <h5 class="modal-title"><?= e(__('forum.new_post')) ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label"><?= e(__('forum.category')) ?></label>
                    <select class="form-select" name="forum_id" id="newPostForum" required></select>
                </div>
                <div class="mb-3">
                    <label class="form-label"><?= e(__('forum.post_title')) ?></label>
                    <input type="text" class="form-control" name="title" required maxlength="200">
                </div>
                <div class="mb-3">
                    <label class="form-label"><?= e(__('forum.content')) ?></label>
                    <textarea class="form-control" name="content" rows="6" required></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><?= e(__('common.cancel')) ?></button>
                <button type="submit" class="btn btn-wc"><?= e(__('forum.publish')) ?></button>
            </div>
        </form>
    </div>
</div>
<?php require __DIR__ . '/includes/layout_end.php'; ?>
