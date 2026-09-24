/**
 * Groups list + create
 */
(function () {
  const WC = window.WebConnect;
  if (!document.getElementById('groupsList')) return;

  function roleLabel(role) {
    if (role === 'owner') return WC.t('js.owner');
    if (role === 'admin') return WC.t('js.admin');
    return WC.t('js.member');
  }

  async function loadGroups() {
    const res = await WC.fetchJSON('groups.php?action=list');
    const box = document.getElementById('groupsList');
    const groups = res.data.groups || [];
    if (!groups.length) {
      box.innerHTML = '<div class="col-12"><div class="wc-panel wc-empty">' + WC.t('js.no_groups') + '</div></div>';
      return;
    }
    box.innerHTML = groups.map((g) => `
      <div class="col-md-6 col-lg-4">
        <div class="wc-panel p-3 h-100 d-flex flex-column">
          <div class="d-flex gap-2 align-items-center mb-2">
            ${g.avatar_url ? `<img class="wc-avatar-md" src="${WC.escapeHtml(g.avatar_url)}" alt="">` : `<div class="wc-avatar-md d-flex align-items-center justify-content-center bg-light"><i class="fa-solid fa-users"></i></div>`}
            <div>
              <div class="fw-semibold">${WC.escapeHtml(g.name)}</div>
              <div class="small text-muted">${g.member_count} ${WC.t('js.members')} · ${WC.escapeHtml(roleLabel(g.role))}</div>
            </div>
          </div>
          <p class="small text-muted flex-grow-1">${WC.escapeHtml(g.description || '')}</p>
          <a class="btn btn-wc btn-sm" href="${WC.APP_URL}/group_chat.php?id=${g.id}">${WC.t('js.open')}</a>
        </div>
      </div>`).join('');
  }

  async function loadFriendPicker() {
    const res = await WC.fetchJSON('friends.php?action=list');
    const box = document.getElementById('friendPickList');
    const friends = res.data.friends || [];
    box.innerHTML = friends.length ? friends.map((f) => `
      <label class="d-flex align-items-center gap-2 mb-1">
        <input type="checkbox" name="member_ids" value="${f.id}">
        <img class="wc-avatar-sm" src="${WC.escapeHtml(f.avatar_url)}" alt="">
        ${WC.escapeHtml(f.username)}
      </label>`).join('') : '<div class="small text-muted">' + WC.t('js.add_friend_first') + '</div>';
  }

  document.getElementById('createGroupForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const fd = new FormData(e.target);
    const memberIds = [...e.target.querySelectorAll('input[name="member_ids"]:checked')].map((el) => Number(el.value));
    try {
      const res = await WC.fetchJSON('groups.php', {
        method: 'POST',
        body: {
          action: 'create',
          name: fd.get('name'),
          description: fd.get('description') || '',
          member_ids: memberIds,
        },
      });
      bootstrap.Modal.getInstance(document.getElementById('createGroupModal'))?.hide();
      location.href = WC.APP_URL + '/group_chat.php?id=' + res.data.group_id;
    } catch (err) { WC.toast(err.message, 'error'); }
  });

  loadGroups().catch((e) => WC.toast(e.message, 'error'));
  loadFriendPicker().catch(() => {});
})();
