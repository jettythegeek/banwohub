$ErrorActionPreference = 'Stop'
$root = Split-Path (Split-Path $PSScriptRoot -Parent) -Parent
if (Test-Path (Join-Path $PSScriptRoot '..\product description.md')) {
    $root = Split-Path $PSScriptRoot -Parent
}

$srcPath = Join-Path $root 'product description.md'
$lines = Get-Content $srcPath -Encoding UTF8

function Write-Section {
    param(
        [string]$Path,
        [int]$StartIndex,
        [int]$EndIndex,
        [string]$Title
    )

    $dir = Split-Path $Path -Parent
    if ($dir -and !(Test-Path $dir)) {
        New-Item -ItemType Directory -Path $dir -Force | Out-Null
    }

    $content = @()
    if ($Title) {
        $content += $Title
        $content += ''
    }

    for ($i = $StartIndex; $i -le $EndIndex; $i++) {
        if ($i -ge 0 -and $i -lt $lines.Count) {
            $line = $lines[$i]
            if ($i -eq 0 -and $line -match '^create an MD') { continue }
            $content += $line
        }
    }

    $content | Set-Content -Path $Path -Encoding UTF8
}

Write-Section (Join-Path $root 'docs/00-foundation/01-product-overview.md') 3 29 '# Product Overview'
Write-Section (Join-Path $root 'docs/00-foundation/02-goals-and-principles.md') 31 54 '# Goals and Principles'
Write-Section (Join-Path $root 'docs/00-foundation/03-roles-and-permissions.md') 56 140 '# User Roles and Permissions'
Write-Section (Join-Path $root 'docs/00-foundation/04-final-summary.md') 1678 1682 '# Final Product Summary'

$modules = [ordered]@{
    'docs/modules/05-dashboard.md' = @(143, 187)
    'docs/modules/06-case-matter-management.md' = @(188, 257)
    'docs/modules/07-client-intake-forms.md' = @(258, 298)
    'docs/modules/08-conflict-check.md' = @(299, 335)
    'docs/modules/09-document-management.md' = @(336, 402)
    'docs/modules/10-brief-writing.md' = @(403, 429)
    'docs/modules/11-motion-writing.md' = @(430, 463)
    'docs/modules/12-legal-research.md' = @(464, 506)
    'docs/modules/13-court-forms.md' = @(507, 545)
    'docs/modules/14-filing-tracker.md' = @(546, 576)
    'docs/modules/15-crm.md' = @(577, 612)
    'docs/modules/16-communication-center.md' = @(613, 653)
    'docs/modules/17-client-portal.md' = @(654, 680)
    'docs/modules/18-appointments.md' = @(681, 709)
    'docs/modules/19-court-calendar.md' = @(710, 743)
    'docs/modules/20-task-management.md' = @(744, 785)
    'docs/modules/21-legal-project-management.md' = @(786, 818)
    'docs/modules/22-case-notes.md' = @(819, 840)
    'docs/modules/23-evidence-management.md' = @(841, 887)
    'docs/modules/24-e-discovery.md' = @(888, 917)
    'docs/modules/25-billing-payments.md' = @(918, 961)
    'docs/modules/26-time-tracking.md' = @(962, 992)
    'docs/modules/27-approval-workflows.md' = @(993, 1023)
    'docs/modules/28-e-signature.md' = @(1024, 1047)
    'docs/modules/29-knowledge-management.md' = @(1048, 1077)
    'docs/modules/30-training-cle.md' = @(1078, 1098)
    'docs/modules/31-ai-analytics.md' = @(1099, 1128)
    'docs/modules/32-reporting.md' = @(1129, 1164)
    'docs/modules/33-global-search.md' = @(1165, 1194)
    'docs/modules/34-ai-chatbot.md' = @(1195, 1230)
    'docs/modules/35-ai-governance.md' = @(1231, 1257)
    'docs/modules/36-security-compliance.md' = @(1258, 1291)
    'docs/modules/37-audit-trail.md' = @(1292, 1325)
    'docs/modules/38-admin-settings.md' = @(1326, 1358)
    'docs/modules/39-onboarding.md' = @(1359, 1391)
    'docs/modules/40-mobile-responsive.md' = @(1392, 1417)
    'docs/modules/41-notifications.md' = @(1418, 1444)
    'docs/modules/42-integrations.md' = @(1445, 1458)
}

foreach ($entry in $modules.GetEnumerator()) {
    $rel = $entry.Key
    $range = $entry.Value
    $full = Join-Path $root $rel
    $baseName = [System.IO.Path]::GetFileNameWithoutExtension($rel)
    $title = '# ' + ($baseName -replace '^\d+-', '' -replace '-', ' ')
    Write-Section -Path $full -StartIndex ($range[0] - 1) -EndIndex ($range[1] - 1) -Title $title
}

Write-Section (Join-Path $root 'docs/01-planning/mvp-scope.md') 1458 1497 '# MVP Scope'
Write-Section (Join-Path $root 'docs/01-planning/key-workflows.md') 1538 1590 '# Key User Workflows'
Write-Section (Join-Path $root 'docs/01-planning/non-functional-requirements.md') 1591 1616 '# Non-Functional Requirements'
Write-Section (Join-Path $root 'docs/01-planning/data-model.md') 1617 1647 '# Data Objects'
Write-Section (Join-Path $root 'docs/01-planning/success-metrics.md') 1648 1666 '# Success Metrics'
Write-Section (Join-Path $root 'docs/01-planning/risks-and-considerations.md') 1667 1677 '# Risks and Considerations'
Write-Section (Join-Path $root 'docs/02-tech-stack/tech-stack.md') 1684 1820 '# Recommended Tech Stack'

Write-Host "Created $((Get-ChildItem -Recurse (Join-Path $root 'docs') -File).Count) documentation files."
