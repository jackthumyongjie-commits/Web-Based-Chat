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
