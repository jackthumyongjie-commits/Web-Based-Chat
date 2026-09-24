(function () {
  const WC = window.WebConnect;
  if (!document.getElementById('profileForm')) return;

  document.getElementById('profileForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    try {
      await WC.fetchJSON('profile.php', {
        method: 'POST',
        body: {
          action: 'update',
          username: document.getElementById('profileUsername').value.trim(),
          status_message: document.getElementById('profileStatus').value.trim(),
        },
      });
      WC.toast(WC.t('js.profile_updated'), 'success');
      setTimeout(() => location.reload(), 600);
    } catch (err) { WC.toast(err.message, 'error'); }
  });

  document.getElementById('passwordForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const fd = new FormData(e.target);
    try {
      await WC.fetchJSON('profile.php', {
        method: 'POST',
        body: {
          action: 'change_password',
          current_password: fd.get('current_password'),
          new_password: fd.get('new_password'),
          confirm_password: fd.get('confirm_password'),
        },
      });
      WC.toast(WC.t('js.password_changed'), 'success');
      e.target.reset();
    } catch (err) { WC.toast(err.message, 'error'); }
  });

  document.getElementById('avatarInput').addEventListener('change', async (e) => {
    const file = e.target.files[0];
    if (!file) return;
    const fd = new FormData();
    fd.append('action', 'upload_avatar');
    fd.append('csrf_token', WC.CSRF);
    fd.append('avatar', file);
    try {
      const res = await fetch(WC.apiUrl('profile.php'), {
        method: 'POST', credentials: 'same-origin',
        headers: { 'X-CSRF-TOKEN': WC.CSRF }, body: fd,
      });
      const data = await res.json();
      if (!data.success) throw new Error(data.message);
      document.getElementById('profileAvatar').src = data.data.avatar_url;
      WC.toast(WC.t('js.avatar_updated'), 'success');
    } catch (err) { WC.toast(err.message, 'error'); }
  });
})();
