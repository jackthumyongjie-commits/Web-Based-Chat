/**
 * Group chat client
 */
(function () {
  const WC = window.WebConnect;
  const app = document.getElementById('groupChatApp');
  if (!app) return;

  const groupId = Number(app.dataset.groupId);
  const state = { lastId: 0, replyTo: null, myRole: 'member', voiceCapture: null, recordStart: 0, recordTimer: null, sending: false };
  const msgList = document.getElementById('gMsgList');

  function roleLabel(role) {
    if (role === 'owner') return WC.t('js.owner');
    if (role === 'admin') return WC.t('js.admin');
    return WC.t('js.member');
  }

  function roleBadge(role) {
    const label = WC.escapeHtml(roleLabel(role));
    const cls = role === 'owner' ? 'owner' : (role === 'admin' ? 'admin' : 'member');
    return `<span class="wc-role-badge wc-role-${cls}">${label}</span>`;
  }

  function setSideOpen(open) {
    app.classList.toggle('side-open', !!open);
  }

  document.getElementById('btnGroupInfo')?.addEventListener('click', () => setSideOpen(true));
  document.getElementById('btnCloseGroupInfo')?.addEventListener('click', () => setSideOpen(false));

  function renderMsg(m) {
    const mine = m.is_mine ? 'mine' : 'theirs';
    let body = '';
    if (m.is_deleted) body = '<em>' + WC.t('js.message_deleted') + '</em>';
    else if (m.message_type === 'image' && m.file_url)
      body = `<a href="${WC.escapeHtml(m.file_url)}" target="_blank"><img src="${WC.escapeHtml(m.file_url)}" style="max-width:220px;border-radius:10px;"></a>`;
    else if (m.message_type === 'file' && m.file_url)
      body = `<a href="${WC.escapeHtml(m.file_url)}" target="_blank"><i class="fa-solid fa-paperclip"></i> ${WC.escapeHtml(m.file_name || WC.t('js.file'))}</a>`;
    else if (m.message_type === 'voice' && m.file_url)
      body = `<audio controls preload="auto" playsinline webkit-playsinline src="${WC.escapeHtml(m.file_url)}" style="max-width:220px;"></audio>`;
    else body = WC.escapeHtml(m.body || '');

    const reply = m.reply
      ? `<div class="wc-reply-preview">${WC.escapeHtml(m.reply.is_deleted ? WC.t('js.message_deleted') : (m.reply.body || m.reply.message_type || ''))}</div>`
      : '';
    const reactions = (m.reactions || []).length
      ? '<div class="wc-reactions">' + m.reactions.map((r) =>
        `<button type="button" class="wc-reaction-chip" data-react="${m.id}" data-emoji="${r.reaction}">${r.reaction} ${r.count}</button>`
      ).join('') + '</div>' : '';

    return `<div class="wc-msg ${mine}" data-id="${m.id}">
      ${!m.is_mine ? `<div class="small text-muted mb-1">${WC.escapeHtml(m.sender_username)}</div>` : ''}
      <div class="wc-bubble">${reply}${body}</div>
      ${reactions}
      <div class="wc-msg-actions">
        <button type="button" data-reply="${m.id}" data-preview="${WC.escapeHtml((m.body || m.message_type || '').slice(0, 40))}">${WC.t('js.reply')}</button>
        <button type="button" data-react="${m.id}" data-emoji="👍">👍</button>
        <button type="button" data-react="${m.id}" data-emoji="❤️">❤️</button>
        ${(m.is_mine || state.myRole === 'owner' || state.myRole === 'admin') ? `<button type="button" data-delete="${m.id}">${WC.t('js.delete')}</button>` : ''}
      </div>
      <div class="wc-msg-meta">${WC.formatTime(m.created_at)}</div>
    </div>`;
  }

  async function loadGroup() {
    const res = await WC.fetchJSON('groups.php?action=get&group_id=' + groupId);
    const g = res.data.group;
    state.myRole = g.my_role;
    document.getElementById('groupTitle').textContent = g.name;
    document.getElementById('groupSubtitle').textContent = g.description || '';
    const side = document.getElementById('groupSide');
    const members = res.data.members || [];
    let html = '<h6 class="mb-2">' + WC.t('js.members') + '</h6>';
    html += members.map((m) => `
      <div class="wc-list-item wc-group-member-row px-0">
        <img class="wc-avatar-sm flex-shrink-0" src="${WC.escapeHtml(m.avatar_url)}" alt="">
        <div class="wc-group-member-meta flex-grow-1 min-w-0">
          <div class="wc-group-member-name text-truncate">${WC.escapeHtml(m.username)}</div>
          <div class="wc-group-member-role">${roleBadge(m.role)}</div>
        </div>
        <div class="wc-group-member-actions flex-shrink-0 d-flex align-items-center gap-1">
          ${(state.myRole === 'owner' && m.role !== 'owner') ? `
            <select class="form-select form-select-sm" data-role-user="${m.id}">
              <option value="member" ${m.role === 'member' ? 'selected' : ''}>${WC.t('js.member')}</option>
              <option value="admin" ${m.role === 'admin' ? 'selected' : ''}>${WC.t('js.admin')}</option>
            </select>` : ''}
          ${(state.myRole === 'owner' || (state.myRole === 'admin' && m.role === 'member')) && m.role !== 'owner' && m.id !== WC.USER_ID
            ? `<button type="button" class="btn btn-sm btn-outline-danger" data-remove-member="${m.id}">&times;</button>` : ''}
        </div>
      </div>`).join('');

    if (state.myRole === 'owner' || state.myRole === 'admin') {
      html += `<hr><h6>${WC.t('js.add_friend')}</h6>
        <div class="input-group input-group-sm mb-2">
          <select class="form-select" id="addMemberSelect"></select>
          <button class="btn btn-wc" type="button" id="btnAddMember">${WC.t('js.add_member')}</button>
        </div>
        <div class="mb-2">
          <label class="form-label small">${WC.t('js.group_avatar')}</label>
          <input type="file" id="groupAvatar" class="form-control form-control-sm" accept="image/*">
        </div>`;
    }
    if (state.myRole === 'owner') {
      html += `<button class="btn btn-outline-danger btn-sm w-100 mt-2" id="btnDeleteGroup">${WC.t('js.delete_group')}</button>`;
    } else {
      html += `<button class="btn btn-outline-secondary btn-sm w-100 mt-2" id="btnLeaveGroup">${WC.t('js.leave_group')}</button>`;
    }
    side.innerHTML = html;

    // populate friend select
    const friends = await WC.fetchJSON('friends.php?action=list');
    const memberIds = new Set(members.map((m) => m.id));
    const sel = document.getElementById('addMemberSelect');
    if (sel) {
      sel.innerHTML = (friends.data.friends || [])
        .filter((f) => !memberIds.has(f.id))
        .map((f) => `<option value="${f.id}">${WC.escapeHtml(f.username)}</option>`).join('') || '<option value="">' + WC.t('js.no_friends_add') + '</option>';
    }

    document.getElementById('btnAddMember')?.addEventListener('click', async () => {
      const id = Number(document.getElementById('addMemberSelect').value);
      if (!id) return;
      try {
        await WC.fetchJSON('groups.php', { method: 'POST', body: { action: 'add_member', group_id: groupId, user_id: id } });
        WC.toast(WC.t('js.member_added'), 'success');
        loadGroup();
      } catch (e) { WC.toast(e.message, 'error'); }
    });
    document.getElementById('btnLeaveGroup')?.addEventListener('click', async () => {
      if (!confirm(WC.t('js.leave_group_confirm'))) return;
      await WC.fetchJSON('groups.php', { method: 'POST', body: { action: 'leave', group_id: groupId } });
      location.href = WC.APP_URL + '/groups.php';
    });
    document.getElementById('btnDeleteGroup')?.addEventListener('click', async () => {
      if (!confirm(WC.t('js.delete_group_confirm'))) return;
      await WC.fetchJSON('groups.php', { method: 'POST', body: { action: 'delete', group_id: groupId } });
      location.href = WC.APP_URL + '/groups.php';
    });
    document.getElementById('groupAvatar')?.addEventListener('change', async (e) => {
      const file = e.target.files[0];
      if (!file) return;
      const fd = new FormData();
      fd.append('action', 'upload_avatar');
      fd.append('csrf_token', WC.CSRF);
      fd.append('group_id', String(groupId));
      fd.append('avatar', file);
      const res = await fetch(WC.apiUrl('groups.php'), { method: 'POST', body: fd, credentials: 'same-origin', headers: { 'X-CSRF-TOKEN': WC.CSRF } });
      const data = await res.json();
      if (!data.success) WC.toast(data.message, 'error');
      else WC.toast(WC.t('js.avatar_updated'), 'success');
    });
    side.querySelectorAll('[data-remove-member]').forEach((btn) => {
      btn.addEventListener('click', async () => {
        await WC.fetchJSON('groups.php', { method: 'POST', body: { action: 'remove_member', group_id: groupId, user_id: Number(btn.dataset.removeMember) } });
        loadGroup();
      });
    });
    side.querySelectorAll('[data-role-user]').forEach((selEl) => {
      selEl.addEventListener('change', async () => {
        await WC.fetchJSON('groups.php', {
          method: 'POST',
          body: { action: 'set_role', group_id: groupId, user_id: Number(selEl.dataset.roleUser), role: selEl.value },
        });
        WC.toast(WC.t('js.role_updated'), 'success');
      });
    });
  }

  async function loadHistory() {
    const res = await WC.fetchJSON('groups.php?action=history&group_id=' + groupId + '&limit=50');
    const messages = res.data.messages || [];
    msgList.innerHTML = messages.map(renderMsg).join('');
    messages.forEach((m) => { if (m.id > state.lastId) state.lastId = m.id; });
    msgList.scrollTop = msgList.scrollHeight;
  }

  async function sendText() {
    const text = document.getElementById('gComposer').value.trim();
    if (!text || state.sending) return;
    state.sending = true;
    try {
      const res = await WC.fetchJSON('groups.php', {
        method: 'POST',
        body: { action: 'send', group_id: groupId, message_type: 'text', body: text, reply_to_id: state.replyTo || 0 },
      });
      document.getElementById('gComposer').value = '';
      state.replyTo = null;
      document.getElementById('gReplyBar').classList.remove('show');
      const m = res.data.message;
      if (m && !msgList.querySelector('.wc-msg[data-id="' + m.id + '"]')) {
        msgList.insertAdjacentHTML('beforeend', renderMsg(m));
      }
      if (m && m.id > state.lastId) state.lastId = m.id;
      msgList.scrollTop = msgList.scrollHeight;
    } catch (e) { WC.toast(e.message, 'error'); }
    finally { state.sending = false; }
  }

  async function sendFile(file, type) {
    const fd = new FormData();
    fd.append('action', 'send');
    fd.append('csrf_token', WC.CSRF);
    fd.append('group_id', String(groupId));
    fd.append('message_type', type);
    fd.append('file', file);
    if (state.replyTo) fd.append('reply_to_id', String(state.replyTo));
    if (file._duration) fd.append('voice_duration', String(file._duration));
    const res = await fetch(WC.apiUrl('groups.php'), {
      method: 'POST', credentials: 'same-origin',
      headers: { 'X-CSRF-TOKEN': WC.CSRF }, body: fd,
    });
    const data = await res.json();
    if (!data.success) throw new Error(data.message);
    msgList.insertAdjacentHTML('beforeend', renderMsg(data.data.message));
    state.lastId = Math.max(state.lastId, data.data.message.id);
    msgList.scrollTop = msgList.scrollHeight;
  }

  document.getElementById('gSend').addEventListener('click', sendText);
  document.getElementById('gComposer').addEventListener('keydown', (e) => {
    if (e.key === 'Enter' && !e.shiftKey) {
      e.preventDefault();
      if (e.isComposing || e.keyCode === 229) return;
      sendText();
    }
    WC.fetchJSON('groups.php', { method: 'POST', body: { action: 'typing', group_id: groupId } }).catch(() => {});
  });
  document.getElementById('gImage').addEventListener('change', (e) => { if (e.target.files[0]) sendFile(e.target.files[0], 'image').catch((err) => WC.toast(err.message, 'error')); e.target.value = ''; });
  document.getElementById('gFile').addEventListener('change', (e) => { if (e.target.files[0]) sendFile(e.target.files[0], 'file').catch((err) => WC.toast(err.message, 'error')); e.target.value = ''; });

  document.getElementById('gStartVoice').addEventListener('click', async () => {
    try {
      if (state.voiceCapture) return;
      state.voiceCapture = await WC.startVoiceCapture();
      state.recordStart = state.voiceCapture.startedAt;
      document.getElementById('gRecordBar').classList.add('show');
      state.recordTimer = setInterval(() => {
        const sec = Math.floor((Date.now() - state.recordStart) / 1000);
        document.getElementById('gRecordTimer').textContent =
          String(Math.floor(sec / 60)).padStart(2, '0') + ':' + String(sec % 60).padStart(2, '0');
      }, 250);
    } catch (e) {
      WC.toast((e && e.message) ? e.message : WC.t('js.mic_required'), 'error');
    }
  });
  document.getElementById('gCancelVoice').addEventListener('click', () => {
    if (state.voiceCapture) {
      try { state.voiceCapture.cancel(); } catch (e) { /* ignore */ }
      state.voiceCapture = null;
    }
    clearInterval(state.recordTimer);
    document.getElementById('gRecordBar').classList.remove('show');
  });
  document.getElementById('gSendVoice').addEventListener('click', async () => {
    if (!state.voiceCapture) return;
    const cap = state.voiceCapture;
    state.voiceCapture = null;
    clearInterval(state.recordTimer);
    document.getElementById('gRecordBar').classList.remove('show');
    try {
      const file = await cap.stop();
      await sendFile(file, 'voice');
    } catch (e) {
      WC.toast((e && e.message) ? e.message : WC.t('js.upload_failed'), 'error');
    }
  });

  msgList.addEventListener('click', async (e) => {
    const reply = e.target.closest('[data-reply]');
    if (reply) {
      state.replyTo = Number(reply.dataset.reply);
      document.getElementById('gReplyText').textContent = reply.dataset.preview || '';
      document.getElementById('gReplyBar').classList.add('show');
      return;
    }
    const react = e.target.closest('[data-react]');
    if (react) {
      await WC.fetchJSON('reactions.php', {
        method: 'POST',
        body: { action: 'toggle', scope: 'group', message_id: Number(react.dataset.react), reaction: react.dataset.emoji },
      });
      await loadHistory();
      return;
    }
    const del = e.target.closest('[data-delete]');
    if (del) {
      if (!confirm(WC.t('js.delete_confirm'))) return;
      await WC.fetchJSON('groups.php', { method: 'POST', body: { action: 'delete_message', message_id: Number(del.dataset.delete) } });
      await loadHistory();
    }
  });
  document.getElementById('gCancelReply').addEventListener('click', () => {
    state.replyTo = null;
    document.getElementById('gReplyBar').classList.remove('show');
  });

  const EMOJIS = [
    '😀', '😁', '😂', '🤣', '😃', '😄', '😅', '😆', '😉', '😊',
    '😋', '😎', '😍', '😘', '🥰', '😗', '😙', '😚', '🙂', '🤗',
    '🤔', '🤨', '😐', '😑', '😶', '🙄', '😏', '😣', '😥', '😮',
    '🤐', '😯', '😪', '😫', '🥱', '😴', '😌', '😛', '😜', '😝',
    '🤤', '😒', '😓', '😔', '😕', '🙃', '🤑', '😲', '☹️', '🙁',
    '😖', '😞', '😟', '😤', '😢', '😭', '😦', '😧', '😨', '😩',
    '🤯', '😬', '😰', '😱', '🥵', '🥶', '😳', '🤪', '😵', '😡',
    '😠', '🤬', '😷', '🤒', '🤕', '🤢', '🤮', '🥴', '😇', '🥳',
    '🥺', '🤠', '🤡', '🤥', '🤫', '🤭', '🧐', '🤓', '😈', '👿',
    '👍', '👎', '👏', '🙌', '👋', '🤝', '🙏', '💪', '✌️', '🤞',
    '❤️', '🧡', '💛', '💚', '💙', '💜', '🖤', '🤍', '💔', '💕',
    '🔥', '⭐', '✨', '🎉', '🎊', '💯', '✅', '❌', '💤', '🎵',
  ];

  const gComposer = document.getElementById('gComposer');
  const gEmojiPanel = document.getElementById('gEmojiPanel');
  const gBtnEmoji = document.getElementById('gBtnEmoji');
  if (gEmojiPanel && gBtnEmoji && gComposer) {
    gEmojiPanel.innerHTML = EMOJIS.map((e) =>
      `<button type="button" class="wc-emoji-btn" data-emoji="${e}">${e}</button>`
    ).join('');

    function insertEmoji(emoji) {
      const start = gComposer.selectionStart ?? gComposer.value.length;
      const end = gComposer.selectionEnd ?? gComposer.value.length;
      gComposer.value = gComposer.value.slice(0, start) + emoji + gComposer.value.slice(end);
      const pos = start + emoji.length;
      gComposer.focus();
      try { gComposer.setSelectionRange(pos, pos); } catch (err) { /* ignore */ }
    }

    gBtnEmoji.addEventListener('click', (e) => {
      e.preventDefault();
      e.stopPropagation();
      gEmojiPanel.hidden = !gEmojiPanel.hidden;
    });
    gEmojiPanel.addEventListener('click', (e) => {
      const btn = e.target.closest('[data-emoji]');
      if (!btn) return;
      insertEmoji(btn.dataset.emoji);
    });
    document.addEventListener('click', (e) => {
      if (!e.target.closest('.wc-emoji-wrap')) {
        gEmojiPanel.hidden = true;
      }
    });
  }

  setInterval(async () => {
    try {
      const res = await WC.fetchJSON('groups.php?action=history&group_id=' + groupId + '&after_id=' + state.lastId);
      const messages = res.data.messages || [];
      if (messages.length) {
        messages.forEach((m) => {
          if (m.id > state.lastId) state.lastId = m.id;
          if (msgList.querySelector('.wc-msg[data-id="' + m.id + '"]')) return;
          msgList.insertAdjacentHTML('beforeend', renderMsg(m));
        });
        msgList.scrollTop = msgList.scrollHeight;
      }
      const t = await WC.fetchJSON('groups.php?action=typing_status&group_id=' + groupId);
      const names = t.data.typing || [];
      document.getElementById('gTyping').textContent = names.length === 1
        ? WC.t('js.typing', { name: names[0] })
        : names.length > 1 ? WC.t('js.typing_many', { names: names.join(', ') }) : '';
    } catch (e) {}
  }, 1000);

  Promise.all([loadGroup(), loadHistory()]).catch((e) => WC.toast(e.message, 'error'));
})();
