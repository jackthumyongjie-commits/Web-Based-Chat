# Cloudflare Quick Tunnel for local XAMPP testing
# Run:  .\scripts\start-cloudflare-tunnel.ps1
# Needs: Apache running, project at http://127.0.0.1/project/WebChat/

$ErrorActionPreference = "Stop"
$Root = Split-Path -Parent (Split-Path -Parent $MyInvocation.MyCommand.Path)
$BinDir = Join-Path $Root "tools"
$Exe = Join-Path $BinDir "cloudflared.exe"
$Origin = "http://127.0.0.1"

if (-not (Test-Path $BinDir)) {
    New-Item -ItemType Directory -Path $BinDir | Out-Null
}

if (-not (Test-Path $Exe)) {
    Write-Host "Downloading cloudflared..." -ForegroundColor Cyan
    $url = "https://github.com/cloudflare/cloudflared/releases/latest/download/cloudflared-windows-amd64.exe"
    Invoke-WebRequest -Uri $url -OutFile $Exe -UseBasicParsing
    Write-Host "Saved: $Exe"
}

Write-Host ""
Write-Host "Starting Cloudflare Quick Tunnel..." -ForegroundColor Green
Write-Host "Local origin: $Origin  (open path /project/WebChat/login.php)" -ForegroundColor Yellow
Write-Host "Look for a line like: https://xxxx.trycloudflare.com" -ForegroundColor Yellow
Write-Host "Then open: https://xxxx.trycloudflare.com/project/WebChat/login.php" -ForegroundColor Yellow
Write-Host "Keep this window open while testing. Ctrl+C to stop." -ForegroundColor DarkGray
Write-Host ""

& $Exe tunnel --url $Origin --no-autoupdate
