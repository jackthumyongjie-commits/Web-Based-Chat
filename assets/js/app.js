/**
 * WebConnect — shared client utilities
 */
(function () {
  const WC = window.WebConnect || (window.WebConnect = {});

  /** Translate key with optional {placeholders} */
  WC.t = function (key, replace) {
    let text = (WC.I18N && WC.I18N[key]) || key;
    if (replace) {
      Object.keys(replace).forEach((k) => {
        text = text.replace(new RegExp('\\{' + k + '\\}', 'g'), String(replace[k]));
      });
    }
    return text;
  };

  WC.apiUrl = function (endpoint, params) {
    const base = (WC.APP_URL || '').replace(/\/$/, '');
    const url = new URL(base + '/api/' + endpoint.replace(/^\//, ''));
    if (params) {
      Object.keys(params).forEach((k) => {
        if (params[k] !== undefined && params[k] !== null) {
          url.searchParams.set(k, params[k]);
        }
      });
    }
    return url.toString();
  };

  WC.fetchJSON = async function (endpoint, options = {}) {
    const opts = Object.assign({ credentials: 'same-origin' }, options);
    opts.headers = Object.assign({}, opts.headers || {});
    if (!(opts.body instanceof FormData)) {
      opts.headers['Content-Type'] = opts.headers['Content-Type'] || 'application/json';
      opts.headers['Accept'] = 'application/json';
    }
    opts.headers['X-CSRF-TOKEN'] = WC.CSRF;
    opts.headers['X-Requested-With'] = 'XMLHttpRequest';

    if (opts.body && typeof opts.body === 'object' && !(opts.body instanceof FormData)) {
      if (!opts.body.csrf_token) opts.body.csrf_token = WC.CSRF;
      opts.body = JSON.stringify(opts.body);
    }

    const res = await fetch(endpoint.startsWith('http') ? endpoint : WC.apiUrl(endpoint), opts);
    let data;
    try {
      data = await res.json();
    } catch (e) {
      throw new Error(WC.t('js.invalid_response'));
    }
    if (!res.ok || data.success === false) {
      const err = new Error(data.message || WC.t('js.request_failed'));
      err.status = res.status;
      err.data = data;
      throw err;
    }
    return data;
  };

  WC.toast = function (message, type = 'info') {
    let box = document.getElementById('wcToastBox');
    if (!box) {
      box = document.createElement('div');
      box.id = 'wcToastBox';
      box.style.cssText = 'position:fixed;right:16px;bottom:16px;z-index:3000;display:flex;flex-direction:column;gap:8px;';
      document.body.appendChild(box);
    }
    const el = document.createElement('div');
    el.className = 'alert alert-' + (type === 'error' ? 'danger' : type === 'success' ? 'success' : 'secondary') + ' shadow-sm mb-0';
    el.style.minWidth = '220px';
    el.textContent = message;
    box.appendChild(el);
    setTimeout(() => el.remove(), 3200);
  };

  WC.escapeHtml = function (str) {
    return String(str ?? '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  };

  WC.formatTime = function (iso) {
    if (!iso) return '';
    const d = new Date(iso.replace(' ', 'T'));
    if (Number.isNaN(d.getTime())) return '';
    return d.toLocaleString([], { hour: '2-digit', minute: '2-digit', month: 'short', day: 'numeric' });
  };

  WC.presenceDot = function (presence) {
    return '<span class="wc-presence ' + WC.escapeHtml(presence || 'offline') + '" title="' + WC.escapeHtml(presence || 'offline') + '"></span>';
  };

  /**
   * Cross-browser voice note recorder → WAV (plays on Chrome + Safari/iPhone).
   * MediaRecorder webm/opus often arrives with "no sound" on iOS.
   */
  WC.openMicStream = async function () {
    if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
      throw new Error(WC.t('js.media_insecure'));
    }
    const attempts = [
      { audio: { echoCancellation: true, noiseSuppression: true, autoGainControl: true } },
      { audio: true },
    ];
    try {
      const devices = await navigator.mediaDevices.enumerateDevices();
      devices.filter((d) => d.kind === 'audioinput' && d.deviceId).slice(0, 2)
        .forEach((d) => attempts.push({ audio: { deviceId: { exact: d.deviceId } } }));
    } catch (e) { /* ignore */ }

    let lastErr = null;
    for (const c of attempts) {
      try {
        return await navigator.mediaDevices.getUserMedia(c);
      } catch (err) {
        lastErr = err;
      }
    }
    const name = lastErr && lastErr.name ? lastErr.name : '';
    if (name === 'NotAllowedError' || name === 'PermissionDeniedError') {
      throw new Error(WC.t('js.media_mic_denied'));
    }
    if (name === 'NotFoundError' || name === 'DevicesNotFoundError') {
      throw new Error(WC.t('js.media_no_mic'));
    }
    if (name === 'SecurityError' || name === 'NotSupportedError') {
      throw new Error(WC.t('js.media_insecure'));
    }
    throw new Error(WC.t('js.media_mic_busy'));
  };

  function encodeWav(floatChunks, sampleRate) {
    let len = 0;
    floatChunks.forEach((c) => { len += c.length; });
    const samples = new Float32Array(len);
    let off = 0;
    floatChunks.forEach((c) => { samples.set(c, off); off += c.length; });

    const buffer = new ArrayBuffer(44 + samples.length * 2);
    const view = new DataView(buffer);
    const writeStr = (o, s) => { for (let i = 0; i < s.length; i++) view.setUint8(o + i, s.charCodeAt(i)); };
    writeStr(0, 'RIFF');
    view.setUint32(4, 36 + samples.length * 2, true);
    writeStr(8, 'WAVE');
    writeStr(12, 'fmt ');
    view.setUint32(16, 16, true);
    view.setUint16(20, 1, true);
    view.setUint16(22, 1, true);
    view.setUint32(24, sampleRate, true);
    view.setUint32(28, sampleRate * 2, true);
    view.setUint16(32, 2, true);
    view.setUint16(34, 16, true);
    writeStr(36, 'data');
    view.setUint32(40, samples.length * 2, true);
    let idx = 44;
    for (let i = 0; i < samples.length; i++, idx += 2) {
      const s = Math.max(-1, Math.min(1, samples[i]));
      view.setInt16(idx, s < 0 ? s * 0x8000 : s * 0x7fff, true);
    }
    return new Blob([buffer], { type: 'audio/wav' });
  }

  /** Start mic → WAV voice capture. Returns { stop(): Promise<File>, cancel() } */
  WC.startVoiceCapture = async function () {
    const stream = await WC.openMicStream();
    const AC = window.AudioContext || window.webkitAudioContext;
    const ctx = new AC();
    if (ctx.state === 'suspended') {
      try { await ctx.resume(); } catch (e) { /* ignore */ }
    }
    const source = ctx.createMediaStreamSource(stream);
    const gain = ctx.createGain();
    gain.gain.value = 0; // no local monitor / feedback
    const processor = ctx.createScriptProcessor(4096, 1, 1);
    const chunks = [];
    processor.onaudioprocess = (ev) => {
      chunks.push(new Float32Array(ev.inputBuffer.getChannelData(0)));
    };
    source.connect(processor);
    processor.connect(gain);
    gain.connect(ctx.destination);
    const startedAt = Date.now();

    const cleanup = () => {
      try { processor.disconnect(); } catch (e) { /* ignore */ }
      try { source.disconnect(); } catch (e) { /* ignore */ }
      try { gain.disconnect(); } catch (e) { /* ignore */ }
      stream.getTracks().forEach((t) => { try { t.stop(); } catch (e) { /* ignore */ } });
      try { ctx.close(); } catch (e) { /* ignore */ }
    };

    return {
      startedAt,
      cancel() { cleanup(); },
      async stop() {
        const duration = Math.max(0.5, (Date.now() - startedAt) / 1000);
        const blob = encodeWav(chunks, ctx.sampleRate || 44100);
        cleanup();
        if (!blob.size || blob.size < 100) {
          throw new Error(WC.t('js.voice_empty'));
        }
        const file = new File([blob], 'voice-' + Date.now() + '.wav', { type: 'audio/wav' });
        file._duration = duration;
        return file;
      },
    };
  };

  // Presence heartbeat
  if (WC.USER_ID) {
    const beat = () => {
      WC.fetchJSON('presence.php?action=heartbeat', {
        method: 'POST',
        body: { action: 'heartbeat', presence: 'online', csrf_token: WC.CSRF },
      }).catch(() => {});
    };
    beat();
    setInterval(beat, 25000);

    const refreshNotif = () => {
      WC.fetchJSON('notifications.php?action=unread_count')
        .then((res) => {
          const badge = document.getElementById('navNotifBadge');
          if (!badge) return;
          const c = res.data.count || 0;
          if (c > 0) {
            badge.textContent = c > 99 ? '99+' : String(c);
            badge.classList.remove('d-none');
          } else {
            badge.classList.add('d-none');
          }
        })
        .catch(() => {});
    };
    refreshNotif();
    setInterval(refreshNotif, 15000);

    // Incoming call poll — keep snappy so phone→PC rings quickly on desktop
    const pollIncoming = () => {
      if (WC._inCall) return;
      WC.fetchJSON('calls.php?action=incoming')
        .then((res) => {
          if (res.data && res.data.call && typeof WC.showIncomingCall === 'function') {
            WC.showIncomingCall(res.data.call, res.data.caller);
          } else if (typeof WC.onIncomingCallGone === 'function') {
            // Only stop ringtone if caller cancelled / call ended — not on transient empty
            WC.onIncomingCallGone();
          }
        })
        .catch(() => {});
    };
    pollIncoming();
    setInterval(pollIncoming, 800);
  }

  document.addEventListener('visibilitychange', () => {
    if (!WC.USER_ID) return;
    const presence = document.hidden ? 'away' : 'online';
    WC.fetchJSON('presence.php?action=heartbeat', {
      method: 'POST',
      body: { action: 'heartbeat', presence, csrf_token: WC.CSRF },
    }).catch(() => {});
    // Resume: pick up ringing calls / in-call signals immediately
    if (!document.hidden) {
      if (!WC._inCall) {
        WC.fetchJSON('calls.php?action=incoming')
          .then((res) => {
            if (res.data && res.data.call && typeof WC.showIncomingCall === 'function') {
              WC.showIncomingCall(res.data.call, res.data.caller);
            }
          })
          .catch(() => {});
      }
    }
  });
})();
