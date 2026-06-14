# Requires Administrator if httpd.conf is not writable by current user.
# Enables mod_proxy_http so banwohub.test can ProxyPass to Next.js on :3000.
$ErrorActionPreference = "Stop"
$httpdConf = "C:\xampp\apache\conf\httpd.conf"
if (-not (Test-Path $httpdConf)) {
    Write-Host "ERROR: Apache config not found at $httpdConf" -ForegroundColor Red
    exit 1
}
$content = Get-Content $httpdConf -Raw
$pattern = '#LoadModule proxy_http_module modules/mod_proxy_http.so'
$replacement = 'LoadModule proxy_http_module modules/mod_proxy_http.so'
if ($content -match '(?m)^LoadModule proxy_http_module') {
    Write-Host "mod_proxy_http already enabled." -ForegroundColor Green
    exit 0
}
if ($content -notmatch [regex]::Escape($pattern)) {
    Write-Host "ERROR: Expected commented proxy_http line not found in httpd.conf." -ForegroundColor Red
    exit 1
}
$content = $content.Replace($pattern, $replacement)
Set-Content -Path $httpdConf -Value $content -NoNewline
Write-Host "Enabled mod_proxy_http in httpd.conf. Restart Apache in XAMPP Control Panel." -ForegroundColor Green
