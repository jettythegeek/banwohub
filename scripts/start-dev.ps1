# Banwolaw Hub — start Laravel API + Vue/Vite (Apache proxies .test domains)
$ErrorActionPreference = "Stop"
$Root = Split-Path -Parent $PSScriptRoot
$Frontend = Join-Path $Root "frontend"
$Backend = Join-Path $Root "backend"

function Resolve-Npm {
    if (Get-Command npm -ErrorAction SilentlyContinue) { return "npm" }
    $nodejs = "C:\Program Files\nodejs\npm.cmd"
    if (Test-Path $nodejs) { return $nodejs }
    throw "npm not found. Install Node.js 20+ and add to PATH."
}

function Resolve-Php {
    $candidates = @()
    if (Get-Command php -ErrorAction SilentlyContinue) {
        $candidates += (Get-Command php).Source
    }
    $fly = "C:\Program Files\FlyEnv-Data\env\php\php.exe"
    if (Test-Path $fly) { $candidates += $fly }
    $laragonPhp = Get-ChildItem "C:\laragon\bin\php\*\php.exe" -ErrorAction SilentlyContinue | Sort-Object FullName -Descending | Select-Object -First 1
    if ($laragonPhp) { $candidates += $laragonPhp.FullName }
    foreach ($phpExe in $candidates | Select-Object -Unique) {
        $v = (& $phpExe -r "echo PHP_VERSION;")
        if ([version]$v -ge [version]"8.4.0") { return $phpExe }
    }
    throw "PHP 8.4+ required on PATH (XAMPP PHP 8.2 is too old for Laravel 13). Install PHP 8.4+ or use FlyEnv."
}

$npm = Resolve-Npm
$php = Resolve-Php

Write-Host "Banwolaw Hub — development" -ForegroundColor Cyan
Write-Host ""
Write-Host "  App:  http://banwohub.test          (Apache -> Vue/Vite :3000)"
Write-Host "  API:  http://api.banwohub.test/api/v1  (Apache -> artisan serve :8000)"
Write-Host ""
Write-Host "Before first use:" -ForegroundColor Yellow
Write-Host "  Laragon: scripts\setup-laragon.ps1 (Admin), then restart Apache if needed"
Write-Host "  XAMPP:   scripts\add-hosts-banwohub.ps1, enable-xampp-proxy.ps1, sync vhosts"
Write-Host ""
Write-Host "Press Ctrl+C to stop API and Vue dev server."
Write-Host ""

if (-not (Test-Path (Join-Path $Frontend ".env"))) {
    $example = Join-Path $Frontend ".env.example"
    if (Test-Path $example) {
        Copy-Item $example (Join-Path $Frontend ".env")
        Write-Host "Created frontend/.env from example." -ForegroundColor Yellow
    }
}

$apiJob = Start-Job -ScriptBlock {
    param ($BackendPath, $PhpCmd)
    Set-Location $BackendPath
    & $PhpCmd artisan serve --host=127.0.0.1 --port=8000
} -ArgumentList $Backend, $php

Start-Sleep -Seconds 2
try {
    $health = Invoke-WebRequest -Uri "http://127.0.0.1:8000/up" -UseBasicParsing -TimeoutSec 5
    if ($health.StatusCode -eq 200) {
        Write-Host "Laravel API listening on http://127.0.0.1:8000" -ForegroundColor Green
    }
} catch {
    Write-Host "WARN: API health check failed. Check PHP 8.4+ and backend/.env" -ForegroundColor Yellow
}

try {
    Set-Location $Frontend
    & $npm run dev
} finally {
    Stop-Job $apiJob -ErrorAction SilentlyContinue
    Remove-Job $apiJob -Force -ErrorAction SilentlyContinue
}
