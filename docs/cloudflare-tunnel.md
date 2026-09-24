# Cloudflare Tunnel (local test)

## Quick start (Windows)

1. Start **XAMPP Apache + MySQL**
2. Run:

```powershell
cd c:\xampp\htdocs\project\WebChat
powershell -ExecutionPolicy Bypass -File .\scripts\start-cloudflare-tunnel.ps1
```

3. Copy the printed `https://….trycloudflare.com` URL
4. Open:

`https://….trycloudflare.com/project/WebChat/login.php`

Keep the tunnel PowerShell window open while testing.

## Notes

- `APP_URL_AUTO` is enabled in local `config.local.php` so links work with the changing tunnel host.
- Voice/video need HTTPS — Tunnel provides that.
- Quick tunnels are temporary; URL changes each restart.
- For production on cPanel: point DNS to Cloudflare (orange cloud) and set `APP_URL` to your real `https://domain` with `APP_URL_AUTO=false`.
