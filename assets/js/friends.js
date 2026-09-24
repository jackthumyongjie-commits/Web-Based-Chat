/**
 * Friends page client
 */
(function () {
  const WC = window.WebConnect;
  if (!document.getElementById('friendsList')) return;

  function setCount(id, n) {
    const el = document.getElementById(id);
    if (!el) return;
    el.textContent = String(n);
    el.classList.toggle('is-zero', n === 0);
  }

  function presenceLabel(p) {
    return WC.t('js.' + (p || 'offline'));
  }

  function emptyHtml(text) {
    return `<div class="wc-friends-empty">
      <i class="fa-regular fa-folder-open"></i>
      <p>${WC.escapeHtml(text)}</p>
    </div>`;
  }

  function personRow(opts) {
    const {
      avatar, name, subtitle, presence, actionsHtml,
    } = opts;
    return `<div class="wc-friend-row">
      <div class="wc-friend-avatar-wrap">
        <img class="wc-avatar-md" src="${WC.escapeHtml(avatar)}" alt="">
        ${presence ? WC.presenceDot(presence) : ''}
      </div>
      <div class="wc-friend-meta">
        <div class="wc-friend-name">${WC.escapeHtml(name)}</div>
        ${subtitle ? `<div class="wc-friend-sub">${WC.escapeHtml(subtitle)}</div>` : ''}
      </div>
      <div class="wc-friend-actions">${actionsHtml}</div>
    </div>`;
  }

  async function loadFriends() {
    const res = await WC.fetchJSON('friends.php?action=list');
    const box = document.getElementById('friendsList');
    const friends = res.data.friends || [];
    setCount('countFriends', friends.length);
    if (!friends.length) {
      box.innerHTML = emptyHtml(WC.t('js.no_friends'));
      return;
    }
    box.innerHTML = friends.map((f) => personRow({
      avatar: f.avatar_url,
      name: f.username,
      subtitle: f.status_message || presenceLabel(f.presence),
      presence: f.presence,
      actionsHtml: `
        <a class="btn btn-sm btn-wc" href="${WC.APP_URL}/chat.php?user=${f.id}" title="${WC.t('nav.chat') || 'Chat'}">
          <i class="fa-regular fa-comment"></i>
        </a>
        <button class="btn btn-sm btn-outline-secondary" data-remove="${f.id}" title="${WC.t('js.remove')}">
          <i class="fa-solid fa-user-minus"></i>
        </button>
        <button class="btn btn-sm btn-outline-danger" data-block="${f.id}" title="${WC.t('js.block')}">
          <i class="fa-solid fa-ban"></i>
        </button>`,
    })).join('');
  }

  async function loadRequests() {
    const res = await WC.fetchJSON('friends.php?action=requests');
    const inc = document.getElementById('incomingRequests');
    const out = document.getElementById('outgoingRequests');
    const incoming = res.data.incoming || [];
    const outgoing = res.data.outgoing || [];
    setCount('countIncoming', incoming.length);
    setCount('countOutgoing', outgoing.length);

    inc.innerHTML = incoming.length ? incoming.map((r) => personRow({
      avatar: r.user.avatar_url,
      name: r.user.username,
      subtitle: '',
      presence: r.user.presence,
      actionsHtml: `
        <button class="btn btn-sm btn-success" data-accept="${r.id}">${WC.t('js.accept_req')}</button>
        <button class="btn btn-sm btn-outline-secondary" data-reject="${r.id}">${WC.t('js.reject_req')}</button>`,
    })).join('') : emptyHtml(WC.t('js.no_incoming'));

    out.innerHTML = outgoing.length ? outgoing.map((r) => personRow({
      avatar: r.user.avatar_url,
      name: r.user.username,
      subtitle: '',
      presence: r.user.presence,
      actionsHtml: `
        <button class="btn btn-sm btn-outline-danger" data-cancel="${r.id}">${WC.t('js.cancel_req')}</button>`,
    })).join('') : emptyHtml(WC.t('js.no_outgoing'));
  }

  async function loadBlocks() {
    const res = await WC.fetchJSON('blocks.php?action=list');
    const box = document.getElementById('blockedList');
    const blocks = res.data.blocks || [];
    setCount('countBlocked', blocks.length);
    box.innerHTML = blocks.length ? blocks.map((b) => personRow({
      avatar: b.user.avatar_url,
      name: b.user.username,
      subtitle: '',
      presence: null,
      actionsHtml: `
        <button class="btn btn-sm btn-outline-secondary" data-unblock="${b.user.id}">${WC.t('js.unblock')}</button>`,
    })).join('') : emptyHtml(WC.t('js.nobody_blocked'));
  }

  async function search() {
    const q = document.getElementById('friendSearch').value.trim();
    const box = document.getElementById('searchResults');
    if (q.length < 2) {
      box.classList.add('d-none');
      box.innerHTML = '';
      return;
    }
    try {
      const res = await WC.fetchJSON('friends.php?action=search&q=' + encodeURIComponent(q));
      const users = res.data.users || [];
      box.classList.remove('d-none');
      if (!users.length) {
        box.innerHTML = emptyHtml(WC.t('js.no_users_found'));
        return;
      }
      box.innerHTML = users.map((u) => {
        let action = '';
        if (u.is_friend) action = '<span class="badge text-bg-success">' + WC.t('js.friends_badge') + '</span>';
        else if (u.is_blocked) action = '<span class="badge text-bg-secondary">' + WC.t('js.blocked_badge') + '</span>';
        else if (u.request_status === 'pending' && u.request_direction === 'outgoing')
          action = `<button class="btn btn-sm btn-outline-danger" data-cancel="${u.request_id}">${WC.t('js.cancel_req')}</button>`;
        else if (u.request_status === 'pending' && u.request_direction === 'incoming')
          action = `<button class="btn btn-sm btn-success" data-accept="${u.request_id}">${WC.t('js.accept_req')}</button>`;
        else action = `<button class="btn btn-sm btn-wc" data-add="${u.id}"><i class="fa-solid fa-user-plus me-1"></i>${WC.t('js.add')}</button>`;
        return personRow({
          avatar: u.avatar_url,
          name: u.username,
          subtitle: u.email || '',
          presence: u.presence,
          actionsHtml: action,
        });
      }).join('');
    } catch (e) { WC.toast(e.message, 'error'); }
  }

  async function refreshAll() {
    await Promise.all([loadFriends(), loadRequests(), loadBlocks()]);
    const q = document.getElementById('friendSearch').value.trim();
    if (q.length >= 2) await search();
  }

  document.getElementById('btnFriendSearch').addEventListener('click', search);
  document.getElementById('friendSearch').addEventListener('keydown', (e) => {
    if (e.key === 'Enter') {
      e.preventDefault();
      search();
    }
  });

  let searchTimer;
  document.getElementById('friendSearch').addEventListener('input', () => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(search, 350);
  });

  document.body.addEventListener('click', async (e) => {
    const t = e.target.closest('[data-add],[data-accept],[data-reject],[data-cancel],[data-remove],[data-block],[data-unblock]');
    if (!t) return;
    try {
      if (t.dataset.add) {
        await WC.fetchJSON('friends.php', { method: 'POST', body: { action: 'send_request', user_id: Number(t.dataset.add) } });
        WC.toast(WC.t('js.request_sent'), 'success');
      } else if (t.dataset.accept) {
        await WC.fetchJSON('friends.php', { method: 'POST', body: { action: 'accept', request_id: Number(t.dataset.accept) } });
        WC.toast(WC.t('js.accepted'), 'success');
      } else if (t.dataset.reject) {
        await WC.fetchJSON('friends.php', { method: 'POST', body: { action: 'reject', request_id: Number(t.dataset.reject) } });
      } else if (t.dataset.cancel) {
        await WC.fetchJSON('friends.php', { method: 'POST', body: { action: 'cancel', request_id: Number(t.dataset.cancel) } });
      } else if (t.dataset.remove) {
        if (!confirm(WC.t('js.remove_friend_confirm'))) return;
        await WC.fetchJSON('friends.php', { method: 'POST', body: { action: 'remove', user_id: Number(t.dataset.remove) } });
      } else if (t.dataset.block) {
        if (!confirm(WC.t('js.block_confirm'))) return;
        await WC.fetchJSON('blocks.php', { method: 'POST', body: { action: 'block', user_id: Number(t.dataset.block) } });
      } else if (t.dataset.unblock) {
        await WC.fetchJSON('blocks.php', { method: 'POST', body: { action: 'unblock', user_id: Number(t.dataset.unblock) } });
      }
      await refreshAll();
    } catch (err) { WC.toast(err.message, 'error'); }
  });

  refreshAll().catch((e) => WC.toast(e.message, 'error'));
})();
