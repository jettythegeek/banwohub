# Copy project vhost snippet into XAMPP (run as Administrator if httpd-vhosts.conf is protected).
$ErrorActionPreference = "Stop"
$src = Join-Path (Split-Path -Parent $PSScriptRoot) "docs\setup\xampp-vhosts.conf"
$dst = "C:\xampp\apache\conf\extra\httpd-vhosts.conf"
$marker = "# Banwolaw Hub — append to C:\xampp\apache\conf\extra\httpd-vhosts.conf"
if (-not (Test-Path $src)) { throw "Missing $src" }
$existing = Get-Content $dst -Raw -ErrorAction SilentlyContinue
if ($existing -and $existing.Contains("ServerName banwohub.test")) {
    Write-Host "Banwolaw vhosts already present in $dst — update manually if needed." -ForegroundColor Yellow
    exit 0
}
Add-Content -Path $dst -Value "`n$(Get-Content $src -Raw)"
Write-Host "Appended Banwolaw vhosts to $dst. Restart Apache." -ForegroundColor Green
