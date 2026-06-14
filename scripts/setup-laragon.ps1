# Banwolaw Hub — Laragon setup (run as Administrator from repo root)
#   .\scripts\setup-laragon.ps1
$ErrorActionPreference = "Stop"
$Root = Split-Path -Parent $PSScriptRoot
$laragonSites = "C:\laragon\etc\apache2\sites-enabled"
$httpdConf = "C:\laragon\bin\apache\httpd-2.4.66-260223-Win64-VS18\conf\httpd.conf"
$apacheBin = "C:\laragon\bin\apache\httpd-2.4.66-260223-Win64-VS18\bin"

Write-Host "Banwolaw Hub — Laragon setup" -ForegroundColor Cyan

& (Join-Path $PSScriptRoot "add-hosts-banwohub.ps1")

if (-not (Test-Path $httpdConf)) {
    throw "Laragon httpd.conf not found at $httpdConf — adjust path if your Apache version differs."
}
$content = Get-Content $httpdConf -Raw
$changed = $false
foreach ($pair in @(
    @{ Old = '# LoadModule proxy_module modules/mod_proxy.so'; New = 'LoadModule proxy_module modules/mod_proxy.so' },
    @{ Old = '# LoadModule proxy_http_module modules/mod_proxy_http.so'; New = 'LoadModule proxy_http_module modules/mod_proxy_http.so' }
)) {
    if ($content.Contains($pair.Old)) {
        $content = $content.Replace($pair.Old, $pair.New)
        $changed = $true
    }
}
if ($changed) {
    Set-Content -Path $httpdConf -Value $content -NoNewline
    Write-Host "Enabled mod_proxy + mod_proxy_http." -ForegroundColor Green
} else {
    Write-Host "Proxy modules already enabled." -ForegroundColor Yellow
}

$banwohubVhost = @'
# Banwolaw Hub — Laragon vhost (Vue/Vite :3000 + API /api -> artisan :8000)

<VirtualHost *:80>
    ServerName banwohub.test
    ProxyPreserveHost On
    ProxyPass /api http://127.0.0.1:8000/api
    ProxyPassReverse /api http://127.0.0.1:8000/api
    ProxyPass / http://127.0.0.1:3000/
    ProxyPassReverse / http://127.0.0.1:3000/
    ErrorLog "logs/banwohub.test-error.log"
    CustomLog "logs/banwohub.test-access.log" common
</VirtualHost>
'@

$apiVhost = @'
# Banwolaw Hub — dedicated API host

<VirtualHost *:80>
    ServerName api.banwohub.test
    ProxyPreserveHost On
    ProxyPass / http://127.0.0.1:8000/
    ProxyPassReverse / http://127.0.0.1:8000/
    ErrorLog "logs/api.banwohub.test-error.log"
    CustomLog "logs/api.banwohub.test-access.log" common
</VirtualHost>
'@

Set-Content -Path (Join-Path $laragonSites "banwohub.test.conf") -Value $banwohubVhost -NoNewline
Set-Content -Path (Join-Path $laragonSites "api.banwohub.test.conf") -Value $apiVhost -NoNewline

$autoVhost = Join-Path $laragonSites "auto.banwohub.test.conf"
if (Test-Path $autoVhost) {
    Remove-Item $autoVhost -Force
    Write-Host "Removed auto.banwohub.test.conf." -ForegroundColor Green
}

$httpd = Join-Path $apacheBin "httpd.exe"
& $httpd -t
Get-Process httpd -ErrorAction SilentlyContinue | Stop-Process -Force
Start-Sleep -Seconds 2
Start-Process -FilePath $httpd -WorkingDirectory $apacheBin -WindowStyle Hidden
Write-Host "Apache restarted." -ForegroundColor Green

Write-Host ""
Write-Host "Start dev servers: .\scripts\start-dev.ps1" -ForegroundColor Cyan
Write-Host "Then open http://banwohub.test" -ForegroundColor Green
