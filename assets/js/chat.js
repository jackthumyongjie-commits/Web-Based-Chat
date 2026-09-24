/**
 * WebConnect — private chat client
 */
(function () {
  const WC = window.WebConnect;
  if (!document.getElementById('chatApp')) return;

  const state = {
    peerId: null,
    peer: null,
    lastId: 0,
    sending: false,
    replyTo: null,
    mediaRecorder: null,
    voiceCapture: null,
    recordedChunks: [],
    recordStart: 0,
    recordTimer: null,
    pollTimer: null,
    typingTimer: null,
  };

  const els = {
    convList: document.getElementById('convList'),
    msgList: document.getElementById('msgList'),
    composer: document.getElementById('composerText'),
    btnSend: document.getElementById('btnSend'),
    chatTitle: document.getElementById('chatTitle'),
    chatSubtitle: document.getElementById('chatSubtitle'),
    typing: document.getElementById('typingIndicator'),
    replyBar: document.getElementById('replyBar'),
    replyText: document.getElementById('replyText'),
    infoPanel: document.getElementById('infoPanel'),
    emptyState: document.getElementById('chatEmpty'),
    chatActive: document.getElementById('chatActive'),
    chatMain: document.getElementById('chatMain'),
    sidebar: document.getElementById('chatSidebar'),
    convSearch: document.getElementById('convSearch'),
    recordBar: document.getElementById('recordBar'),
    recordTimer: document.getElementById('recordTimer'),
  };

  function ticksHtml(status) {
    if (status === 'read') return '<span class="wc-ticks read"><i class="fa-solid fa-check-double"></i> ' + WC.t('js.read') + '</span>';
    if (status === 'delivered') return '<span class="wc-ticks"><i class="fa-solid fa-check-double"></i></span>';
    return '<span class="wc-ticks"><i class="fa-solid fa-check"></i></span>';
  }

  function formatCallDuration(sec) {
    sec = Math.max(0, Math.floor(Number(sec) || 0));
    const h = Math.floor(sec / 3600);
    const m = Math.floor((sec % 3600) / 60);
    const s = sec % 60;
    if (h > 0) {
      return h + ':' + String(m).padStart(2, '0') + ':' + String(s).padStart(2, '0');
    }
    return m + ':' + String(s).padStart(2, '0');
  }

  function renderMessage(m) {
    if (m.hidden) return '';

    // Call log — WhatsApp-style bubble (right = you called, left = they called)
    if (m.message_type === 'call' && !m.is_deleted) {
      const call = m.call || {};
      const mine = call.outgoing || m.is_mine;
      const isVideo = call.call_type === 'video';
      const title = call.title || call.label || (isVideo ? WC.t('js.video_call') : WC.t('js.voice_call'));
      const statusText = call.status_label || '';
      return `<div class="wc-msg ${mine ? 'mine' : 'theirs'} wc-msg-call-bubble" data-id="${m.id}">
        <div class="wc-bubble wc-call-bubble">
          <div class="wc-call-bubble-row">
            <span class="wc-call-icon ${call.status === 'missed' || call.status === 'rejected' ? 'is-missed' : ''}">
              <i class="fa-solid ${isVideo ? 'fa-video' : 'fa-phone'}"></i>
              <i class="fa-solid ${mine ? 'fa-arrow-up' : 'fa-arrow-down'} wc-call-dir"></i>
            </span>
            <div class="wc-call-texts">
              <div class="wc-call-title">${WC.escapeHtml(title)}</div>
              <div class="wc-call-status">${WC.escapeHtml(statusText)}</div>
            </div>
          </div>
        </div>
        <div class="wc-msg-meta">${WC.formatTime(m.created_at)}</div>
      </div>`;
    }

    const mine = m.is_mine ? 'mine' : 'theirs';
    let body = '';
    if (m.is_deleted) {
      body = '<em>' + WC.t('js.message_deleted') + '</em>';
    } else if (m.message_type === 'image' && m.file_url) {
      body = `<a href="${WC.escapeHtml(m.file_url)}" target="_blank" rel="noopener"><img src="${WC.escapeHtml(m.file_url)}" alt="" style="max-width:240px;border-radius:10px;"></a>`;
    } else if (m.message_type === 'file' && m.file_url) {
      body = `<a class="link-dark" href="${WC.escapeHtml(m.file_url)}" target="_blank" rel="noopener"><i class="fa-solid fa-paperclip"></i> ${WC.escapeHtml(m.file_name || 'File')}</a>`;
    } else if (m.message_type === 'voice' && m.file_url) {
      body = `<audio controls preload="auto" playsinline webkit-playsinline src="${WC.escapeHtml(m.file_url)}" style="max-width:min(240px,70vw);"></audio>` +
        (m.voice_duration ? `<div class="small opacity-75">${Number(m.voice_duration).toFixed(1)}s</div>` : '');
    } else {
      body = WC.escapeHtml(m.body || '');
    }

    let reply = '';
    if (m.reply) {
      const rtext = m.reply.is_deleted ? 'Deleted message' : (m.reply.body || m.reply.message_type || '');
      reply = `<div class="wc-reply-preview">${WC.escapeHtml(rtext)}</div>`;
    }

    let reactions = '';
    if (m.reactions && m.reactions.length) {
      reactions = '<div class="wc-reactions">' + m.reactions.map((r) =>
        `<span class="wc-reaction-chip">${r.reaction} ${r.count}</span>`
      ).join('') + '</div>';
    }

    const actions = m.is_deleted ? '' : `
      <div class="wc-msg-actions">
        <button type="button" data-reply="${m.id}" data-preview="${WC.escapeHtml((m.body || m.message_type || '').slice(0, 60))}">${WC.t('js.reply')}</button>
        ${m.is_mine ? `<button type="button" data-delete="${m.id}">${WC.t('js.delete')}</button>` : ''}
      </div>`;

    return `<div class="wc-msg ${mine}" data-id="${m.id}">
      <div class="wc-bubble">${reply}${body}</div>
      ${reactions}
      ${actions}
      <div class="wc-msg-meta">${WC.formatTime(m.created_at)} ${m.is_mine ? ticksHtml(m.delivery_status) : ''}</div>
    </div>`;
  }

  function appendMessages(messages, replace) {
    if (replace) els.msgList.innerHTML = '';
    const fresh = [];
    messages.forEach((m) => {
      if (!m || !m.id) return;
      if (m.id > state.lastId) state.lastId = m.id;
      // Skip if already on screen (send + poll race)
      if (!replace && els.msgList.querySelector('.wc-msg[data-id="' + m.id + '"]')) return;
      fresh.push(m);
    });
    if (!fresh.length && !replace) return;
    const html = (replace ? messages : fresh).map(renderMessage).join('');
    if (replace) {
      els.msgList.innerHTML = html;
    } else if (html) {
      els.msgList.insertAdjacentHTML('beforeend', html);
    }
    els.msgList.scrollTop = els.msgList.scrollHeight;
  }

  async function loadConversations(q) {
    const res = await WC.fetchJSON('chat.php?action=conversations' + (q ? '&q=' + encodeURIComponent(q) : ''));
    const list = res.data.conversations || [];
    if (!list.length) {
      els.convList.innerHTML = '<div class="wc-empty">' + WC.t('js.no_conversations') + '</div>';
      return;
    }
    els.convList.innerHTML = list.map((c) => `
      <div class="wc-conv-item ${state.peerId === c.peer.id ? 'active' : ''}" data-peer="${c.peer.id}">
        <div>
          <img class="wc-avatar-md" src="${WC.escapeHtml(c.peer.avatar_url)}" alt="">
          ${WC.presenceDot(c.peer.presence)}
        </div>
        <div class="wc-conv-meta">
          <div class="name"><span>${WC.escapeHtml(c.peer.username)}</span>
            ${c.unread_count ? `<span class="wc-unread">${c.unread_count}</span>` : ''}
          </div>
          <div class="preview">${WC.escapeHtml(c.last_message.preview || '')}</div>
        </div>
      </div>`).join('');
  }

  async function openPeer(peerId) {
    state.peerId = peerId;
    state.lastId = 0;
    state.replyTo = null;
    els.replyBar.classList.remove('show');
    els.emptyState.classList.add('d-none');
    els.chatActive.classList.remove('d-none');
    els.chatActive.style.display = 'flex';
    if (window.innerWidth <= 768) {
      els.sidebar.classList.add('mobile-hidden');
      els.chatMain.classList.remove('mobile-hidden');
    }
    const res = await WC.fetchJSON('messages.php?action=history&user_id=' + peerId + '&limit=50');
    state.peer = res.data.peer;
    els.chatTitle.textContent = state.peer.username;
    els.chatSubtitle.textContent = (state.peer.presence || 'offline') + (state.peer.status_message ? ' · ' + state.peer.status_message : '');
    appendMessages(res.data.messages || [], true);
    renderInfo();
    bindHeaderCalls();
    loadConversations(els.convSearch.value.trim());
    startPoll();
  }

  function bindHeaderCalls() {
    const voiceBtn = document.getElementById('btnHeaderVoiceCall');
    const videoBtn = document.getElementById('btnHeaderVideoCall');
    if (voiceBtn) {
      voiceBtn.onclick = () => {
        if (!state.peerId) return;
        WC.startCall && WC.startCall(state.peerId, 'voice', state.peer);
      };
    }
    if (videoBtn) {
      videoBtn.onclick = () => {
        if (!state.peerId) return;
        WC.startCall && WC.startCall(state.peerId, 'video', state.peer);
      };
    }
  }

  function renderInfo() {
    if (!state.peer) return;
    els.infoPanel.innerHTML = `
      <img class="wc-avatar-xl mb-3" src="${WC.escapeHtml(state.peer.avatar_url)}" alt="">
      <h5>${WC.escapeHtml(state.peer.username)}</h5>
      <p class="status mb-3">${WC.escapeHtml(state.peer.status_message || WC.t('js.no_status'))}</p>
      <p class="mb-3"><span class="badge text-bg-secondary">${WC.escapeHtml(state.peer.presence)}</span></p>
      <div class="d-grid gap-2">
        <button type="button" class="btn btn-outline-danger" id="btnBlockPeer"><i class="fa-solid fa-ban"></i> ${WC.t('js.block')}</button>
      </div>`;
    document.getElementById('btnBlockPeer').onclick = async () => {
      if (!confirm(WC.t('js.block_confirm'))) return;
      try {
        await WC.fetchJSON('blocks.php', { method: 'POST', body: { action: 'block', user_id: state.peerId } });
        WC.toast(WC.t('js.user_blocked'), 'success');
        location.href = (WC.APP_URL || '') + '/friends.php';
      } catch (e) { WC.toast(e.message, 'error'); }
    };
  }

  async function sendText() {
    const text = els.composer.value.trim();
    if (!text || !state.peerId || state.sending) return;
    state.sending = true;
    try {
      const res = await WC.fetchJSON('messages.php', {
        method: 'POST',
        body: {
          action: 'send',
          user_id: state.peerId,
          message_type: 'text',
          body: text,
          reply_to_id: state.replyTo || 0,
        },
      });
      els.composer.value = '';
      state.replyTo = null;
      els.replyBar.classList.remove('show');
      appendMessages([res.data.message], false);
      loadConversations();
    } catch (e) {
      WC.toast(e.message || WC.t('js.unable_send'), 'error');
    } finally {
      state.sending = false;
    }
  }

  async function sendFile(file, type) {
    if (!state.peerId || !file) return;
    const fd = new FormData();
    fd.append('action', 'send');
    fd.append('csrf_token', WC.CSRF);
    fd.append('user_id', String(state.peerId));
    fd.append('message_type', type);
    fd.append('file', file);
    if (state.replyTo) fd.append('reply_to_id', String(state.replyTo));
    if (type === 'voice' && file._duration) fd.append('voice_duration', String(file._duration));
    try {
      const res = await fetch(WC.apiUrl('messages.php'), {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'X-CSRF-TOKEN': WC.CSRF, 'X-Requested-With': 'XMLHttpRequest' },
        body: fd,
      });
      const data = await res.json();
      if (!data.success) throw new Error(data.message);
      state.replyTo = null;
      els.replyBar.classList.remove('show');
      appendMessages([data.data.message], false);
      loadConversations();
    } catch (e) {
      WC.toast(e.message || WC.t('js.upload_failed'), 'error');
    }
  }

  function startPoll() {
    if (state.pollTimer) clearInterval(state.pollTimer);
    state.pollTimer = setInterval(async () => {
      if (!state.peerId) return;
      try {
        const res = await WC.fetchJSON('messages.php?action=poll&user_id=' + state.peerId + '&after_id=' + state.lastId);
        if (res.data.messages && res.data.messages.length) {
          appendMessages(res.data.messages, false);
          loadConversations();
        }
        if (res.data.statuses) {
          res.data.statuses.forEach((s) => {
            const el = els.msgList.querySelector('.wc-msg[data-id="' + s.id + '"] .wc-msg-meta');
            if (el && el.innerHTML.indexOf('fa-check') >= 0) {
              el.innerHTML = WC.formatTime('') + ' ' + ticksHtml(s.delivery_status);
              // keep time from existing text roughly
            }
          });
        }
        const t = await WC.fetchJSON('chat.php?action=typing_status&user_id=' + state.peerId);
        els.typing.textContent = t.data.typing ? WC.t('js.typing', { name: t.data.username || 'User' }) : '';
        // Refresh peer online/offline (was stuck after first open)
        try {
          const pr = await WC.fetchJSON('presence.php?action=get&ids=' + state.peerId);
          const u = (pr.data.users || [])[0];
          if (u && state.peer) {
            state.peer.presence = u.presence;
            els.chatSubtitle.textContent = (u.presence || 'offline')
              + (state.peer.status_message ? ' · ' + state.peer.status_message : '');
            const badge = els.infoPanel.querySelector('.badge');
            if (badge) badge.textContent = u.presence || 'offline';
          }
        } catch (pe) { /* ignore */ }
      } catch (e) { /* ignore transient */ }
    }, 1000);
  }

  // Events
  els.convList.addEventListener('click', (e) => {
    const item = e.target.closest('[data-peer]');
    if (item) openPeer(Number(item.dataset.peer));
  });

  els.btnSend.addEventListener('click', sendText);
  els.composer.addEventListener('keydown', (e) => {
    if (e.key === 'Enter' && !e.shiftKey) {
      e.preventDefault();
      if (e.isComposing || e.keyCode === 229) return; // IME
      sendText();
    }
    if (state.peerId) {
      clearTimeout(state.typingTimer);
      state.typingTimer = setTimeout(() => {
        WC.fetchJSON('chat.php', { method: 'POST', body: { action: 'typing', user_id: state.peerId } }).catch(() => {});
      }, 200);
    }
  });

  document.getElementById('btnAttachImage').addEventListener('change', (e) => {
    const f = e.target.files[0];
    if (f) sendFile(f, 'image');
    e.target.value = '';
  });
  document.getElementById('btnAttachFile').addEventListener('change', (e) => {
    const f = e.target.files[0];
    if (f) sendFile(f, 'file');
    e.target.value = '';
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

  const emojiPanel = document.getElementById('emojiPanel');
  const btnEmoji = document.getElementById('btnEmoji');
  if (emojiPanel && btnEmoji) {
    emojiPanel.innerHTML = EMOJIS.map((e) =>
      `<button type="button" class="wc-emoji-btn" data-emoji="${e}">${e}</button>`
    ).join('');

    function insertEmoji(emoji) {
      const ta = els.composer;
      const start = ta.selectionStart ?? ta.value.length;
      const end = ta.selectionEnd ?? ta.value.length;
      ta.value = ta.value.slice(0, start) + emoji + ta.value.slice(end);
      const pos = start + emoji.length;
      ta.focus();
      ta.setSelectionRange(pos, pos);
      ta.dispatchEvent(new Event('input'));
    }

    btnEmoji.addEventListener('click', (e) => {
      e.stopPropagation();
      emojiPanel.hidden = !emojiPanel.hidden;
    });

    emojiPanel.addEventListener('click', (e) => {
      const btn = e.target.closest('[data-emoji]');
      if (!btn) return;
      insertEmoji(btn.dataset.emoji);
    });

    document.addEventListener('click', (e) => {
      if (!e.target.closest('.wc-emoji-wrap')) {
        emojiPanel.hidden = true;
      }
    });
  }

  async function openMicStream() {
    return WC.openMicStream();
  }

  document.getElementById('btnStartVoice').addEventListener('click', async () => {
    try {
      if (state.voiceCapture) return;
      state.voiceCapture = await WC.startVoiceCapture();
      state.recordStart = state.voiceCapture.startedAt;
      els.recordBar.classList.add('show');
      state.recordTimer = setInterval(() => {
        const sec = Math.floor((Date.now() - state.recordStart) / 1000);
        els.recordTimer.textContent = String(Math.floor(sec / 60)).padStart(2, '0') + ':' + String(sec % 60).padStart(2, '0');
      }, 250);
    } catch (e) {
      WC.toast(e && e.message ? e.message : WC.t('js.mic_required'), 'error');
    }
  });

  document.getElementById('btnCancelVoice').addEventListener('click', () => {
    if (state.voiceCapture) {
      try { state.voiceCapture.cancel(); } catch (e) { /* ignore */ }
      state.voiceCapture = null;
    }
    if (state.mediaRecorder && state.mediaRecorder.state !== 'inactive') {
      try { state.mediaRecorder.stop(); } catch (e) { /* ignore */ }
    }
    if (state.mediaRecorder && state.mediaRecorder.stream) {
      state.mediaRecorder.stream.getTracks().forEach((t) => t.stop());
    }
    state.mediaRecorder = null;
    state.recordedChunks = [];
    clearInterval(state.recordTimer);
    els.recordBar.classList.remove('show');
  });

  document.getElementById('btnSendVoice').addEventListener('click', async () => {
    if (!state.voiceCapture) return;
    const cap = state.voiceCapture;
    state.voiceCapture = null;
    clearInterval(state.recordTimer);
    els.recordBar.classList.remove('show');
    try {
      const file = await cap.stop();
      await sendFile(file, 'voice');
    } catch (e) {
      WC.toast((e && e.message) ? e.message : WC.t('js.upload_failed'), 'error');
    }
  });

  els.msgList.addEventListener('click', async (e) => {
    const replyBtn = e.target.closest('[data-reply]');
    if (replyBtn) {
      state.replyTo = Number(replyBtn.dataset.reply);
      els.replyText.textContent = replyBtn.dataset.preview || 'Message';
      els.replyBar.classList.add('show');
      return;
    }
    const cancelReply = e.target.closest('#btnCancelReply');
    if (cancelReply) {
      state.replyTo = null;
      els.replyBar.classList.remove('show');
      return;
    }
    const del = e.target.closest('[data-delete]');
    if (del) {
      if (!confirm(WC.t('js.delete_confirm'))) return;
      try {
        await WC.fetchJSON('messages.php', { method: 'POST', body: { action: 'delete', message_id: Number(del.dataset.delete), mode: 'everyone' } });
        const msgEl = els.msgList.querySelector('.wc-msg[data-id="' + del.dataset.delete + '"] .wc-bubble');
        if (msgEl) msgEl.innerHTML = '<em>' + WC.t('js.message_deleted') + '</em>';
      } catch (err) { WC.toast(err.message, 'error'); }
    }
  });

  document.getElementById('btnCancelReply').addEventListener('click', () => {
    state.replyTo = null;
    els.replyBar.classList.remove('show');
  });

  document.getElementById('btnBackToList')?.addEventListener('click', () => {
    els.sidebar.classList.remove('mobile-hidden');
    els.chatMain.classList.add('mobile-hidden');
  });

  let searchTimer;
  els.convSearch.addEventListener('input', () => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => loadConversations(els.convSearch.value.trim()), 300);
  });

  loadConversations().catch(() => {});
  const params = new URLSearchParams(location.search);
  if (params.get('user')) {
    openPeer(Number(params.get('user'))).catch((e) => WC.toast(e.message, 'error'));
  }

  // Expose for friends page deep links
  WC.openChat = openPeer;
})();
