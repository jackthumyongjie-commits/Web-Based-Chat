/**
 * WebConnect — WebRTC voice/video calling
 */
(function () {
  const WC = window.WebConnect;
  if (!WC || !WC.USER_ID) return;

  const overlay = document.getElementById('callOverlay');
  const remoteVideo = document.getElementById('remoteVideo');
  const localVideo = document.getElementById('localVideo');
  const remoteAudio = document.getElementById('remoteAudio');
  const voicePanel = document.getElementById('voiceCallPanel');
  const activeName = document.getElementById('activeCallName');
  const activeStatus = document.getElementById('activeCallStatus');
  const activeAvatar = document.getElementById('activeCallAvatar');
  const btnMute = document.getElementById('btnToggleMute');
  const btnCam = document.getElementById('btnToggleCamera');
  const btnEnd = document.getElementById('btnEndCall');
  const incomingModalEl = document.getElementById('incomingCallModal');
  let incomingModal = null;

  const callState = {
    callId: null,
    peerId: null,
    peer: null,
    type: 'voice',
    pc: null,
    localStream: null,
    remoteStream: null,
    pendingIce: [],
    lastSignalId: 0,
    signalTimer: null,
    statusTimer: null,
    wakeLock: null,
    muted: false,
    camOff: false,
    isCaller: false,
    offerSent: false,
    negotiating: false,
  };

  // Ringtone / ringback via Web Audio — keeps looping until stopRingtone()
  const tone = {
    ctx: null,
    timer: null,
    burstTimers: [],
    nodes: [],
    mode: null, // 'out' | 'in'
    active: false,
    gen: 0,
  };
  const ignoredCalls = {}; // call ids user already rejected/accepted

  function ensureAudioCtx() {
    const AC = window.AudioContext || window.webkitAudioContext;
    if (!AC) return null;
    if (!tone.ctx) tone.ctx = new AC();
    if (tone.ctx.state === 'suspended') {
      tone.ctx.resume().catch(() => {});
    }
    return tone.ctx;
  }

  // Browsers block audio until a user gesture — unlock on any interaction
  function unlockAudio() {
    const ctx = ensureAudioCtx();
    if (!ctx) return;
    try {
      const buf = ctx.createBuffer(1, 1, 22050);
      const src = ctx.createBufferSource();
      src.buffer = buf;
      src.connect(ctx.destination);
      src.start(0);
    } catch (e) { /* ignore */ }
  }
  ['pointerdown', 'keydown', 'touchstart'].forEach((evt) => {
    document.addEventListener(evt, unlockAudio, { passive: true, once: true });
  });
  incomingModalEl?.addEventListener('shown.bs.modal', () => {
    unlockAudio();
    // Only start if still an active unanswered incoming call
    if (callState._shownIncoming && !ignoredCalls[callState._shownIncoming]) {
      startIncomingRing();
    }
  });

  function stopRingtone() {
    tone.gen += 1;
    tone.active = false;
    tone.mode = null;
    if (tone.timer) {
      clearTimeout(tone.timer);
      tone.timer = null;
    }
    tone.burstTimers.forEach((t) => clearTimeout(t));
    tone.burstTimers = [];
    tone.nodes.forEach((n) => {
      try { if (typeof n.stop === 'function') n.stop(0); } catch (e) { /* already stopped */ }
      try { n.disconnect(); } catch (e) { /* ignore */ }
    });
    tone.nodes = [];
    try { if (navigator.vibrate) navigator.vibrate(0); } catch (e) { /* ignore */ }
  }

  function playToneBurst(freqs, durationMs, volume, gen) {
    if (gen !== tone.gen) return;
    const ctx = ensureAudioCtx();
    if (!ctx) return;
    const now = ctx.currentTime;
    const dur = Math.max(0.05, durationMs / 1000);
    const gain = ctx.createGain();
    gain.connect(ctx.destination);
    gain.gain.setValueAtTime(volume, now);
    gain.gain.exponentialRampToValueAtTime(0.001, now + dur);
    tone.nodes.push(gain);
    freqs.forEach((f) => {
      const osc = ctx.createOscillator();
      osc.type = 'sine';
      osc.frequency.value = f;
      osc.connect(gain);
      osc.start(now);
      osc.stop(now + dur + 0.02);
      tone.nodes.push(osc);
    });
  }

  /** Outgoing ringback — loops until answered / cancelled */
  function startOutgoingRing() {
    stopRingtone();
    const gen = tone.gen;
    tone.active = true;
    tone.mode = 'out';
    ensureAudioCtx();
    const tick = () => {
      if (gen !== tone.gen || !tone.active || tone.mode !== 'out') return;
      playToneBurst([440, 480], 2000, 0.1, gen);
      if (gen !== tone.gen || !tone.active) return;
      tone.timer = setTimeout(tick, 6000);
    };
    tick();
  }

  /** Incoming ringtone — loops until accept / reject */
  function startIncomingRing() {
    if (tone.active && tone.mode === 'in') {
      ensureAudioCtx();
      return;
    }
    stopRingtone();
    const gen = tone.gen;
    tone.active = true;
    tone.mode = 'in';
    ensureAudioCtx();
    const tick = () => {
      if (gen !== tone.gen || !tone.active || tone.mode !== 'in') return;
      ensureAudioCtx();
      playToneBurst([852, 1209], 420, 0.18, gen);
      tone.burstTimers.push(setTimeout(() => {
        if (gen === tone.gen && tone.active && tone.mode === 'in') {
          playToneBurst([852, 1209], 420, 0.18, gen);
        }
      }, 520));
      try {
        if (navigator.vibrate) navigator.vibrate([400, 180, 400, 1200]);
      } catch (e) { /* ignore */ }
      if (gen !== tone.gen || !tone.active) return;
      tone.timer = setTimeout(tick, 2400);
    };
    tick();
  }

  /** Stop ring + ignore this call so poll cannot restart it */
  function dismissIncoming(callId) {
    if (callId) ignoredCalls[callId] = true;
    callState._shownIncoming = null;
    callState.callId = null;
    callState.peerId = null;
    stopRingtone();
    try { incomingModal?.hide(); } catch (e) { /* ignore */ }
  }

  function setStatus(text) {
    if (activeStatus) activeStatus.textContent = text;
  }

  function showOverlay(show) {
    if (!overlay) return;
    overlay.classList.toggle('d-none', !show);
    WC._inCall = show;
  }

  async function stopStream(stream) {
    if (!stream) return;
    stream.getTracks().forEach((t) => {
      try { t.stop(); } catch (e) { /* ignore */ }
    });
  }

  async function requestWakeLock() {
    try {
      if (navigator.wakeLock && navigator.wakeLock.request) {
        callState.wakeLock = await navigator.wakeLock.request('screen');
        callState.wakeLock.addEventListener('release', () => {
          callState.wakeLock = null;
        });
      }
    } catch (e) { /* unsupported / denied */ }
  }

  async function releaseWakeLock() {
    try {
      if (callState.wakeLock) await callState.wakeLock.release();
    } catch (e) { /* ignore */ }
    callState.wakeLock = null;
  }

  /**
   * Voice = microphone only (never touch camera).
   * Retry with simpler constraints — Windows/Chrome often throws NotReadableError
   * for permission / default-device issues, not only "another app".
   */
  async function getMedia(type) {
    const wantVideo = type === 'video';
    if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
      throw friendlyMediaError({ name: 'NotSupportedError' }, wantVideo);
    }

    const attempts = wantVideo
      ? [
          {
            audio: { echoCancellation: true, noiseSuppression: true, autoGainControl: true },
            video: { facingMode: 'user', width: { ideal: 1280 }, height: { ideal: 720 } },
          },
          {
            audio: true,
            video: { facingMode: 'user' },
          },
          { audio: true, video: true },
        ]
      : [
          {
            audio: { echoCancellation: true, noiseSuppression: true, autoGainControl: true },
          },
          { audio: true },
        ];

    // Prefer a real audioinput if the default device is broken
    try {
      const devices = await navigator.mediaDevices.enumerateDevices();
      const mics = devices.filter((d) => d.kind === 'audioinput' && d.deviceId);
      if (!wantVideo && mics.length) {
        attempts.push({ audio: { deviceId: { exact: mics[0].deviceId } } });
        if (mics[1]) attempts.push({ audio: { deviceId: { exact: mics[1].deviceId } } });
      }
    } catch (e) { /* ignore */ }

    let lastErr = null;
    for (const constraints of attempts) {
      try {
        return await navigator.mediaDevices.getUserMedia(constraints);
      } catch (err) {
        lastErr = err;
      }
    }
    throw friendlyMediaError(lastErr || { name: 'NotReadableError' }, wantVideo);
  }

  function friendlyMediaError(err, wantVideo) {
    const name = err && err.name ? err.name : '';
    let key = wantVideo ? 'js.media_cam_denied' : 'js.media_mic_denied';
    if (name === 'NotFoundError' || name === 'DevicesNotFoundError') {
      key = wantVideo ? 'js.media_no_cam' : 'js.media_no_mic';
    } else if (name === 'NotReadableError' || name === 'TrackStartError' || name === 'AbortError') {
      key = wantVideo ? 'js.media_cam_busy' : 'js.media_mic_busy';
    } else if (name === 'NotAllowedError' || name === 'PermissionDeniedError') {
      key = wantVideo ? 'js.media_cam_denied' : 'js.media_mic_denied';
    } else if (name === 'SecurityError' || name === 'NotSupportedError') {
      key = 'js.media_insecure';
    }
    const friendly = new Error(WC.t(key));
    friendly.cause = err;
    return friendly;
  }

  /** Accumulate remote tracks — Safari often fires ontrack without streams[0]. */
  function attachRemoteTrack(track) {
    if (!track) return;
    if (!callState.remoteStream) {
      callState.remoteStream = new MediaStream();
    }
    const exists = callState.remoteStream.getTracks().some((t) => t.id === track.id);
    if (!exists) {
      callState.remoteStream.addTrack(track);
    }
    if (remoteVideo) {
      remoteVideo.srcObject = callState.remoteStream;
      remoteVideo.muted = false;
      remoteVideo.playsInline = true;
      remoteVideo.play().catch(() => {});
    }
    if (remoteAudio) {
      remoteAudio.srcObject = callState.remoteStream;
      remoteAudio.muted = false;
      remoteAudio.play().catch(() => {});
    }
  }

  async function flushPendingIce() {
    if (!callState.pc || !callState.pc.remoteDescription) return;
    const queued = callState.pendingIce.splice(0, callState.pendingIce.length);
    for (const cand of queued) {
      try {
        await callState.pc.addIceCandidate(cand);
      } catch (e) { /* stale / duplicate */ }
    }
  }

  function createPeerConnection() {
    const ice = WC.RTC_CONFIG || { iceServers: [{ urls: 'stun:stun.l.google.com:19302' }] };
    const pc = new RTCPeerConnection({
      iceServers: ice.iceServers || ice,
      iceCandidatePoolSize: 4,
      bundlePolicy: 'max-bundle',
      rtcpMuxPolicy: 'require',
    });
    pc.onicecandidate = (ev) => {
      if (ev.candidate && callState.callId) {
        WC.fetchJSON('calls.php', {
          method: 'POST',
          body: {
            action: 'signal',
            call_id: callState.callId,
            signal_type: 'ice',
            payload: ev.candidate,
          },
        }).catch(() => {});
      }
    };
    pc.ontrack = (ev) => {
      if (ev.streams && ev.streams[0]) {
        ev.streams[0].getTracks().forEach((t) => attachRemoteTrack(t));
      } else if (ev.track) {
        attachRemoteTrack(ev.track);
      }
    };
    pc.onconnectionstatechange = () => {
      const st = pc.connectionState;
      if (st === 'connected') {
        stopRingtone();
        setStatus(WC.t('js.connected'));
        try { remoteVideo && remoteVideo.play(); } catch (e) { /* ignore */ }
        try { remoteAudio && remoteAudio.play(); } catch (e) { /* ignore */ }
      } else if (st === 'connecting') setStatus(WC.t('js.connecting'));
      else if (st === 'failed') {
        stopRingtone();
        setStatus(WC.t('js.unable_establish_call'));
        WC.toast(WC.t('js.call_failed_turn'), 'error');
      } else if (st === 'disconnected' || st === 'closed') {
        stopRingtone();
        setStatus(WC.t('js.call_ended'));
      }
    };
    pc.oniceconnectionstatechange = () => {
      const st = pc.iceConnectionState;
      if (st === 'connected' || st === 'completed') {
        stopRingtone();
        setStatus(WC.t('js.connected'));
      } else if (st === 'checking') {
        setStatus(WC.t('js.connecting'));
      } else if (st === 'failed') {
        setStatus(WC.t('js.unable_establish_call'));
        WC.toast(WC.t('js.call_failed_turn'), 'error');
      }
    };
    return pc;
  }

  async function postSignal(type, payload) {
    await WC.fetchJSON('calls.php', {
      method: 'POST',
      body: { action: 'signal', call_id: callState.callId, signal_type: type, payload },
    });
  }

  async function handleSignals(signals) {
    if (!callState.pc) return;
    for (const s of signals) {
      try {
        if (s.signal_type === 'offer') {
          // Skip duplicates — re-applying breaks negotiation mid-call
          if (!callState.pc.remoteDescription) {
            await callState.pc.setRemoteDescription(s.payload);
            await flushPendingIce();
            const answer = await callState.pc.createAnswer();
            await callState.pc.setLocalDescription(answer);
            await postSignal('answer', answer);
            stopRingtone();
            setStatus(WC.t('js.connecting'));
          }
        } else if (s.signal_type === 'answer') {
          if (callState.pc.signalingState === 'have-local-offer') {
            await callState.pc.setRemoteDescription(s.payload);
            await flushPendingIce();
            stopRingtone();
            setStatus(WC.t('js.connecting'));
          }
        } else if (s.signal_type === 'ice') {
          if (!callState.pc.remoteDescription) {
            callState.pendingIce.push(s.payload);
          } else {
            try {
              await callState.pc.addIceCandidate(s.payload);
            } catch (e) { /* ignore */ }
          }
        } else if (s.signal_type === 'hangup') {
          if (s.id && s.id > callState.lastSignalId) {
            callState.lastSignalId = s.id;
          }
          await cleanupCall(false);
          WC.toast(WC.t('js.call_ended'), 'info');
          return;
        }
      } catch (e) {
        // One bad signal must not block later ICE / hangup
        console.warn('signal handle failed', s.signal_type, e);
      }
      if (s.id && s.id > callState.lastSignalId) {
        callState.lastSignalId = s.id;
      }
    }
  }

  /**
   * Caller must wait until callee accepts before creating the offer.
   * Early offer/ICE (common when phone dials PC) often expires or is
   * missed while the PC is still ringing — PC→phone looked fine because
   * the phone answers quickly.
   */
  async function maybeSendOffer() {
    if (!callState.isCaller || callState.offerSent || !callState.pc || !callState.localStream) {
      return;
    }
    if (callState.pc.localDescription) {
      callState.offerSent = true;
      return;
    }
    callState.offerSent = true;
    callState.negotiating = true;
    try {
      const offer = await callState.pc.createOffer({
        offerToReceiveAudio: true,
        offerToReceiveVideo: callState.type === 'video',
      });
      await callState.pc.setLocalDescription(offer);
      await postSignal('offer', offer);
      setStatus(WC.t('js.connecting'));
    } catch (e) {
      callState.offerSent = false;
      callState.negotiating = false;
      console.warn('send offer failed', e);
      WC.toast(e.message || WC.t('js.unable_establish_call'), 'error');
      await cleanupCall(true);
    }
  }

  async function pollSignalsOnce() {
    if (!callState.callId) return;
    try {
      const res = await WC.fetchJSON(
        'calls.php?action=poll_signals&call_id=' + callState.callId
          + '&after_id=' + encodeURIComponent(callState.lastSignalId)
      );
      if (res.data.call && ['ended', 'rejected', 'missed', 'cancelled'].includes(res.data.call.status)) {
        await cleanupCall(false);
        setStatus(WC.t('js.call_ended'));
        return;
      }
      if (res.data.call && res.data.call.status === 'accepted') {
        stopRingtone();
        await maybeSendOffer();
      }
      if (res.data.signals && res.data.signals.length) {
        await handleSignals(res.data.signals);
      } else if (typeof res.data.last_id === 'number' && res.data.last_id > callState.lastSignalId) {
        callState.lastSignalId = res.data.last_id;
      }
    } catch (e) { /* ignore transient network */ }
  }

  function startSignalPoll() {
    stopSignalPoll();
    // Immediate + recursive timeout (more reliable than setInterval on mobile).
    // Faster while still negotiating / waiting for accept.
    const tick = async () => {
      await pollSignalsOnce();
      if (!callState.callId) return;
      const connected = callState.pc
        && (callState.pc.connectionState === 'connected'
          || callState.pc.iceConnectionState === 'connected'
          || callState.pc.iceConnectionState === 'completed');
      const delay = connected ? 1200 : 400;
      callState.signalTimer = setTimeout(tick, delay);
    };
    tick();
  }

  function stopSignalPoll() {
    if (callState.signalTimer) {
      clearTimeout(callState.signalTimer);
      clearInterval(callState.signalTimer);
    }
    callState.signalTimer = null;
  }

  async function prepareUi(peer, type) {
    callState.peer = peer;
    callState.type = type;
    callState.muted = false;
    callState.camOff = false;
    if (activeName) activeName.textContent = peer.username || 'User';
    if (activeAvatar) activeAvatar.src = peer.avatar_url || '';
    if (voicePanel) voicePanel.style.display = type === 'video' ? 'none' : 'flex';
    if (remoteVideo) {
      remoteVideo.style.display = type === 'video' ? 'block' : 'none';
      remoteVideo.classList.toggle('d-none', type !== 'video');
    }
    if (localVideo) {
      localVideo.style.display = type === 'video' ? 'block' : 'none';
      localVideo.classList.toggle('d-none', type !== 'video');
    }
    if (btnCam) btnCam.classList.toggle('d-none', type !== 'video');
    if (btnMute) {
      btnMute.innerHTML = '<i class="fa-solid fa-microphone"></i>';
    }
    if (btnCam) {
      btnCam.innerHTML = '<i class="fa-solid fa-video"></i>';
    }
    showOverlay(true);
    await requestWakeLock();
  }

  async function cleanupCall(notifyServer) {
    stopRingtone();
    stopSignalPoll();
    await releaseWakeLock();
    if (notifyServer && callState.callId) {
      try {
        await WC.fetchJSON('calls.php', {
          method: 'POST',
          body: { action: 'end', call_id: callState.callId, reason: 'ended' },
        });
      } catch (e) { /* ignore */ }
    }
    if (callState.pc) {
      try { callState.pc.close(); } catch (e) {}
    }
    await stopStream(callState.localStream);
    if (remoteVideo) remoteVideo.srcObject = null;
    if (localVideo) localVideo.srcObject = null;
    if (remoteAudio) remoteAudio.srcObject = null;
    callState.pc = null;
    callState.localStream = null;
    callState.remoteStream = null;
    callState.pendingIce = [];
    callState.lastSignalId = 0;
    callState.offerSent = false;
    callState.negotiating = false;
    callState.callId = null;
    callState.peerId = null;
    callState._shownIncoming = null;
    showOverlay(false);
  }

  WC.startCall = async function (peerId, type, peer) {
    if (WC._inCall) {
      WC.toast(WC.t('js.already_in_call'), 'error');
      return;
    }
    try {
      setStatus(WC.t('js.calling'));
      await prepareUi(peer || { username: 'User', avatar_url: '' }, type);
      callState.isCaller = true;
      callState.peerId = peerId;
      callState.lastSignalId = 0;
      callState.pendingIce = [];
      callState.remoteStream = null;
      callState.offerSent = false;
      callState.negotiating = false;

      const start = await WC.fetchJSON('calls.php', {
        method: 'POST',
        body: { action: 'start', user_id: peerId, call_type: type },
      });
      callState.callId = start.data.call.id;
      if (start.data.peer) callState.peer = start.data.peer;

      callState.localStream = await getMedia(type);
      if (localVideo) {
        localVideo.srcObject = type === 'video' ? callState.localStream : null;
        if (type === 'video') localVideo.play().catch(() => {});
      }
      if (remoteVideo && type !== 'video') {
        remoteVideo.srcObject = null;
      }
      callState.pc = createPeerConnection();
      callState.localStream.getTracks().forEach((t) => callState.pc.addTrack(t, callState.localStream));

      // Do NOT createOffer yet — wait until callee accepts (see maybeSendOffer).
      setStatus(WC.t('js.ringing'));
      startOutgoingRing();
      startSignalPoll();
    } catch (e) {
      await cleanupCall(true);
      WC.toast(e.message || WC.t('js.unable_start_call'), 'error');
    }
  };

  WC.showIncomingCall = function (call, caller) {
    if (WC._inCall || !incomingModalEl) return;
    if (ignoredCalls[call.id]) return; // already rejected/accepted — never restart ring
    // Same call still ringing — keep ringtone going until accept / reject
    if (callState._shownIncoming === call.id) {
      if (!tone.active || tone.mode !== 'in') startIncomingRing();
      return;
    }
    callState._shownIncoming = call.id;
    callState.callId = call.id;
    callState.peerId = call.caller_id;
    callState.type = call.call_type;
    callState.peer = caller;
    callState.isCaller = false;

    document.getElementById('incomingCallName').textContent = caller.username;
    document.getElementById('incomingCallAvatar').src = caller.avatar_url;
    document.getElementById('incomingCallType').textContent =
      call.call_type === 'video' ? WC.t('js.video_call') : WC.t('js.voice_call');

    if (!incomingModal) {
      incomingModal = new bootstrap.Modal(incomingModalEl, { backdrop: 'static', keyboard: false });
    }
    incomingModal.show();
    startIncomingRing();
  };

  /**
   * Poll says no ringing call. Only dismiss if caller cancelled (not our reject —
   * reject already dismissed locally).
   */
  WC.onIncomingCallGone = async function () {
    if (WC._inCall) return;
    if (!callState._shownIncoming || callState.isCaller) return;
    const shownId = callState._shownIncoming;
    if (ignoredCalls[shownId]) {
      dismissIncoming(shownId);
      return;
    }
    try {
      const st = await WC.fetchJSON('calls.php?action=status&call_id=' + shownId);
      const status = st.data && st.data.call ? st.data.call.status : null;
      if (status === 'ringing') {
        if (!tone.active || tone.mode !== 'in') startIncomingRing();
        return;
      }
      if (status && status !== 'accepted') {
        dismissIncoming(shownId);
      }
    } catch (e) {
      // Network blip: keep ringing until user accepts/rejects
    }
  };

  document.getElementById('btnAcceptCall')?.addEventListener('click', async (e) => {
    e.preventDefault();
    e.stopPropagation();
    const id = callState.callId;
    if (id) ignoredCalls[id] = true;
    stopRingtone();
    incomingModal?.hide();
    try {
      setStatus(WC.t('js.connecting'));
      callState.lastSignalId = 0;
      callState.pendingIce = [];
      callState.remoteStream = null;
      callState.offerSent = false;
      callState.negotiating = true;
      await prepareUi(callState.peer, callState.type);
      await WC.fetchJSON('calls.php', {
        method: 'POST',
        body: { action: 'accept', call_id: id },
      });
      callState._shownIncoming = null;
      callState.localStream = await getMedia(callState.type);
      if (localVideo) {
        localVideo.srcObject = callState.type === 'video' ? callState.localStream : null;
        if (callState.type === 'video') localVideo.play().catch(() => {});
      }
      callState.pc = createPeerConnection();
      callState.localStream.getTracks().forEach((t) => callState.pc.addTrack(t, callState.localStream));
      // Caller sends offer only after accept; poll hard until it arrives
      startSignalPoll();
    } catch (err) {
      await cleanupCall(true);
      WC.toast(err.message || WC.t('js.unable_accept_call'), 'error');
    }
  });

  document.getElementById('btnRejectCall')?.addEventListener('click', async (e) => {
    e.preventDefault();
    e.stopPropagation();
    const id = callState.callId;
    // Stop immediately — before any await — so click handlers / poll cannot restart
    dismissIncoming(id);
    try {
      if (id) {
        await WC.fetchJSON('calls.php', {
          method: 'POST',
          body: { action: 'reject', call_id: id },
        });
      }
    } catch (err) {
      WC.toast(err.message || WC.t('js.call_ended'), 'error');
    }
  });

  btnEnd?.addEventListener('click', async () => {
    stopRingtone();
    if (callState.isCaller && callState.callId) {
      try {
        const st = await WC.fetchJSON('calls.php?action=status&call_id=' + callState.callId);
        if (st.data.call.status === 'ringing') {
          await WC.fetchJSON('calls.php', { method: 'POST', body: { action: 'cancel', call_id: callState.callId } });
          await cleanupCall(false);
          return;
        }
      } catch (e) {}
    }
    await cleanupCall(true);
  });

  btnMute?.addEventListener('click', () => {
    callState.muted = !callState.muted;
    if (callState.localStream) {
      callState.localStream.getAudioTracks().forEach((t) => { t.enabled = !callState.muted; });
    }
    btnMute.innerHTML = callState.muted
      ? '<i class="fa-solid fa-microphone-slash"></i>'
      : '<i class="fa-solid fa-microphone"></i>';
  });

  btnCam?.addEventListener('click', () => {
    callState.camOff = !callState.camOff;
    if (callState.localStream) {
      callState.localStream.getVideoTracks().forEach((t) => { t.enabled = !callState.camOff; });
    }
    btnCam.innerHTML = callState.camOff
      ? '<i class="fa-solid fa-video-slash"></i>'
      : '<i class="fa-solid fa-video"></i>';
  });

  // Re-acquire wake lock when tab becomes visible again during a call
  document.addEventListener('visibilitychange', () => {
    if (document.visibilityState === 'visible' && WC._inCall) {
      requestWakeLock();
      pollSignalsOnce();
    }
  });
})();
