# Verify Banwolaw Hub login against the API (run from repo root).
# Requires: php artisan serve in backend/, Apache restarted if using banwohub.test.
param(
    [string]$ApiBase = "http://banwohub.test/api/v1",
    [string]$Email = "admin@banwolaw.com",
    [string]$Password = "ChangeMe123!"
)

$body = @{ email = $Email; password = $Password } | ConvertTo-Json
$uri = "$ApiBase/auth/login"

Write-Host "POST $uri"
try {
    $r = Invoke-RestMethod -Uri $uri -Method Post -Body $body -ContentType "application/json"
    if ($r.token) {
        Write-Host "OK login succeeded (token length $($r.token.Length))"
        exit 0
    }
    Write-Host "FAIL unexpected response"
    exit 1
} catch {
    Write-Host "FAIL $($_.Exception.Message)"
    if ($_.ErrorDetails.Message) { Write-Host $_.ErrorDetails.Message }
    exit 1
}
