(function () {
  const WC = window.WebConnect;
  if (!document.getElementById('forumCats')) return;

  let currentForum = 0;
  const deepPost = Number(document.querySelector('[data-post-id]')?.dataset.postId || 0);

  async function loadCats() {
    const res = await WC.fetchJSON('forums.php?action=categories');
    const forums = res.data.forums || [];
    document.getElementById('forumCats').innerHTML =
      `<button class="btn btn-sm btn-outline-secondary w-100 mb-1 ${currentForum === 0 ? 'active' : ''}" data-forum="0">${WC.t('js.all')}</button>` +
      forums.map((f) => `
        <button class="btn btn-sm btn-outline-secondary w-100 mb-1 text-start ${currentForum === f.id ? 'active' : ''}" data-forum="${f.id}">
          <div class="fw-semibold">${WC.escapeHtml(f.name)}</div>
          <div class="small text-muted">${WC.t('js.posts_count', { count: f.post_count })}</div>
        </button>`).join('');
    document.getElementById('newPostForum').innerHTML = forums.map((f) =>
      `<option value="${f.id}">${WC.escapeHtml(f.name)}</option>`
    ).join('');
  }

  function postActionsHtml(p) {
    return `<div class="wc-post-actions d-flex gap-3 small">
      <button type="button" class="wc-post-action ${p.liked ? 'is-liked' : ''}" data-like="${p.id}" title="${WC.escapeHtml(WC.t('js.like'))}">
        <i class="${p.liked ? 'fa-solid' : 'fa-regular'} fa-heart"></i>
        <span data-like-count="${p.id}">${p.like_count}</span>
      </button>
      <button type="button" class="wc-post-action" data-open-post="${p.id}" data-focus-comment="1" title="${WC.escapeHtml(WC.t('js.comment'))}">
        <i class="fa-regular fa-comment"></i>
        <span data-comment-count="${p.id}">${p.comment_count}</span>
      </button>
      <button type="button" class="wc-post-action" data-share="${p.id}" title="${WC.escapeHtml(WC.t('js.share'))}">
        <i class="fa-solid fa-share-nodes"></i>
        <span data-share-count="${p.id}">${p.share_count}</span>
      </button>
    </div>`;
  }

  async function toggleLike(postId) {
    try {
      const r = await WC.fetchJSON('forums.php', { method: 'POST', body: { action: 'like', post_id: postId } });
      const liked = !!r.data.liked;
      const count = r.data.like_count;
      document.querySelectorAll('[data-like="' + postId + '"]').forEach((el) => {
        el.classList.toggle('is-liked', liked);
        const icon = el.querySelector('i');
        if (icon) {
          icon.classList.toggle('fa-solid', liked);
          icon.classList.toggle('fa-regular', !liked);
        }
      });
      document.querySelectorAll('[data-like-count="' + postId + '"]').forEach((el) => {
        el.textContent = String(count);
      });
      const detailBtn = document.getElementById('btnLike');
      if (detailBtn) {
        detailBtn.classList.toggle('btn-wc', liked);
        detailBtn.classList.toggle('btn-outline-secondary', !liked);
        const icon = detailBtn.querySelector('i');
        if (icon) {
          icon.classList.toggle('fa-solid', liked);
          icon.classList.toggle('fa-regular', !liked);
        }
      }
    } catch (err) {
      WC.toast(err.message, 'error');
    }
  }

  async function sharePost(postId) {
    try {
      const r = await WC.fetchJSON('forums.php', { method: 'POST', body: { action: 'share', post_id: postId } });
      document.querySelectorAll('[data-share-count="' + postId + '"]').forEach((el) => {
        el.textContent = String(r.data.share_count);
      });
      try { await navigator.clipboard.writeText(r.data.share_url); } catch (e) {}
      WC.toast(WC.t('js.share_copied'), 'success');
    } catch (err) {
      WC.toast(err.message, 'error');
    }
  }

  async function loadPosts() {
    const res = await WC.fetchJSON('forums.php?action=posts&forum_id=' + currentForum);
    const posts = res.data.posts || [];
    const box = document.getElementById('postsList');
    if (!posts.length) {
      box.innerHTML = '<div class="wc-panel wc-empty">' + WC.t('js.no_posts') + '</div>';
      return;
    }
    box.innerHTML = posts.map((p) => `
      <div class="wc-panel wc-post-card" data-post-card="${p.id}">
        <h4><a href="#" data-open-post="${p.id}" class="text-decoration-none text-dark">${WC.escapeHtml(p.title)}</a></h4>
        <div class="small text-muted mb-2">${WC.t('js.by')} ${WC.escapeHtml(p.author.username)} · ${WC.formatTime(p.created_at)}
          ${p.is_pinned ? ' · ' + WC.t('js.pinned') : ''}</div>
        <p class="mb-2">${WC.escapeHtml((p.content || '').slice(0, 180))}${(p.content || '').length > 180 ? '…' : ''}</p>
        ${postActionsHtml(p)}
      </div>`).join('');
  }

  async function openPost(id, focusComment) {
    try {
      const res = await WC.fetchJSON('forums.php?action=get_post&post_id=' + id);
      const p = res.data.post;
      const comments = res.data.comments || [];
      document.getElementById('forumMain').classList.add('d-none');
      const detail = document.getElementById('postDetail');
      detail.classList.remove('d-none');
      detail.innerHTML = `
        <button class="btn btn-sm btn-outline-secondary mb-3" id="btnBackPosts"><i class="fa-solid fa-arrow-left"></i> ${WC.t('js.back')}</button>
        <div class="wc-panel p-3 mb-3">
          <h2 class="h4">${WC.escapeHtml(p.title)}</h2>
          <div class="small text-muted mb-3">${WC.t('js.by')} ${WC.escapeHtml(p.author.username)} · ${WC.formatTime(p.created_at)}</div>
          <div class="mb-3" style="white-space:pre-wrap;">${WC.escapeHtml(p.content)}</div>
          <div class="d-flex gap-2 flex-wrap">
            <button class="btn btn-sm ${p.liked ? 'btn-wc' : 'btn-outline-secondary'}" id="btnLike" data-id="${p.id}">
              <i class="${p.liked ? 'fa-solid' : 'fa-regular'} fa-heart"></i> <span id="likeCount" data-like-count="${p.id}">${p.like_count}</span>
            </button>
            <button class="btn btn-sm btn-outline-secondary" id="btnShare" data-id="${p.id}"><i class="fa-solid fa-share-nodes"></i> ${WC.t('js.share')}</button>
          </div>
        </div>
        <div class="wc-panel p-3" id="commentsPanel">
          <h3 class="h6">${WC.t('js.comments')}</h3>
          <div id="commentList">${comments.map((c) => `
            <div class="border-bottom py-2">
              <div class="fw-semibold small">${WC.escapeHtml(c.author.username)}</div>
              <div>${WC.escapeHtml(c.content)}</div>
              <div class="small text-muted">${WC.formatTime(c.created_at)}</div>
            </div>`).join('') || '<div class="text-muted small">' + WC.t('js.no_comments') + '</div>'}
          </div>
          ${p.is_locked ? '<div class="alert alert-secondary mt-2 mb-0">' + WC.t('js.post_locked') + '</div>' : `
          <form id="commentForm" class="mt-3">
            <textarea class="form-control mb-2" name="content" rows="3" required placeholder="${WC.escapeHtml(WC.t('js.write_comment'))}"></textarea>
            <button class="btn btn-wc btn-sm" type="submit">${WC.t('js.comment')}</button>
          </form>`}
        </div>`;

      document.getElementById('btnBackPosts').onclick = () => {
        detail.classList.add('d-none');
        document.getElementById('forumMain').classList.remove('d-none');
        history.replaceState({}, '', WC.APP_URL + '/forums.php');
        loadPosts().catch(() => {});
      };
      document.getElementById('btnLike')?.addEventListener('click', () => toggleLike(id));
      document.getElementById('btnShare')?.addEventListener('click', () => sharePost(id));
      document.getElementById('commentForm')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const content = String(new FormData(e.target).get('content') || '').trim();
        if (!content) return;
        try {
          await WC.fetchJSON('forums.php', { method: 'POST', body: { action: 'comment', post_id: id, content } });
          await openPost(id, true);
        } catch (err) {
          WC.toast(err.message, 'error');
        }
      });
      history.replaceState({}, '', WC.APP_URL + '/forums.php?post=' + id);

      if (focusComment) {
        const panel = document.getElementById('commentsPanel');
        const ta = document.querySelector('#commentForm textarea');
        panel?.scrollIntoView({ behavior: 'smooth', block: 'start' });
        ta?.focus();
      }
    } catch (err) {
      WC.toast(err.message, 'error');
    }
  }

  document.getElementById('forumCats').addEventListener('click', (e) => {
    const btn = e.target.closest('[data-forum]');
    if (!btn) return;
    currentForum = Number(btn.dataset.forum);
    loadCats();
    loadPosts();
  });

  document.getElementById('postsList').addEventListener('click', (e) => {
    const likeBtn = e.target.closest('[data-like]');
    if (likeBtn) {
      e.preventDefault();
      toggleLike(Number(likeBtn.dataset.like));
      return;
    }
    const shareBtn = e.target.closest('[data-share]');
    if (shareBtn) {
      e.preventDefault();
      sharePost(Number(shareBtn.dataset.share));
      return;
    }
    const a = e.target.closest('[data-open-post]');
    if (!a) return;
    e.preventDefault();
    openPost(Number(a.dataset.openPost), a.dataset.focusComment === '1');
  });

  document.getElementById('newPostForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const fd = new FormData(e.target);
    try {
      const res = await WC.fetchJSON('forums.php', {
        method: 'POST',
        body: {
          action: 'create_post',
          forum_id: Number(fd.get('forum_id')),
          title: fd.get('title'),
          content: fd.get('content'),
        },
      });
      bootstrap.Modal.getInstance(document.getElementById('newPostModal'))?.hide();
      e.target.reset();
      await loadPosts();
      openPost(res.data.post_id);
    } catch (err) { WC.toast(err.message, 'error'); }
  });

  Promise.all([loadCats(), loadPosts()]).then(() => {
    if (deepPost) openPost(deepPost);
  }).catch((e) => WC.toast(e.message, 'error'));
})();
