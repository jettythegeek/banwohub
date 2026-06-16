# Build the Vue frontend locally and copy the production bundle into Laravel public/.
param(
    [string]$ApiUrl = "https://hub.banwolaw.net/api/v1"
)

$ErrorActionPreference = "Stop"

$Root = Split-Path -Parent $PSScriptRoot
$Frontend = Join-Path $Root "frontend"
$Public = Join-Path $Root "public"
$Dist = Join-Path $Frontend "dist"

if (-not (Test-Path -LiteralPath $Frontend)) {
    throw "Frontend folder not found: $Frontend"
}

if (-not (Test-Path -LiteralPath $Public)) {
    throw "Laravel public folder not found: $Public"
}

Write-Host "Building frontend for $ApiUrl" -ForegroundColor Cyan

Push-Location $Frontend
try {
    $env:VITE_API_URL = $ApiUrl
    npm run build-only
} finally {
    Pop-Location
}

$publicResolved = (Resolve-Path -LiteralPath $Public).Path
$rootResolved = (Resolve-Path -LiteralPath $Root).Path
if (-not $publicResolved.StartsWith($rootResolved)) {
    throw "Resolved public path is outside the workspace: $publicResolved"
}

foreach ($target in @('assets', 'index.html', 'favicon.ico')) {
    $path = Join-Path $Public $target
    if (Test-Path -LiteralPath $path) {
        Remove-Item -LiteralPath $path -Recurse -Force
    }
}

Get-ChildItem -LiteralPath $Dist -Force | ForEach-Object {
    Copy-Item -LiteralPath $_.FullName -Destination $Public -Recurse -Force
}

Write-Host "Production frontend copied to public/." -ForegroundColor Green
