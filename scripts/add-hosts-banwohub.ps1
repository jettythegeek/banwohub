# Requires Administrator: Right-click PowerShell -> Run as administrator
#   .\scripts\add-hosts-banwohub.ps1
$hostsPath = "$env:SystemRoot\System32\drivers\etc\hosts"
$entries = @(
    @{ Ip = "127.0.0.1"; Name = "banwohub.test" },
    @{ Ip = "127.0.0.1"; Name = "api.banwohub.test" }
)
$content = Get-Content $hostsPath -Raw -ErrorAction Stop
$added = @()
foreach ($entry in $entries) {
    $name = $entry.Name
    if ($content -match "(?m)^\s*\S+\s+$([regex]::Escape($name))\s*$") {
        continue
    }
    $line = "$($entry.Ip) $name"
    try {
        Add-Content -Path $hostsPath -Value $line -ErrorAction Stop
        $content += "`n$line"
        $added += $line
    } catch {
        Write-Host "ERROR: Cannot write hosts file. Run this script as Administrator."
        Write-Host $_.Exception.Message
        exit 1
    }
}
$missing = @()
foreach ($entry in $entries) {
    if ($content -notmatch "(?m)^\s*\S+\s+$([regex]::Escape($entry.Name))\s*$") {
        $missing += $entry.Name
    }
}
if ($missing.Count -gt 0) {
    Write-Host "WARNING: Missing hosts entries: $($missing -join ', '). Run this script as Administrator."
}
if ($added.Count -eq 0 -and $missing.Count -eq 0) {
    Write-Host "Hosts entries already present for banwohub.test / api.banwohub.test"
} elseif ($added.Count -gt 0) {
    Write-Host "Added to hosts file:"
    $added | ForEach-Object { Write-Host "  $_" }
}
