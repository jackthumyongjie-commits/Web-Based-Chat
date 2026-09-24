(function () {
  const WC = window.WebConnect;
  if (!document.getElementById('notifList')) return;

  async function load() {
    const res = await WC.fetchJSON('notifications.php?action=list');
    const box = document.getElementById('notifList');
    const items = res.data.notifications || [];
    if (!items.length) {
      box.innerHTML = '<div class="wc-empty">' + WC.t('js.no_notifications') + '</div>';
      return;
    }
    box.innerHTML = items.map((n) => `
      <a class="wc-list-item text-decoration-none text-dark ${n.is_read ? '' : 'bg-light'}" href="${n.link ? (WC.APP_URL + '/' + n.link.replace(/^\//, '')) : '#'}" data-id="${n.id}">
        <div class="flex-grow-1">
          <div class="fw-semibold">${WC.escapeHtml(n.title)}</div>
          <div class="small text-muted">${WC.escapeHtml(n.body)}</div>
        </div>
        <div class="small text-muted">${WC.escapeHtml(n.relative || '')}</div>
      </a>`).join('');
  }

  document.getElementById('notifList').addEventListener('click', async (e) => {
    const item = e.target.closest('[data-id]');
    if (!item) return;
    await WC.fetchJSON('notifications.php', { method: 'POST', body: { action: 'mark_read', id: Number(item.dataset.id) } }).catch(() => {});
  });

  document.getElementById('btnMarkAllRead').addEventListener('click', async () => {
    await WC.fetchJSON('notifications.php', { method: 'POST', body: { action: 'mark_read', id: 0 } });
    load();
  });

  load().catch((e) => WC.toast(e.message, 'error'));
})();
