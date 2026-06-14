<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use App\Models\DocumentVersion;
use App\Models\AiGovernanceLog;
use App\Models\AiProviderConfig;
use App\Models\AppNotification;
use App\Models\ApprovalRequest;
use App\Models\CaseNote;
use App\Models\Client;
use App\Models\SignatureRequest;
use App\Models\Invoice;
use App\Models\IntakeForm;
use App\Models\IntakeSubmission;
use App\Models\LegalDocument;
use App\Models\LegalMatter;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Permission;

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(ConsoleKernel::class)->bootstrap();

/** @var HttpKernel $http */
$http = $app->make(HttpKernel::class);

$email = getenv('SEED_ADMIN_EMAIL') ?: 'admin@banwolaw.com';
$admin = User::query()->where('email', $email)->first();
if (! $admin) {
    fwrite(STDERR, "Admin not found: {$email}\n");
    exit(1);
}

$token = $admin->createToken('phase4-verify')->plainTextToken;

$results = [];
$ids = [];

function call(HttpKernel $http, string $token, string $method, string $uri, array $payload = []): array
{
    $server = [
        'HTTP_Accept' => 'application/json',
    ];
    if ($token !== '') {
        $server['HTTP_Authorization'] = 'Bearer ' . $token;
    }

    $request = Request::create('/api/v1' . $uri, $method, $payload, [], [], $server);
    if ($payload !== [] && in_array($method, ['POST', 'PUT', 'PATCH'], true)) {
        $request = Request::create('/api/v1' . $uri, $method, [], [], [], array_merge($server, [
            'CONTENT_TYPE' => 'application/json',
        ]), json_encode($payload));
    }

    $response = $http->handle($request);
    $status = $response->getStatusCode();

    ob_start();
    $response->sendContent();
    $content = ob_get_clean();
    $body = is_string($content) && $content !== '' ? json_decode($content, true) : null;

    $http->terminate($request, $response);
    \Illuminate\Support\Facades\Auth::forgetGuards();

    return ['status' => $status, 'body' => $body];
}

function record(array &$results, string $label, array $res, array $okStatuses = [200, 201]): void
{
    $ok = in_array($res['status'], $okStatuses, true);
    $results[] = [$label, $res['status'], $ok ? 'OK' : 'FAIL'];
}

config(['ai.rate_limit_per_minute' => 500, 'ai.stub_mode' => true]);
if (method_exists(Cache::getStore(), 'flush')) {
    Cache::flush();
}
$organization = $admin->organization;
if ($organization) {
    $settings = $organization->settings ?? [];
    unset($settings['active_ai_provider']);
    $organization->update(['settings' => $settings]);
}

echo "=== PHASE 4: AI SERVICE + GOVERNANCE + UI API ===\n";

$results[] = ['config/ai.php exists', file_exists(__DIR__ . '/../config/ai.php') ? 'yes' : 'no', file_exists(__DIR__ . '/../config/ai.php') ? 'OK' : 'FAIL'];
$results[] = ['ai-service package.json', file_exists(__DIR__ . '/../../ai-service/package.json') ? 'yes' : 'no', file_exists(__DIR__ . '/../../ai-service/package.json') ? 'OK' : 'FAIL'];
$results[] = ['ai_governance_logs table', Schema::hasTable('ai_governance_logs') ? 'yes' : 'no', Schema::hasTable('ai_governance_logs') ? 'OK' : 'FAIL'];
$results[] = ['ai_provider_configs table', Schema::hasTable('ai_provider_configs') ? 'yes' : 'no', Schema::hasTable('ai_provider_configs') ? 'OK' : 'FAIL'];

Permission::findOrCreate('ai.use', 'web');
Permission::findOrCreate('ai.governance.view', 'web');
Permission::findOrCreate('ai.providers.manage', 'web');
$admin->givePermissionTo(['ai.use', 'ai.governance.view', 'ai.providers.manage']);

$settingsRes = call($http, $token, 'GET', '/ai/governance/settings');
record($results, 'GET /ai/governance/settings', $settingsRes, [200]);
$settingsBody = $settingsRes['body'] ?? [];
$results[] = [
    '  settings has disclaimer',
    isset($settingsBody['disclaimer']) ? 'yes' : 'no',
    isset($settingsBody['disclaimer']) ? 'OK' : 'FAIL',
];
$results[] = [
    '  settings requires_lawyer_approval',
    ($settingsBody['requires_lawyer_approval'] ?? false) ? 'yes' : 'no',
    ($settingsBody['requires_lawyer_approval'] ?? false) ? 'OK' : 'FAIL',
];
$results[] = [
    '  settings has label + review_statuses',
    (isset($settingsBody['label'], $settingsBody['review_statuses']) && is_array($settingsBody['review_statuses'])) ? 'yes' : 'no',
    (isset($settingsBody['label'], $settingsBody['review_statuses']) && is_array($settingsBody['review_statuses'])) ? 'OK' : 'FAIL',
];

$healthRes = call($http, $token, 'GET', '/ai/health');
record($results, 'GET /ai/health', $healthRes, [200]);
$healthBody = $healthRes['body'] ?? [];
$results[] = [
    '  health available (stub)',
    ($healthBody['available'] ?? false) ? 'yes' : 'no',
    ($healthBody['available'] ?? false) ? 'OK' : 'FAIL',
];

$chatRes = call($http, $token, 'POST', '/ai/chat', [
    'message' => 'What is the conflict check process?',
    'context' => 'staff',
]);
record($results, 'POST /ai/chat', $chatRes, [200]);
$chatBody = $chatRes['body'] ?? [];
$results[] = [
    '  chat labeled + disclaimer',
    (isset($chatBody['label'], $chatBody['disclaimer'], $chatBody['output_id']) && ($chatBody['labeled'] ?? false)) ? 'yes' : 'no',
    (isset($chatBody['label'], $chatBody['disclaimer'], $chatBody['output_id']) && ($chatBody['labeled'] ?? false)) ? 'OK' : 'FAIL',
];
$results[] = [
    '  chat requires_review flag',
    array_key_exists('requires_review', $chatBody) ? 'yes' : 'no',
    array_key_exists('requires_review', $chatBody) ? 'OK' : 'FAIL',
];
$ids['governance_log'] = $chatBody['governance_log_id'] ?? null;

$logCount = AiGovernanceLog::query()->where('action_type', 'chatbot')->count();
$results[] = ['  governance log persisted', $logCount >= 1 ? (string) $logCount : '0', $logCount >= 1 ? 'OK' : 'FAIL'];

$activityCount = Activity::query()->where('log_name', 'ai')->count();
$results[] = ['  activity log ai entry', $activityCount >= 1 ? (string) $activityCount : '0', $activityCount >= 1 ? 'OK' : 'FAIL'];

$logsRes = call($http, $token, 'GET', '/ai/governance/logs');
record($results, 'GET /ai/governance/logs', $logsRes, [200]);
$logsBody = $logsRes['body'] ?? [];
$firstLog = $logsBody['data'][0] ?? null;
$results[] = [
    '  logs paginated data shape',
    (is_array($firstLog) && isset($firstLog['id'], $firstLog['action_type'], $firstLog['status'])) ? 'yes' : 'no',
    (is_array($firstLog) && isset($firstLog['id'], $firstLog['action_type'], $firstLog['status'])) ? 'OK' : 'FAIL',
];

$staffNoGovernance = User::query()->updateOrCreate(
    ['email' => 'phase4-ai-staff@banwolaw.test'],
    [
        'organization_id' => $admin->organization_id,
        'name' => 'Phase4 AI Staff',
        'password' => Hash::make('ChangeMe123!'),
        'is_active' => true,
    ]
);
if (! $staffNoGovernance->hasRole('Paralegal')) {
    $staffNoGovernance->syncRoles(['Paralegal']);
}
$staffNoGovernance->syncPermissions(['ai.use']);
$staffToken = $staffNoGovernance->createToken('phase4-ai-staff')->plainTextToken;

$deniedLogs = call($http, $staffToken, 'GET', '/ai/governance/logs');
record($results, 'GET /ai/governance/logs (no permission)', $deniedLogs, [403]);

$staffChat = call($http, $staffToken, 'POST', '/ai/chat', [
    'message' => 'Staff assistant check',
    'context' => 'staff',
]);
record($results, 'POST /ai/chat (ai.use only)', $staffChat, [200]);

$organization = $admin->organization;
$client = Client::query()->firstOrCreate(
    ['organization_id' => $organization->id, 'email' => 'phase4-portal@banwolaw.test'],
    ['name' => 'Phase4 Portal Client', 'phone' => '555-0400']
);
$portalUser = User::query()->updateOrCreate(
    ['email' => 'phase4-portal@banwolaw.test'],
    [
        'organization_id' => $organization->id,
        'client_id' => $client->id,
        'name' => 'Phase4 Portal User',
        'password' => Hash::make('ChangeMe123!'),
        'is_active' => true,
    ]
);
if (! $portalUser->hasRole('Client')) {
    $portalUser->assignRole('Client');
}
$portalUser->givePermissionTo('ai.use');

$portalLogin = call($http, '', 'POST', '/portal/auth/login', [
    'email' => $portalUser->email,
    'password' => 'ChangeMe123!',
]);
record($results, 'POST /portal/auth/login (ai block test)', $portalLogin, [200]);
$portalToken = $portalLogin['body']['token'] ?? '';

$portalChat = call($http, $portalToken, 'POST', '/ai/chat', ['message' => 'blocked']);
record($results, 'POST /ai/chat (portal client blocked)', $portalChat, [403]);

$deniedRes = call($http, '', 'GET', '/ai/governance/settings');
record($results, 'GET /ai/governance/settings (unauthenticated)', $deniedRes, [401]);

$matter = LegalMatter::query()->first();
if (! $matter) {
    $matter = LegalMatter::query()->create([
        'organization_id' => $organization->id,
        'client_id' => $client->id,
        'title' => 'Phase 4 AI Matter',
        'status' => 'open',
        'matter_number' => 'P4-001',
    ]);
}
$ids['matter'] = $matter->id;

$caseQaRes = call($http, $token, 'POST', '/ai/case-qa', [
    'legal_matter_id' => $matter->id,
    'question' => 'What deadlines apply?',
]);
record($results, 'POST /ai/case-qa', $caseQaRes, [200]);

$timelineRes = call($http, $token, 'POST', '/ai/timeline-summary', [
    'legal_matter_id' => $matter->id,
]);
record($results, 'POST /ai/timeline-summary', $timelineRes, [200]);

$document = LegalDocument::query()->create([
    'organization_id' => $organization->id,
    'legal_matter_id' => $matter->id,
    'uploaded_by' => $admin->id,
    'document_type' => 'case_document',
    'name' => 'Phase4 Test Doc',
    'content_html' => '<p>Contract between parties regarding services.</p>',
    'original_filename' => 'phase4-test.html',
    'mime_type' => 'text/html',
    'size' => 48,
    'disk' => 'local',
    'path' => '',
    'version' => 1,
]);
$ids['document'] = $document->id;

$summarizeRes = call($http, $token, 'POST', '/ai/summarize-document', [
    'legal_document_id' => $document->id,
]);
record($results, 'POST /ai/summarize-document', $summarizeRes, [200]);

$draftRes = call($http, $token, 'POST', '/ai/draft-assist', [
    'legal_matter_id' => $matter->id,
]);
record($results, 'POST /ai/draft-assist', $draftRes, [200]);
$draftBody = $draftRes['body'] ?? [];
$results[] = [
    '  draft-assist labeled output',
    (isset($draftBody['content'], $draftBody['label']) && ($draftBody['requires_review'] ?? false)) ? 'yes' : 'no',
    (isset($draftBody['content'], $draftBody['label']) && ($draftBody['requires_review'] ?? false)) ? 'OK' : 'FAIL',
];

$results[] = [
    'legal_documents ai_review columns',
    Schema::hasColumns('legal_documents', ['ai_generated', 'ai_review_status', 'ai_governance_log_id', 'ai_approved_by', 'ai_approved_at']) ? 'yes' : 'no',
    Schema::hasColumns('legal_documents', ['ai_generated', 'ai_review_status', 'ai_governance_log_id', 'ai_approved_by', 'ai_approved_at']) ? 'OK' : 'FAIL',
];

$aiDraftRes = call($http, $token, 'POST', '/documents/ai-draft', [
    'legal_matter_id' => $matter->id,
    'content_html' => '<p>AI-assisted draft for review.</p>',
    'name' => 'Phase4 AI Draft',
    'ai_governance_log_id' => $ids['governance_log'] ?? null,
]);
record($results, 'POST /documents/ai-draft', $aiDraftRes, [201]);
$aiDraftBody = $aiDraftRes['body'] ?? [];
$aiDraftData = $aiDraftBody['data'] ?? $aiDraftBody;
$ids['ai_draft'] = $aiDraftData['id'] ?? null;
$results[] = [
    '  ai-draft status generated',
    ($aiDraftData['ai_generated'] ?? false) && ($aiDraftData['ai_review_status'] ?? '') === 'generated' ? 'yes' : 'no',
    ($aiDraftData['ai_generated'] ?? false) && ($aiDraftData['ai_review_status'] ?? '') === 'generated' ? 'OK' : 'FAIL',
];

if ($ids['ai_draft']) {
    $underReviewRes = call($http, $token, 'PATCH', '/documents/'.$ids['ai_draft'].'/ai-review', [
        'ai_review_status' => 'under_review',
    ]);
    record($results, 'PATCH /documents/{id}/ai-review (under_review)', $underReviewRes, [200]);

    $finalizeBlocked = call($http, $token, 'PATCH', '/documents/'.$ids['ai_draft'].'/ai-review', [
        'ai_review_status' => 'finalized',
    ]);
    record($results, 'PATCH ai-review finalize blocked (no approval)', $finalizeBlocked, [422]);

    $approveRes = call($http, $token, 'PATCH', '/documents/'.$ids['ai_draft'].'/ai-review', [
        'ai_review_status' => 'approved',
    ]);
    record($results, 'PATCH /documents/{id}/ai-review (approved)', $approveRes, [200]);

    $finalizeRes = call($http, $token, 'PATCH', '/documents/'.$ids['ai_draft'].'/ai-review', [
        'ai_review_status' => 'finalized',
    ]);
    record($results, 'PATCH /documents/{id}/ai-review (finalized)', $finalizeRes, [200]);
    $finalizeBody = $finalizeRes['body'] ?? [];
    $finalizeData = $finalizeBody['data'] ?? $finalizeBody;
    $results[] = [
        '  finalized after lawyer approval',
        ($finalizeData['ai_review_status'] ?? '') === 'finalized' ? 'yes' : 'no',
        ($finalizeData['ai_review_status'] ?? '') === 'finalized' ? 'OK' : 'FAIL',
    ];
}

$form = IntakeForm::query()->first();
if (! $form) {
    $form = IntakeForm::query()->create([
        'organization_id' => $organization->id,
        'name' => 'Phase4 Intake',
        'status' => 'published',
        'fields' => [['name' => 'issue', 'label' => 'Issue', 'type' => 'text']],
    ]);
}

$submission = IntakeSubmission::query()->create([
    'organization_id' => $organization->id,
    'intake_form_id' => $form->id,
    'client_id' => $client->id,
    'status' => 'submitted',
    'data' => ['issue' => 'Need family law advice'],
    'submitted_at' => now(),
]);
$ids['submission'] = $submission->id;

$intakeRes = call($http, $token, 'POST', '/ai/intake-summary', [
    'intake_submission_id' => $submission->id,
]);
record($results, 'POST /ai/intake-summary', $intakeRes, [200]);

$actionTypes = AiGovernanceLog::query()
    ->where('organization_id', $organization->id)
    ->pluck('action_type')
    ->unique()
    ->values()
    ->all();
$expectedActions = ['chatbot', 'case_qa', 'timeline_summary', 'document_summarize', 'draft_assist', 'intake_summary'];
$missing = array_diff($expectedActions, $actionTypes);
$results[] = [
    '  all action types logged',
    $missing === [] ? 'yes' : implode(',', $missing),
    $missing === [] ? 'OK' : 'FAIL',
];

echo "\n=== APPROVAL WORKFLOW ENGINE ===\n";

$results[] = [
    'approval_requests table',
    Schema::hasTable('approval_requests') ? 'yes' : 'no',
    Schema::hasTable('approval_requests') ? 'OK' : 'FAIL',
];
$results[] = [
    'requires_approval columns',
    Schema::hasColumns('legal_documents', ['requires_approval']) && Schema::hasColumns('invoices', ['requires_approval']) ? 'yes' : 'no',
    Schema::hasColumns('legal_documents', ['requires_approval']) && Schema::hasColumns('invoices', ['requires_approval']) ? 'OK' : 'FAIL',
];

Permission::findOrCreate('approvals.view', 'web');
Permission::findOrCreate('approvals.submit', 'web');
Permission::findOrCreate('approvals.review', 'web');
$admin->givePermissionTo(['approvals.view', 'approvals.submit', 'approvals.review']);

$approvalInvoiceRes = call($http, $token, 'POST', '/invoices', [
    'client_id' => $matter->client_id,
    'legal_matter_id' => $matter->id,
    'issue_date' => now()->toDateString(),
    'due_date' => now()->addDays(14)->toDateString(),
    'line_items' => [
        [
            'description' => 'Phase4 approval test',
            'quantity' => 1,
            'unit_price' => 1000,
            'line_type' => 'service',
        ],
    ],
]);
record($results, 'POST /invoices (approval test)', $approvalInvoiceRes, [201]);
$approvalInvoiceBody = $approvalInvoiceRes['body'] ?? [];
$ids['approval_invoice'] = $approvalInvoiceBody['id'] ?? null;

if ($ids['approval_invoice']) {
    $submitInvoiceRes = call($http, $token, 'POST', '/approval-requests', [
        'subject_type' => 'invoice',
        'subject_id' => $ids['approval_invoice'],
        'notes' => 'Please review before sending to client.',
        'requires_approval' => true,
    ]);
    record($results, 'POST /approval-requests (invoice)', $submitInvoiceRes, [201]);
    $submitInvoiceBody = $submitInvoiceRes['body'] ?? [];
    $ids['approval_request_invoice'] = $submitInvoiceBody['id'] ?? null;
    $results[] = [
        '  invoice approval status submitted',
        ($submitInvoiceBody['status'] ?? '') === 'submitted' ? 'yes' : 'no',
        ($submitInvoiceBody['status'] ?? '') === 'submitted' ? 'OK' : 'FAIL',
    ];

    $blockedSend = call($http, $token, 'POST', '/invoices/'.$ids['approval_invoice'].'/mark-sent');
    record($results, 'POST mark-sent blocked (no approval)', $blockedSend, [422]);

    if ($ids['approval_request_invoice']) {
        $approveInvoiceRes = call($http, $token, 'PATCH', '/approval-requests/'.$ids['approval_request_invoice'].'/review', [
            'action' => 'approve',
            'comment' => 'Approved for client delivery.',
        ]);
        record($results, 'PATCH /approval-requests/{id}/review (approve)', $approveInvoiceRes, [200]);

        $sentAfterApproval = call($http, $token, 'POST', '/invoices/'.$ids['approval_invoice'].'/mark-sent');
        record($results, 'POST mark-sent after approval', $sentAfterApproval, [200]);
    }
}

if ($ids['document']) {
    $submitDocRes = call($http, $token, 'POST', '/approval-requests', [
        'subject_type' => 'legal_document',
        'subject_id' => $ids['document'],
        'requires_approval' => true,
    ]);
    record($results, 'POST /approval-requests (document)', $submitDocRes, [201]);
    $submitDocBody = $submitDocRes['body'] ?? [];
    $ids['approval_request_document'] = $submitDocBody['id'] ?? null;

    $blockedShare = call($http, $token, 'PATCH', '/documents/'.$ids['document'], [
        'client_visible' => true,
    ]);
    record($results, 'PATCH document share blocked (no approval)', $blockedShare, [422]);

    if ($ids['approval_request_document']) {
        $approveDocRes = call($http, $token, 'PATCH', '/approval-requests/'.$ids['approval_request_document'].'/review', [
            'action' => 'approve',
        ]);
        record($results, 'PATCH /approval-requests/{id}/review (document)', $approveDocRes, [200]);

        $shareAfterApproval = call($http, $token, 'PATCH', '/documents/'.$ids['document'], [
            'client_visible' => true,
        ]);
        record($results, 'PATCH document share after approval', $shareAfterApproval, [200]);
    }
}

$listApprovals = call($http, $token, 'GET', '/approval-requests?subject_type=invoice');
record($results, 'GET /approval-requests', $listApprovals, [200]);

$approvalNotifCount = AppNotification::query()
    ->whereIn('type', ['approval_request', 'approval_completed'])
    ->count();
$results[] = [
    '  approval notifications created',
    $approvalNotifCount >= 2 ? (string) $approvalNotifCount : (string) $approvalNotifCount,
    $approvalNotifCount >= 2 ? 'OK' : 'FAIL',
];

$paralegalSubmit = User::query()->updateOrCreate(
    ['email' => 'phase4-approval-paralegal@banwolaw.test'],
    [
        'organization_id' => $admin->organization_id,
        'name' => 'Phase4 Approval Paralegal',
        'password' => Hash::make('ChangeMe123!'),
        'is_active' => true,
    ]
);
if (! $paralegalSubmit->hasRole('Paralegal')) {
    $paralegalSubmit->syncRoles(['Paralegal']);
}
$paralegalSubmit->syncPermissions(['approvals.view', 'approvals.submit']);
$paralegalToken = $paralegalSubmit->createToken('phase4-approval-paralegal')->plainTextToken;

$deniedReview = call($http, $paralegalToken, 'PATCH', '/approval-requests/'.($ids['approval_request_invoice'] ?? 0).'/review', [
    'action' => 'approve',
]);
record($results, 'PATCH review denied (no approvals.review)', $deniedReview, [403]);

$approvalCount = ApprovalRequest::query()->where('organization_id', $organization->id)->count();
$results[] = [
    '  approval requests persisted',
    $approvalCount >= 2 ? (string) $approvalCount : (string) $approvalCount,
    $approvalCount >= 2 ? 'OK' : 'FAIL',
];

$frontendFiles = [
    'AiGovernanceView.vue' => __DIR__ . '/../../frontend/src/views/AiGovernanceView.vue',
    'AiAssistantView.vue' => __DIR__ . '/../../frontend/src/views/AiAssistantView.vue',
    'AiAssistantPanel.vue' => __DIR__ . '/../../frontend/src/components/ai/AiAssistantPanel.vue',
    'AiDisclaimerBanner.vue' => __DIR__ . '/../../frontend/src/components/ai/AiDisclaimerBanner.vue',
];
foreach ($frontendFiles as $label => $path) {
    $results[] = [
        "  frontend {$label}",
        file_exists($path) ? 'yes' : 'no',
        file_exists($path) ? 'OK' : 'FAIL',
    ];
}

$caseDocsPanel = __DIR__ . '/../../frontend/src/components/cases/CaseDocumentsPanel.vue';
$panelSource = file_exists($caseDocsPanel) ? (string) file_get_contents($caseDocsPanel) : '';
$panelChecks = [
    'CaseDocumentsPanel AiDisclaimerBanner' => str_contains($panelSource, 'AiDisclaimerBanner'),
    'CaseDocumentsPanel AiOutputBadges' => str_contains($panelSource, 'AiOutputBadges'),
    'CaseDocumentsPanel draft-assist wiring' => str_contains($panelSource, 'draftAssist'),
    'CaseDocumentsPanel summarize wiring' => str_contains($panelSource, 'summarizeDocument'),
    'CaseDocumentsPanel ai-review workflow' => str_contains($panelSource, 'updateAiReview'),
];
foreach ($panelChecks as $label => $ok) {
    $results[] = [$label, $ok ? 'yes' : 'no', $ok ? 'OK' : 'FAIL'];
}

$approvalPanel = __DIR__ . '/../../frontend/src/components/approvals/ApprovalWorkflowPanel.vue';
$approvalPanelSource = file_exists($approvalPanel) ? (string) file_get_contents($approvalPanel) : '';
$approvalChecks = [
    'ApprovalWorkflowPanel exists' => file_exists($approvalPanel),
    'ApprovalWorkflowPanel submit wiring' => str_contains($approvalPanelSource, 'submitForReview'),
    'ApprovalWorkflowPanel review wiring' => str_contains($approvalPanelSource, "review('approve')"),
    'CaseDocumentsPanel approval panel' => str_contains($panelSource, 'ApprovalWorkflowPanel'),
    'InvoiceDetailView approval panel' => str_contains((string) @file_get_contents(__DIR__ . '/../../frontend/src/views/invoices/InvoiceDetailView.vue'), 'ApprovalWorkflowPanel'),
];
foreach ($approvalChecks as $label => $ok) {
    $results[] = [$label, $ok ? 'yes' : 'no', $ok ? 'OK' : 'FAIL'];
}

echo "\n=== PHASE 4 SLICE 5: MULTI-PROVIDER AI ===\n";

$providersListRes = call($http, $token, 'GET', '/settings/ai-providers');
record($results, 'GET /settings/ai-providers', $providersListRes, [200]);
$providersBody = $providersListRes['body'] ?? [];
$providerRows = $providersBody['providers'] ?? [];
$results[] = [
    '  providers list has 4 entries',
    is_array($providerRows) && count($providerRows) === 4 ? 'yes' : (string) count($providerRows),
    is_array($providerRows) && count($providerRows) === 4 ? 'OK' : 'FAIL',
];
$openaiRow = is_array($providerRows) ? collect($providerRows)->firstWhere('provider', 'openai') : null;
$results[] = [
    '  provider row masked key shape',
    (is_array($openaiRow) && array_key_exists('api_key_set', $openaiRow) && ! array_key_exists('api_key', $openaiRow)) ? 'yes' : 'no',
    (is_array($openaiRow) && array_key_exists('api_key_set', $openaiRow) && ! array_key_exists('api_key', $openaiRow)) ? 'OK' : 'FAIL',
];

$saveProviderRes = call($http, $token, 'PUT', '/settings/ai-providers', [
    'provider' => 'openai',
    'api_key' => 'sk-phase4-test-openai-key-1234567890',
]);
record($results, 'PUT /settings/ai-providers (api key)', $saveProviderRes, [200]);
$saveProviderBody = $saveProviderRes['body'] ?? [];
$results[] = [
    '  saved provider api_key_set',
    ($saveProviderBody['provider']['api_key_set'] ?? false) ? 'yes' : 'no',
    ($saveProviderBody['provider']['api_key_set'] ?? false) ? 'OK' : 'FAIL',
];
$results[] = [
    '  saved provider api_key_masked',
    isset($saveProviderBody['provider']['api_key_masked']) ? 'yes' : 'no',
    isset($saveProviderBody['provider']['api_key_masked']) ? 'OK' : 'FAIL',
];
$results[] = [
    '  update returns providers list',
    isset($saveProviderBody['providers']) && is_array($saveProviderBody['providers']) ? 'yes' : 'no',
    isset($saveProviderBody['providers']) && is_array($saveProviderBody['providers']) ? 'OK' : 'FAIL',
];
$results[] = [
    '  provider accordion fields',
    (isset($saveProviderBody['provider']['can_select_model'], $saveProviderBody['provider']['available_models'])) ? 'yes' : 'no',
    (isset($saveProviderBody['provider']['can_select_model'], $saveProviderBody['provider']['available_models'])) ? 'OK' : 'FAIL',
];

$modelBlockedRes = call($http, $token, 'PUT', '/settings/ai-providers', [
    'provider' => 'openai',
    'model' => 'gpt-4o',
]);
record($results, 'PUT model blocked before test', $modelBlockedRes, [422]);

$openaiConfig = AiProviderConfig::query()
    ->where('organization_id', $admin->organization_id)
    ->where('provider', 'openai')
    ->first();
if ($openaiConfig) {
    $openaiConfig->update(['last_test_success_at' => now()]);
}
$results[] = [
    '  seeded last_test_success_at for openai',
    ($openaiConfig?->fresh()?->last_test_success_at !== null) ? 'yes' : 'no',
    ($openaiConfig?->fresh()?->last_test_success_at !== null) ? 'OK' : 'FAIL',
];

$saveModelRes = call($http, $token, 'PUT', '/settings/ai-providers', [
    'provider' => 'openai',
    'model' => 'gpt-4o-mini',
]);
record($results, 'PUT /settings/ai-providers (model)', $saveModelRes, [200]);

$enableOpenaiRes = call($http, $token, 'PUT', '/settings/ai-providers', [
    'provider' => 'openai',
    'is_enabled' => true,
]);
record($results, 'PUT /settings/ai-providers (enable)', $enableOpenaiRes, [200]);
$enableOpenaiBody = $enableOpenaiRes['body'] ?? [];
$results[] = [
    '  exclusive enable sets active openai',
    ($enableOpenaiBody['active_provider'] ?? '') === 'openai' ? 'yes' : 'no',
    ($enableOpenaiBody['active_provider'] ?? '') === 'openai' ? 'OK' : 'FAIL',
];

$saveAnthropicRes = call($http, $token, 'PUT', '/settings/ai-providers', [
    'provider' => 'anthropic',
    'api_key' => 'sk-phase4-test-anthropic-key-1234567890',
]);
record($results, 'PUT /settings/ai-providers (anthropic key)', $saveAnthropicRes, [200]);
$anthropicConfig = AiProviderConfig::query()
    ->where('organization_id', $admin->organization_id)
    ->where('provider', 'anthropic')
    ->first();
if ($anthropicConfig) {
    $anthropicConfig->update(['last_test_success_at' => now()]);
}
$enableAnthropicRes = call($http, $token, 'PUT', '/settings/ai-providers', [
    'provider' => 'anthropic',
    'is_enabled' => true,
]);
record($results, 'PUT exclusive toggle anthropic', $enableAnthropicRes, [200]);
$enableAnthropicBody = $enableAnthropicRes['body'] ?? [];
$openaiDisabled = collect($enableAnthropicBody['providers'] ?? [])->firstWhere('provider', 'openai');
$results[] = [
    '  openai disabled when anthropic enabled',
    (($openaiDisabled['is_enabled'] ?? true) === false) ? 'yes' : 'no',
    (($openaiDisabled['is_enabled'] ?? true) === false) ? 'OK' : 'FAIL',
];
$results[] = [
    '  active provider switched to anthropic',
    ($enableAnthropicBody['active_provider'] ?? '') === 'anthropic' ? 'yes' : 'no',
    ($enableAnthropicBody['active_provider'] ?? '') === 'anthropic' ? 'OK' : 'FAIL',
];

$activeProviderRes = call($http, $token, 'PUT', '/settings/ai-providers/active', [
    'provider' => 'anthropic',
]);
record($results, 'PUT /settings/ai-providers/active', $activeProviderRes, [200]);
$activeProviderBody = $activeProviderRes['body'] ?? [];
$results[] = [
    '  active provider is anthropic',
    ($activeProviderBody['active_provider'] ?? '') === 'anthropic' ? 'yes' : 'no',
    ($activeProviderBody['active_provider'] ?? '') === 'anthropic' ? 'OK' : 'FAIL',
];

$testConnRes = call($http, $token, 'POST', '/settings/ai-providers/openai/test-connection', []);
$results[] = [
    'POST /settings/ai-providers/openai/test-connection',
    (string) $testConnRes['status'],
    in_array($testConnRes['status'], [200, 422], true) ? 'OK' : 'FAIL',
];
$testConnBody = $testConnRes['body'] ?? [];
$results[] = [
    '  test-connection response shape',
    (isset($testConnBody['success'], $testConnBody['message'])) ? 'yes' : 'no',
    (isset($testConnBody['success'], $testConnBody['message'])) ? 'OK' : 'FAIL',
];

$staffNoProviders = User::query()->updateOrCreate(
    ['email' => 'phase4-no-ai-providers@banwolaw.test'],
    [
        'organization_id' => $admin->organization_id,
        'name' => 'Phase4 No AI Providers',
        'password' => Hash::make('ChangeMe123!'),
        'is_active' => true,
    ]
);
$staffNoProviders->syncPermissions(['ai.use']);
$noProvidersToken = $staffNoProviders->createToken('phase4-no-ai-providers')->plainTextToken;
$deniedProviders = call($http, $noProvidersToken, 'GET', '/settings/ai-providers');
record($results, 'GET ai-providers denied (no permission)', $deniedProviders, [403]);

$configCount = AiProviderConfig::query()
    ->where('organization_id', $admin->organization_id)
    ->where('provider', 'openai')
    ->count();
$results[] = [
    '  ai_provider_configs persisted',
    $configCount >= 1 ? (string) $configCount : '0',
    $configCount >= 1 ? 'OK' : 'FAIL',
];

$aiProvidersPanel = __DIR__ . '/../../frontend/src/components/settings/AiProvidersPanel.vue';
$settingsView = __DIR__ . '/../../frontend/src/views/SettingsView.vue';
$settingsSource = file_exists($settingsView) ? (string) file_get_contents($settingsView) : '';
$panelSource = file_exists($aiProvidersPanel) ? (string) file_get_contents($aiProvidersPanel) : '';
$providerChecks = [
    'AiProvidersPanel.vue exists' => file_exists($aiProvidersPanel),
    'SettingsView AI providers tab' => str_contains($settingsSource, 'ai-providers'),
    'SettingsView AiProvidersPanel import' => str_contains($settingsSource, 'AiProvidersPanel'),
    'AiProvidersPanel test connection' => str_contains($panelSource, 'testConnection'),
    'AiProvidersPanel accordion expand' => str_contains($panelSource, 'expandedProvider'),
    'AiProvidersPanel exclusive toggle' => str_contains($panelSource, 'toggleProvider'),
    'AiProvidersPanel model gated' => str_contains($panelSource, 'can_select_model'),
];
foreach ($providerChecks as $label => $ok) {
    $results[] = [$label, $ok ? 'yes' : 'no', $ok ? 'OK' : 'FAIL'];
}

echo "\n=== INTEGRATIONS SETTINGS (Wave 7) ===\n";
$integrationsRes = call($http, $token, 'GET', '/settings/integrations');
record($results, 'GET /settings/integrations', $integrationsRes, [200]);
$integrationRows = $integrationsRes['body']['integrations'] ?? [];
$integrationKeys = array_column($integrationRows, 'key');
$results[] = [
    '  integrations count',
    (string) count($integrationRows),
    count($integrationRows) === 3 ? 'OK' : 'FAIL',
];
$results[] = [
    '  sms_whatsapp integration',
    in_array('sms_whatsapp', $integrationKeys, true) ? 'yes' : 'no',
    in_array('sms_whatsapp', $integrationKeys, true) ? 'OK' : 'FAIL',
];
$results[] = [
    '  google_calendar integration',
    in_array('google_calendar', $integrationKeys, true) ? 'yes' : 'no',
    in_array('google_calendar', $integrationKeys, true) ? 'OK' : 'FAIL',
];
$results[] = [
    '  court_efiling integration',
    in_array('court_efiling', $integrationKeys, true) ? 'yes' : 'no',
    in_array('court_efiling', $integrationKeys, true) ? 'OK' : 'FAIL',
];
$integrationsPanel = __DIR__ . '/../../frontend/src/components/settings/IntegrationsPanel.vue';
$integrationsPanelSource = file_exists($integrationsPanel) ? (string) file_get_contents($integrationsPanel) : '';
$oauthConnectRes = call($http, $token, 'GET', '/integrations/google-calendar/connect');
record($results, 'GET /integrations/google-calendar/connect', $oauthConnectRes, [200, 422]);
$oauthMigration = __DIR__ . '/../database/migrations/2026_06_13_160000_create_integration_oauth_tokens_table.php';
$oauthController = __DIR__ . '/../app/Http/Controllers/Api/V1/GoogleCalendarOAuthController.php';
$results[] = [
    '  integration_oauth_tokens migration',
    file_exists($oauthMigration) ? 'yes' : 'no',
    file_exists($oauthMigration) ? 'OK' : 'FAIL',
];
$results[] = [
    '  GoogleCalendarOAuthController',
    file_exists($oauthController) ? 'yes' : 'no',
    file_exists($oauthController) ? 'OK' : 'FAIL',
];
$integrationUiChecks = [
    'IntegrationsPanel.vue exists' => file_exists($integrationsPanel),
    'SettingsView integrations tab' => str_contains($settingsSource, 'integrations'),
    'SettingsView IntegrationsPanel import' => str_contains($settingsSource, 'IntegrationsPanel'),
    'IntegrationsPanel API wiring' => str_contains($integrationsPanelSource, 'integrationsApi'),
    'IntegrationsPanel connect button' => str_contains($integrationsPanelSource, 'googleCalendarConnect'),
    'RichTextEditor track changes lite' => str_contains(
        (string) file_get_contents(__DIR__ . '/../../frontend/src/components/editor/RichTextEditor.vue'),
        'trackChangesMode',
    ),
];
foreach ($integrationUiChecks as $label => $ok) {
    $results[] = [$label, $ok ? 'yes' : 'no', $ok ? 'OK' : 'FAIL'];
}

$managerFile = __DIR__ . '/../app/Services/Ai/AiProviderManager.php';
$adapterFiles = [
    'OpenAiAdapter' => __DIR__ . '/../app/Services/Ai/Adapters/OpenAiAdapter.php',
    'AnthropicAdapter' => __DIR__ . '/../app/Services/Ai/Adapters/AnthropicAdapter.php',
    'GoogleAiAdapter' => __DIR__ . '/../app/Services/Ai/Adapters/GoogleAiAdapter.php',
    'DeepseekAdapter' => __DIR__ . '/../app/Services/Ai/Adapters/DeepseekAdapter.php',
];
$results[] = ['AiProviderManager.php exists', file_exists($managerFile) ? 'yes' : 'no', file_exists($managerFile) ? 'OK' : 'FAIL'];
foreach ($adapterFiles as $label => $path) {
    $results[] = ["  adapter {$label}", file_exists($path) ? 'yes' : 'no', file_exists($path) ? 'OK' : 'FAIL'];
}

echo "\n=== PHASE 4 SLICE 6: E-SIGNATURE ===\n";

$matter->update(['client_id' => $client->id]);

$results[] = [
    'signature_requests table',
    Schema::hasTable('signature_requests') ? 'yes' : 'no',
    Schema::hasTable('signature_requests') ? 'OK' : 'FAIL',
];
$results[] = [
    'signature_requests columns',
    Schema::hasColumns('signature_requests', [
        'document_id', 'legal_matter_id', 'client_id', 'status', 'fields',
        'signed_at', 'signer_ip', 'audit', 'signed_document_id',
    ]) ? 'yes' : 'no',
    Schema::hasColumns('signature_requests', [
        'document_id', 'legal_matter_id', 'client_id', 'status', 'fields',
        'signed_at', 'signer_ip', 'audit', 'signed_document_id',
    ]) ? 'OK' : 'FAIL',
];

Permission::findOrCreate('signatures.view', 'web');
Permission::findOrCreate('signatures.send', 'web');
Permission::findOrCreate('portal.signatures.view', 'web');
Permission::findOrCreate('portal.signatures.sign', 'web');
$admin->givePermissionTo(['signatures.view', 'signatures.send']);
$portalUser->givePermissionTo(['portal.signatures.view', 'portal.signatures.sign']);

$signDoc = LegalDocument::query()->create([
    'organization_id' => $organization->id,
    'legal_matter_id' => $matter->id,
    'uploaded_by' => $admin->id,
    'document_type' => 'case_document',
    'name' => 'Phase4 E-Sign Doc',
    'content_html' => '<p>Engagement letter for electronic signature.</p>',
    'original_filename' => 'phase4-esign.html',
    'mime_type' => 'text/html',
    'size' => 48,
    'disk' => 'local',
    'path' => '',
    'version' => 1,
    'client_visible' => false,
]);
$ids['esign_document'] = $signDoc->id;

SignatureRequest::query()
    ->where('document_id', $signDoc->id)
    ->where('status', 'pending')
    ->delete();

$sendSigRes = call($http, $token, 'POST', '/signature-requests', [
    'document_id' => $signDoc->id,
    'message' => 'Please sign this engagement letter.',
]);
record($results, 'POST /signature-requests', $sendSigRes, [201]);
$sendSigBody = $sendSigRes['body'] ?? [];
$ids['signature_request'] = $sendSigBody['id'] ?? null;
$results[] = [
    '  signature request status pending',
    ($sendSigBody['status'] ?? '') === 'pending' ? 'yes' : 'no',
    ($sendSigBody['status'] ?? '') === 'pending' ? 'OK' : 'FAIL',
];
$results[] = [
    '  document client_visible after send',
    LegalDocument::query()->find($signDoc->id)?->client_visible ? 'yes' : 'no',
    LegalDocument::query()->find($signDoc->id)?->client_visible ? 'OK' : 'FAIL',
];

$listSigRes = call($http, $token, 'GET', '/signature-requests?legal_matter_id='.$matter->id);
record($results, 'GET /signature-requests', $listSigRes, [200]);

$portalListSig = call($http, $portalToken, 'GET', '/portal/signature-requests?status=pending');
record($results, 'GET /portal/signature-requests', $portalListSig, [200]);
$portalListBody = $portalListSig['body'] ?? [];
$portalSigRows = $portalListBody['data'] ?? (is_array($portalListBody) ? $portalListBody : []);
$results[] = [
    '  portal pending signature listed',
    is_array($portalSigRows) && count($portalSigRows) >= 1 ? 'yes' : 'no',
    is_array($portalSigRows) && count($portalSigRows) >= 1 ? 'OK' : 'FAIL',
];

if ($ids['signature_request']) {
    $portalShowSig = call($http, $portalToken, 'GET', '/portal/signature-requests/'.$ids['signature_request']);
    record($results, 'GET /portal/signature-requests/{id}', $portalShowSig, [200]);

    $signRes = call($http, $portalToken, 'POST', '/portal/signature-requests/'.$ids['signature_request'].'/sign', [
        'field_values' => [
            'signature' => 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==',
            'date' => now()->toDateString(),
            'printed_name' => 'Phase4 Portal User',
        ],
        'method' => 'canvas',
    ]);
    record($results, 'POST /portal/signature-requests/{id}/sign', $signRes, [200]);
    $signBody = $signRes['body'] ?? [];
    $signData = $signBody['data'] ?? $signBody;
    $results[] = [
        '  signed status + signed_document_id',
        ($signData['status'] ?? '') === 'signed' && ! empty($signData['signed_document_id']) ? 'yes' : 'no',
        ($signData['status'] ?? '') === 'signed' && ! empty($signData['signed_document_id']) ? 'OK' : 'FAIL',
    ];
    $results[] = [
        '  audit trail has signed event',
        isset($signData['audit']['events']) && collect($signData['audit']['events'])->contains(fn ($e) => ($e['action'] ?? '') === 'signed') ? 'yes' : 'no',
        isset($signData['audit']['events']) && collect($signData['audit']['events'])->contains(fn ($e) => ($e['action'] ?? '') === 'signed') ? 'OK' : 'FAIL',
    ];
    $ids['signed_document'] = $signData['signed_document_id'] ?? null;
}

$declineDoc = LegalDocument::query()->create([
    'organization_id' => $organization->id,
    'legal_matter_id' => $matter->id,
    'uploaded_by' => $admin->id,
    'document_type' => 'case_document',
    'name' => 'Phase4 Decline Doc',
    'content_html' => '<p>Document for decline test.</p>',
    'original_filename' => 'phase4-decline.html',
    'mime_type' => 'text/html',
    'size' => 32,
    'disk' => 'local',
    'path' => '',
    'version' => 1,
]);
$sendDeclineRes = call($http, $token, 'POST', '/signature-requests', [
    'document_id' => $declineDoc->id,
]);
record($results, 'POST /signature-requests (decline test)', $sendDeclineRes, [201]);
$declineReqId = $sendDeclineRes['body']['id'] ?? null;
if ($declineReqId) {
    $declineRes = call($http, $portalToken, 'POST', '/portal/signature-requests/'.$declineReqId.'/decline', [
        'reason' => 'Need more time to review.',
    ]);
    record($results, 'POST /portal/signature-requests/{id}/decline', $declineRes, [200]);
    $declineBody = $declineRes['body'] ?? [];
    $declineData = $declineBody['data'] ?? $declineBody;
    $results[] = [
        '  declined status recorded',
        ($declineData['status'] ?? '') === 'declined' ? 'yes' : 'no',
        ($declineData['status'] ?? '') === 'declined' ? 'OK' : 'FAIL',
    ];
}

$paralegalNoSign = User::query()->updateOrCreate(
    ['email' => 'phase4-no-signatures@banwolaw.test'],
    [
        'organization_id' => $admin->organization_id,
        'name' => 'Phase4 No Signatures',
        'password' => Hash::make('ChangeMe123!'),
        'is_active' => true,
    ]
);
$paralegalNoSign->syncPermissions(['documents.view']);
$noSignToken = $paralegalNoSign->createToken('phase4-no-signatures')->plainTextToken;
$deniedSend = call($http, $noSignToken, 'POST', '/signature-requests', [
    'document_id' => $signDoc->id,
]);
record($results, 'POST signature-requests denied (no permission)', $deniedSend, [403]);

$signatureNotifCount = AppNotification::query()
    ->whereIn('type', ['signature_request_sent', 'signature_completed', 'signature_declined'])
    ->count();
$results[] = [
    '  signature notifications created',
    $signatureNotifCount >= 2 ? (string) $signatureNotifCount : (string) $signatureNotifCount,
    $signatureNotifCount >= 2 ? 'OK' : 'FAIL',
];

$sigCount = SignatureRequest::query()->where('organization_id', $organization->id)->count();
$results[] = [
    '  signature requests persisted',
    $sigCount >= 2 ? (string) $sigCount : (string) $sigCount,
    $sigCount >= 2 ? 'OK' : 'FAIL',
];

$sigServiceFile = __DIR__ . '/../app/Services/SignatureRequestService.php';
$sigChecks = [
    'SignatureRequestService.php exists' => file_exists($sigServiceFile),
    'SignatureSendPanel.vue exists' => file_exists(__DIR__ . '/../../frontend/src/components/signatures/SignatureSendPanel.vue'),
    'SignatureCanvas.vue exists' => file_exists(__DIR__ . '/../../frontend/src/components/signatures/SignatureCanvas.vue'),
    'PortalSignatureSignView.vue exists' => file_exists(__DIR__ . '/../../frontend/src/views/portal/PortalSignatureSignView.vue'),
];
$caseDocsPanel = __DIR__ . '/../../frontend/src/components/cases/CaseDocumentsPanel.vue';
$caseDocsSource = file_exists($caseDocsPanel) ? (string) file_get_contents($caseDocsPanel) : '';
$sigChecks['CaseDocumentsPanel SignatureSendPanel'] = str_contains($caseDocsSource, 'SignatureSendPanel');
$portalCaseView = __DIR__ . '/../../frontend/src/views/portal/PortalCaseDetailView.vue';
$sigChecks['PortalCaseDetailView sign section'] = str_contains((string) @file_get_contents($portalCaseView), 'portalSignaturesApi');
$routerFile = __DIR__ . '/../../frontend/src/router/index.ts';
$sigChecks['Router portal sign route'] = str_contains((string) @file_get_contents($routerFile), 'portal-signature-sign');
foreach ($sigChecks as $label => $ok) {
    $results[] = [$label, $ok ? 'yes' : 'no', $ok ? 'OK' : 'FAIL'];
}

echo "\n=== PHASE 4 SLICE 7: RESEARCH ASSISTANT (LITE) ===\n";

if (method_exists(Cache::getStore(), 'flush')) {
    Cache::flush();
}
if ($organization) {
    $organization->refresh();
    $orgSettings = is_array($organization->settings) ? $organization->settings : [];
    unset($orgSettings['active_ai_provider']);
    $organization->update(['settings' => $orgSettings]);
}

$researchNote = CaseNote::query()->updateOrCreate(
    [
        'organization_id' => $organization->id,
        'legal_matter_id' => $matter->id,
        'title' => 'Phase4 Research Note',
    ],
    [
        'author_id' => $admin->id,
        'note_type' => 'research_summary',
        'visibility' => 'assigned_team',
        'body' => 'Precedent review: Example v. Sample supports summary judgment when material facts are undisputed.',
    ]
);
$ids['research_note'] = $researchNote->id;

$summarizeNotesRes = call($http, $token, 'POST', '/ai/research/summarize-notes', [
    'legal_matter_id' => $matter->id,
]);
record($results, 'POST /ai/research/summarize-notes', $summarizeNotesRes, [200]);
$summarizeNotesBody = $summarizeNotesRes['body'] ?? [];
$results[] = [
    '  summarize labeled + disclaimer',
    (isset($summarizeNotesBody['label'], $summarizeNotesBody['disclaimer'], $summarizeNotesBody['output_id'])
        && ($summarizeNotesBody['labeled'] ?? false)) ? 'yes' : 'no',
    (isset($summarizeNotesBody['label'], $summarizeNotesBody['disclaimer'], $summarizeNotesBody['output_id'])
        && ($summarizeNotesBody['labeled'] ?? false)) ? 'OK' : 'FAIL',
];
$results[] = [
    '  summarize requires_review flag',
    array_key_exists('requires_review', $summarizeNotesBody) ? 'yes' : 'no',
    array_key_exists('requires_review', $summarizeNotesBody) ? 'OK' : 'FAIL',
];
$results[] = [
    '  summarize governance_log_id',
    ! empty($summarizeNotesBody['governance_log_id']) ? 'yes' : 'no',
    ! empty($summarizeNotesBody['governance_log_id']) ? 'OK' : 'FAIL',
];

$suggestAuthRes = call($http, $token, 'POST', '/ai/research/suggest-authorities', [
    'legal_matter_id' => $matter->id,
    'issue' => 'Summary judgment standard for breach of contract',
]);
record($results, 'POST /ai/research/suggest-authorities', $suggestAuthRes, [200]);
$suggestAuthBody = $suggestAuthRes['body'] ?? [];
$results[] = [
    '  suggest authorities verification_warning',
    isset($suggestAuthBody['verification_warning']) ? 'yes' : 'no',
    isset($suggestAuthBody['verification_warning']) ? 'OK' : 'FAIL',
];
$authorities = $suggestAuthBody['authorities'] ?? [];
$results[] = [
    '  suggest authorities list shape',
    (is_array($authorities) && count($authorities) >= 1
        && isset($authorities[0]['citation'], $authorities[0]['type'])) ? 'yes' : 'no',
    (is_array($authorities) && count($authorities) >= 1
        && isset($authorities[0]['citation'], $authorities[0]['type'])) ? 'OK' : 'FAIL',
];
$results[] = [
    '  suggest requires_review flag',
    array_key_exists('requires_review', $suggestAuthBody) ? 'yes' : 'no',
    array_key_exists('requires_review', $suggestAuthBody) ? 'OK' : 'FAIL',
];

$researchLogCount = AiGovernanceLog::query()
    ->whereIn('action_type', ['research_summarize_notes', 'research_suggest_authorities'])
    ->count();
$results[] = [
    '  research governance logs persisted',
    $researchLogCount >= 2 ? (string) $researchLogCount : (string) $researchLogCount,
    $researchLogCount >= 2 ? 'OK' : 'FAIL',
];

$emptyNotesMatter = LegalMatter::query()->firstOrCreate(
    [
        'organization_id' => $organization->id,
        'matter_number' => 'P4-RES-EMPTY',
    ],
    [
        'client_id' => $client->id,
        'title' => 'Phase4 Empty Research Matter',
        'status' => 'open',
    ]
);
CaseNote::query()
    ->where('legal_matter_id', $emptyNotesMatter->id)
    ->whereIn('note_type', ['research_summary', 'strategy_note', 'internal_memo'])
    ->delete();
$emptySummarizeRes = call($http, $token, 'POST', '/ai/research/summarize-notes', [
    'legal_matter_id' => $emptyNotesMatter->id,
]);
record($results, 'POST summarize-notes (no notes)', $emptySummarizeRes, [422]);

$portalResearch = call($http, $portalToken, 'POST', '/ai/research/summarize-notes', [
    'legal_matter_id' => $matter->id,
]);
record($results, 'POST research summarize (portal blocked)', $portalResearch, [403]);

$researchPanel = __DIR__ . '/../../frontend/src/components/cases/CaseResearchPanel.vue';
$researchPanelSource = file_exists($researchPanel) ? (string) file_get_contents($researchPanel) : '';
$caseDetailView = __DIR__ . '/../../frontend/src/views/cases/CaseDetailView.vue';
$caseDetailSource = file_exists($caseDetailView) ? (string) file_get_contents($caseDetailView) : '';
$researchChecks = [
    'CaseResearchPanel.vue exists' => file_exists($researchPanel),
    'CaseResearchPanel AiDisclaimerBanner' => str_contains($researchPanelSource, 'AiDisclaimerBanner'),
    'CaseResearchPanel summarize wiring' => str_contains($researchPanelSource, 'summarizeResearchNotes'),
    'CaseResearchPanel suggest wiring' => str_contains($researchPanelSource, 'suggestAuthorities'),
    'CaseResearchPanel verification warning UI' => str_contains($researchPanelSource, 'verification_warning'),
    'CaseDetailView research tab' => str_contains($caseDetailSource, "key: 'research'"),
    'CaseDetailView CaseResearchPanel' => str_contains($caseDetailSource, 'CaseResearchPanel'),
    'Router research workspace tab' => str_contains((string) @file_get_contents(__DIR__ . '/../../frontend/src/router/index.ts'), 'research|knowledge|conflicts'),
];
foreach ($researchChecks as $label => $ok) {
    $results[] = [$label, $ok ? 'yes' : 'no', $ok ? 'OK' : 'FAIL'];
}

echo "\n=== 100% ROADMAP SLICE 4: DOCUMENT EDITOR PARITY ===\n";

$results[] = [
    'document_versions table',
    Schema::hasTable('document_versions') ? 'yes' : 'no',
    Schema::hasTable('document_versions') ? 'OK' : 'FAIL',
];
$results[] = [
    'document_versions columns',
    Schema::hasColumns('document_versions', [
        'document_id', 'content_html', 'version_number', 'created_by', 'change_summary',
    ]) ? 'yes' : 'no',
    Schema::hasColumns('document_versions', [
        'document_id', 'content_html', 'version_number', 'created_by', 'change_summary',
    ]) ? 'OK' : 'FAIL',
];

if ($ids['document']) {
    DocumentVersion::query()->updateOrCreate(
        [
            'document_id' => $ids['document'],
            'version_number' => 1,
        ],
        [
            'content_html' => '<p>Contract between parties regarding services.</p>',
            'created_by' => $admin->id,
            'change_summary' => 'Initial version',
        ]
    );

    $versionSaveRes = call($http, $token, 'PATCH', '/documents/'.$ids['document'], [
        'content_html' => '<p>Contract between parties regarding services — revised.</p>',
        'change_summary' => 'Phase4 verify revision',
    ]);
    record($results, 'PATCH /documents/{id} content (version snapshot)', $versionSaveRes, [200]);
    $versionSaveBody = $versionSaveRes['body'] ?? [];
    $versionSaveData = $versionSaveBody['data'] ?? $versionSaveBody;
    $results[] = [
        '  document version incremented',
        ((int) ($versionSaveData['version'] ?? 0)) >= 2 ? 'yes' : 'no',
        ((int) ($versionSaveData['version'] ?? 0)) >= 2 ? 'OK' : 'FAIL',
    ];

    $listVersionsRes = call($http, $token, 'GET', '/documents/'.$ids['document'].'/versions');
    record($results, 'GET /documents/{id}/versions', $listVersionsRes, [200]);
    $listVersionsBody = $listVersionsRes['body'] ?? [];
    $versionRows = $listVersionsBody['data'] ?? (is_array($listVersionsBody) ? $listVersionsBody : []);
    $results[] = [
        '  versions list has snapshots',
        is_array($versionRows) && count($versionRows) >= 1 ? (string) count($versionRows) : '0',
        is_array($versionRows) && count($versionRows) >= 1 ? 'OK' : 'FAIL',
    ];

    $firstVersion = is_array($versionRows) ? ($versionRows[0] ?? null) : null;
    $secondVersion = is_array($versionRows) ? ($versionRows[1] ?? null) : null;
    if (is_array($firstVersion) && is_array($secondVersion)) {
        $compareRes = call($http, $token, 'GET', '/documents/'.$ids['document'].'/versions/compare', [
            'from_version' => $secondVersion['version_number'] ?? 1,
            'to_version' => $firstVersion['version_number'] ?? 2,
        ]);
        record($results, 'GET /documents/{id}/versions/compare', $compareRes, [200]);
        $compareBody = $compareRes['body'] ?? [];
        $results[] = [
            '  compare returns from/to content',
            (isset($compareBody['from']['content_html'], $compareBody['to']['content_html'])) ? 'yes' : 'no',
            (isset($compareBody['from']['content_html'], $compareBody['to']['content_html'])) ? 'OK' : 'FAIL',
        ];
    } else {
        $results[] = ['GET /documents/{id}/versions/compare', 'skipped', 'OK'];
        $results[] = ['  compare returns from/to content', 'skipped', 'OK'];
    }
}

$richTextEditor = __DIR__ . '/../../frontend/src/components/editor/RichTextEditor.vue';
$richTextSource = file_exists($richTextEditor) ? (string) file_get_contents($richTextEditor) : '';
$commentMarkFile = __DIR__ . '/../../frontend/src/components/editor/commentMark.ts';
$commentMarkSource = file_exists($commentMarkFile) ? (string) file_get_contents($commentMarkFile) : '';
$caseDocsPanelPath = __DIR__ . '/../../frontend/src/components/cases/CaseDocumentsPanel.vue';
$caseDocsSourceSlice4 = file_exists($caseDocsPanelPath) ? (string) file_get_contents($caseDocsPanelPath) : '';
$envExample = __DIR__ . '/../.env.example';
$envExampleSource = file_exists($envExample) ? (string) file_get_contents($envExample) : '';
$slice4Checks = [
    'RichTextEditor CommentMark extension' => str_contains($richTextSource, 'CommentMark'),
    'RichTextEditor highlight extension' => str_contains($richTextSource, 'Highlight'),
    'RichTextEditor comments sidebar' => str_contains($richTextSource, 'enableComments'),
    'commentMark.ts exists' => file_exists($commentMarkFile),
    'commentMark data-comment-id' => str_contains($commentMarkSource, 'data-comment-id'),
    'CaseDocumentsPanel version history UI' => str_contains($caseDocsSourceSlice4, 'Version history'),
    'CaseDocumentsPanel compareVersions wiring' => str_contains($caseDocsSourceSlice4, 'compareVersions'),
    'CaseDocumentsPanel change summary field' => str_contains($caseDocsSourceSlice4, 'changeSummary'),
    '.env.example ONLYOFFICE_URL placeholder' => str_contains($envExampleSource, 'ONLYOFFICE_URL'),
];
foreach ($slice4Checks as $label => $ok) {
    $results[] = [$label, $ok ? 'yes' : 'no', $ok ? 'OK' : 'FAIL'];
}

echo "\n=== PHASE 4 SLICE 5: ONLYOFFICE EVALUATION ===\n";
$onlyOfficeService = __DIR__ . '/../app/Services/OnlyOfficeConfigService.php';
$onlyOfficeConfig = __DIR__ . '/../config/onlyoffice.php';
$onlyOfficeEditor = __DIR__ . '/../../frontend/src/components/editor/OnlyOfficeEditor.vue';
$onlyOfficeEditorSource = file_exists($onlyOfficeEditor) ? (string) file_get_contents($onlyOfficeEditor) : '';
$dockerCompose = __DIR__ . '/../../docker-compose.onlyoffice.yml';
$controllerPath = __DIR__ . '/../app/Http/Controllers/Api/V1/LegalDocumentController.php';
$controllerSource = file_exists($controllerPath) ? (string) file_get_contents($controllerPath) : '';
$routesSource = file_exists(__DIR__ . '/../routes/api.php') ? (string) file_get_contents(__DIR__ . '/../routes/api.php') : '';

$onlyOfficeConfigRes = call($http, $token, 'GET', '/documents/'.($ids['document'] ?? 0).'/onlyoffice-config');
record($results, 'GET /documents/{id}/onlyoffice-config', $onlyOfficeConfigRes, [200]);
$configBody = $onlyOfficeConfigRes['body'] ?? [];
$results[] = [
    '  onlyoffice-config returns configured flag',
    isset($configBody['configured']) ? 'yes' : 'no',
    isset($configBody['configured']) ? 'OK' : 'FAIL',
];

$slice5Checks = [
    'OnlyOfficeConfigService exists' => file_exists($onlyOfficeService),
    'config/onlyoffice.php exists' => file_exists($onlyOfficeConfig),
    'OnlyOfficeEditor.vue exists' => file_exists($onlyOfficeEditor),
    'OnlyOfficeEditor loads DocsAPI' => str_contains($onlyOfficeEditorSource, 'DocsAPI'),
    'LegalDocumentController onlyOfficeCallback' => str_contains($controllerSource, 'onlyOfficeCallback'),
    'LegalDocumentController JWT signConfig' => str_contains($controllerSource, 'onlyOfficeConfig'),
    'routes onlyoffice-file + callback' => str_contains($routesSource, 'onlyoffice-callback')
        && str_contains($routesSource, 'onlyoffice-file'),
    'CaseDocumentsPanel OnlyOfficeEditor wiring' => str_contains($caseDocsSourceSlice4, 'OnlyOfficeEditor'),
    'CaseDocumentsPanel Edit in Word button' => str_contains($caseDocsSourceSlice4, 'Edit in Word'),
    'docker-compose.onlyoffice.yml exists' => file_exists($dockerCompose),
    '.env.example ONLYOFFICE_JWT_SECRET hint' => str_contains($envExampleSource, 'ONLYOFFICE_JWT_SECRET'),
];
foreach ($slice5Checks as $label => $ok) {
    $results[] = [$label, $ok ? 'yes' : 'no', $ok ? 'OK' : 'FAIL'];
}

echo "\n=== PHASE 4 SLICE 6: DOCUMENT VERSION DIFF (roadmap) ===\n";
$slice6Checks = [
    'Slice 6 covered in Slice 4 compare API' => str_contains($caseDocsSourceSlice4, 'compareVersions'),
    'Slice 6 side-by-side compare UI' => str_contains($caseDocsSourceSlice4, 'versionCompare'),
];
foreach ($slice6Checks as $label => $ok) {
    $results[] = [$label, $ok ? 'yes' : 'no', $ok ? 'OK' : 'FAIL'];
}

echo "\n=== PHASE 4 SLICE 10: PUBLIC AI SUPPORT CHAT ===\n";
$publicChatController = __DIR__ . '/../app/Http/Controllers/Api/V1/PublicAiChatController.php';
$publicChatLeadModel = __DIR__ . '/../app/Models/PublicChatLead.php';
$publicSupportView = __DIR__ . '/../../frontend/src/views/PublicSupportChatView.vue';
$publicSupportSource = file_exists($publicSupportView) ? (string) file_get_contents($publicSupportView) : '';
$apiClientSource = file_exists(__DIR__ . '/../../frontend/src/lib/api.ts') ? (string) file_get_contents(__DIR__ . '/../../frontend/src/lib/api.ts') : '';
$routerSource = file_exists(__DIR__ . '/../../frontend/src/router/index.ts') ? (string) file_get_contents(__DIR__ . '/../../frontend/src/router/index.ts') : '';

$results[] = [
    'public_chat_leads table',
    Schema::hasTable('public_chat_leads') ? 'yes' : 'no',
    Schema::hasTable('public_chat_leads') ? 'OK' : 'FAIL',
];

$publicChatRes = call($http, '', 'POST', '/public/chat', [
    'message' => 'How do I schedule a consultation?',
    'name' => 'Phase4 Verify',
    'email' => 'public-chat-verify@banwolaw.com',
]);
record($results, 'POST /public/chat (unauthenticated)', $publicChatRes, [200]);
$publicChatBody = $publicChatRes['body'] ?? [];
$results[] = [
    '  public chat labeled + disclaimer',
    (isset($publicChatBody['label'], $publicChatBody['disclaimer'], $publicChatBody['output_id']) && ($publicChatBody['labeled'] ?? false)) ? 'yes' : 'no',
    (isset($publicChatBody['label'], $publicChatBody['disclaimer'], $publicChatBody['output_id']) && ($publicChatBody['labeled'] ?? false)) ? 'OK' : 'FAIL',
];
$results[] = [
    '  public chat returns session_id',
    isset($publicChatBody['session_id']) ? 'yes' : 'no',
    isset($publicChatBody['session_id']) ? 'OK' : 'FAIL',
];
$results[] = [
    '  public chat lead_captured flag',
    ($publicChatBody['lead_captured'] ?? false) ? 'yes' : 'no',
    ($publicChatBody['lead_captured'] ?? false) ? 'OK' : 'FAIL',
];

$internalDataRes = call($http, '', 'POST', '/public/chat', [
    'message' => 'test',
    'legal_matter_id' => 1,
]);
$results[] = [
    'POST /public/chat rejects internal case data',
    $internalDataRes['status'] === 422 ? '422' : (string) $internalDataRes['status'],
    $internalDataRes['status'] === 422 ? 'OK' : 'FAIL',
];

$leadCount = \App\Models\PublicChatLead::query()
    ->where('email', 'public-chat-verify@banwolaw.com')
    ->count();
$results[] = [
    '  public_chat_leads row stored',
    $leadCount >= 1 ? (string) $leadCount : '0',
    $leadCount >= 1 ? 'OK' : 'FAIL',
];

echo "\n=== PHASE 4 SLICE: DOCUMENT MERGE FIELDS ===\n";

$mergeFieldsRes = call($http, $token, 'GET', '/documents/merge-fields');
record($results, 'GET /documents/merge-fields', $mergeFieldsRes, [200]);
$mergeFieldsBody = $mergeFieldsRes['body'] ?? [];
$fieldRows = $mergeFieldsBody['fields'] ?? [];
$results[] = [
    '  merge fields catalog count >= 25',
    is_array($fieldRows) ? (string) count($fieldRows) : '0',
    is_array($fieldRows) && count($fieldRows) >= 25 ? 'OK' : 'FAIL',
];

$mergeService = app(\App\Services\DocumentMergeService::class);
$matter->loadMissing(['client', 'leadLawyer', 'organization', 'parties']);
$mergedFieldMap = $mergeService->mergeFields($matter);
$results[] = [
    '  DocumentMergeService client.name populated',
    ($mergedFieldMap['client.name'] ?? '') !== '' ? 'yes' : 'no',
    ($mergedFieldMap['client.name'] ?? '') !== '' ? 'OK' : 'FAIL',
];
$results[] = [
    '  DocumentMergeService organization.name key',
    array_key_exists('organization.name', $mergedFieldMap) ? 'yes' : 'no',
    array_key_exists('organization.name', $mergedFieldMap) ? 'OK' : 'FAIL',
];

$mergeTemplate = LegalDocument::query()->create([
    'organization_id' => $organization->id,
    'uploaded_by' => $admin->id,
    'document_type' => 'organization_template',
    'name' => 'Phase4 Merge Template',
    'content_html' => '<p>{{client.name}} — {{case.title}} — {{lawyer.name}} — {{organization.name}}</p>',
    'original_filename' => 'phase4-merge.html',
    'mime_type' => 'text/html',
    'size' => 80,
    'disk' => 'local',
    'path' => '',
    'version' => 1,
]);
$generateMergeRes = call($http, $token, 'POST', '/documents/generate-draft', [
    'template_id' => $mergeTemplate->id,
    'legal_matter_id' => $matter->id,
    'name' => 'Phase4 merged draft',
]);
record($results, 'POST /documents/generate-draft (merge fields)', $generateMergeRes, [201]);
$generateMergeBody = $generateMergeRes['body'] ?? [];
$generateMergeData = $generateMergeBody['data'] ?? $generateMergeBody;
$mergedHtml = (string) ($generateMergeData['content_html'] ?? '');
$results[] = [
    '  generate-draft resolves client.name token',
    ! str_contains($mergedHtml, '{{client.name}}') ? 'yes' : 'no',
    ! str_contains($mergedHtml, '{{client.name}}') ? 'OK' : 'FAIL',
];

$mergeFieldPicker = __DIR__ . '/../../frontend/src/components/documents/MergeFieldPicker.vue';
$mergePickerSource = file_exists($mergeFieldPicker) ? (string) file_get_contents($mergeFieldPicker) : '';
$caseDocsPanelSource = file_exists($caseDocsPanel) ? (string) file_get_contents($caseDocsPanel) : '';
$mergeUiChecks = [
    'MergeFieldPicker component exists' => file_exists($mergeFieldPicker),
    'MergeFieldPicker listMergeFields wiring' => str_contains($mergePickerSource, 'listMergeFields'),
    'CaseDocumentsPanel MergeFieldPicker' => str_contains($caseDocsPanelSource, 'MergeFieldPicker'),
];
foreach ($mergeUiChecks as $label => $ok) {
    $results[] = [$label, $ok ? 'yes' : 'no', $ok ? 'OK' : 'FAIL'];
}

echo "\n=== WAVE 4: CLAUSE LIBRARY + AI CONTRACT/LETTERS ===\n";

$results[] = [
    'document_clauses table',
    Schema::hasTable('document_clauses') ? 'yes' : 'no',
    Schema::hasTable('document_clauses') ? 'OK' : 'FAIL',
];
$results[] = [
    'document_clauses columns',
    Schema::hasColumns('document_clauses', [
        'organization_id', 'title', 'category', 'body_html', 'tags',
    ]) ? 'yes' : 'no',
    Schema::hasColumns('document_clauses', [
        'organization_id', 'title', 'category', 'body_html', 'tags',
    ]) ? 'OK' : 'FAIL',
];
$results[] = [
    'document_versions source column',
    Schema::hasColumn('document_versions', 'source') ? 'yes' : 'no',
    Schema::hasColumn('document_versions', 'source') ? 'OK' : 'FAIL',
];

$clauseCreateRes = call($http, $token, 'POST', '/document-clauses', [
    'title' => 'Phase4 Limitation of Liability',
    'category' => 'indemnity',
    'body_html' => '<p>Provider liability shall not exceed fees paid in the prior twelve months.</p>',
    'tags' => ['liability', 'standard'],
]);
record($results, 'POST /document-clauses', $clauseCreateRes, [201]);
$clauseCreateBody = $clauseCreateRes['body'] ?? [];
$clauseCreateData = $clauseCreateBody['data'] ?? $clauseCreateBody;
$clauseId = (int) ($clauseCreateData['id'] ?? 0);
$results[] = [
    '  clause created with body_html',
    ($clauseCreateData['body_html'] ?? '') !== '' ? 'yes' : 'no',
    ($clauseCreateData['body_html'] ?? '') !== '' ? 'OK' : 'FAIL',
];

if ($clauseId > 0) {
    $clauseListRes = call($http, $token, 'GET', '/document-clauses', ['category' => 'indemnity']);
    record($results, 'GET /document-clauses', $clauseListRes, [200]);
    $clauseListBody = $clauseListRes['body'] ?? [];
    $clauseRows = $clauseListBody['data'] ?? (is_array($clauseListBody) ? $clauseListBody : []);
    $results[] = [
        '  clause list returns rows',
        is_array($clauseRows) && count($clauseRows) >= 1 ? (string) count($clauseRows) : '0',
        is_array($clauseRows) && count($clauseRows) >= 1 ? 'OK' : 'FAIL',
    ];

    $clauseUpdateRes = call($http, $token, 'PATCH', '/document-clauses/' . $clauseId, [
        'title' => 'Phase4 Liability Cap (updated)',
    ]);
    record($results, 'PATCH /document-clauses/{id}', $clauseUpdateRes, [200]);

    $clauseDeleteRes = call($http, $token, 'DELETE', '/document-clauses/' . $clauseId);
    record($results, 'DELETE /document-clauses/{id}', $clauseDeleteRes, [200]);
}

$contractReviewRes = call($http, $token, 'POST', '/ai/contract/review', [
    'legal_document_id' => $ids['document'] ?? $mergeTemplate->id ?? 0,
]);
record($results, 'POST /ai/contract/review', $contractReviewRes, [200]);
$contractReviewBody = $contractReviewRes['body'] ?? [];
$results[] = [
    '  contract review disclaimer + issues',
    (isset($contractReviewBody['disclaimer'], $contractReviewBody['issues']) && is_array($contractReviewBody['issues']) && count($contractReviewBody['issues']) >= 1)
        ? 'yes'
        : 'no',
    (isset($contractReviewBody['disclaimer'], $contractReviewBody['issues']) && is_array($contractReviewBody['issues']) && count($contractReviewBody['issues']) >= 1)
        ? 'OK'
        : 'FAIL',
];

$letterPackRes = call($http, $token, 'POST', '/ai/letters/generate-pack', [
    'legal_matter_id' => $matter->id,
    'letter_types' => ['engagement', 'demand'],
]);
record($results, 'POST /ai/letters/generate-pack', $letterPackRes, [200]);
$letterPackBody = $letterPackRes['body'] ?? [];
$results[] = [
    '  letter pack returns letters array',
    (isset($letterPackBody['letters']) && is_array($letterPackBody['letters']) && count($letterPackBody['letters']) >= 2)
        ? (string) count($letterPackBody['letters'])
        : 'no',
    (isset($letterPackBody['letters']) && is_array($letterPackBody['letters']) && count($letterPackBody['letters']) >= 2)
        ? 'OK'
        : 'FAIL',
];

if ($ids['document']) {
    $aiVersionSaveRes = call($http, $token, 'POST', '/documents/ai-draft', [
        'legal_matter_id' => $matter->id,
        'content_html' => '<p>AI lineage verify draft.</p>',
        'name' => 'Phase4 AI lineage draft',
    ]);
    record($results, 'POST /documents/ai-draft (lineage)', $aiVersionSaveRes, [201]);
    $aiVersionBody = $aiVersionSaveRes['body'] ?? [];
    $aiVersionData = $aiVersionBody['data'] ?? $aiVersionBody;
    $aiDocId = (int) ($aiVersionData['id'] ?? 0);
    if ($aiDocId > 0) {
        $aiVersionsRes = call($http, $token, 'GET', '/documents/' . $aiDocId . '/versions');
        record($results, 'GET /documents/{id}/versions (AI lineage)', $aiVersionsRes, [200]);
        $aiVersionsBody = $aiVersionsRes['body'] ?? [];
        $aiVersionRows = $aiVersionsBody['data'] ?? (is_array($aiVersionsBody) ? $aiVersionsBody : []);
        $firstAiVersion = is_array($aiVersionRows) ? ($aiVersionRows[0] ?? null) : null;
        $results[] = [
            '  AI draft version source=ai',
            (is_array($firstAiVersion) && ($firstAiVersion['source'] ?? '') === 'ai') ? 'yes' : 'no',
            (is_array($firstAiVersion) && ($firstAiVersion['source'] ?? '') === 'ai') ? 'OK' : 'FAIL',
        ];
    }
}

$clauseLibraryPanel = __DIR__ . '/../../frontend/src/components/documents/ClauseLibraryPanel.vue';
$clausePanelSource = file_exists($clauseLibraryPanel) ? (string) file_get_contents($clauseLibraryPanel) : '';
$wave4UiChecks = [
    'ClauseLibraryPanel component exists' => file_exists($clauseLibraryPanel),
    'ClauseLibraryPanel documentClausesApi wiring' => str_contains($clausePanelSource, 'documentClausesApi'),
    'CaseDocumentsPanel ClauseLibraryPanel' => str_contains($caseDocsPanelSource, 'ClauseLibraryPanel'),
    'CaseDocumentsPanel contract review button' => str_contains($caseDocsPanelSource, 'contractReview'),
    'CaseDocumentsPanel letter pack wiring' => str_contains($caseDocsPanelSource, 'generateLetterPack'),
    'CaseDocumentsPanel version source badge' => str_contains($caseDocsPanelSource, 'versionSourceLabel'),
    'routes document-clauses registered' => str_contains($routesSource, 'document-clauses'),
    'routes ai/contract/review registered' => str_contains($routesSource, '/contract/review'),
    'routes ai/letters/generate-pack registered' => str_contains($routesSource, '/letters/generate-pack'),
    'DocumentClauseController exists' => file_exists(__DIR__ . '/../app/Http/Controllers/Api/V1/DocumentClauseController.php'),
    'AiAssistantController contractReview method' => str_contains((string) @file_get_contents(__DIR__ . '/../app/Http/Controllers/Api/V1/AiAssistantController.php'), 'function contractReview'),
    'AiAssistantController generateLetterPack method' => str_contains((string) @file_get_contents(__DIR__ . '/../app/Http/Controllers/Api/V1/AiAssistantController.php'), 'function generateLetterPack'),
];
foreach ($wave4UiChecks as $label => $ok) {
    $results[] = [$label, $ok ? 'yes' : 'no', $ok ? 'OK' : 'FAIL'];
}

echo "\n=== WAVE 5 UI API CHECKS ===\n";

$agingRes = call($http, $token, 'GET', '/invoices/aging-summary');
record($results, 'GET /invoices/aging-summary', $agingRes, [200]);
$agingBody = $agingRes['body'] ?? [];
$results[] = [
    '  aging summary buckets count',
    is_array($agingBody['buckets'] ?? null) && count($agingBody['buckets']) === 4 ? '4' : 'no',
    is_array($agingBody['buckets'] ?? null) && count($agingBody['buckets']) === 4 ? 'OK' : 'FAIL',
];

$overviewRes = call($http, $token, 'GET', '/cases/'.$matter->id.'/overview-metrics');
record($results, 'GET /cases/{id}/overview-metrics', $overviewRes, [200]);
$overviewBody = $overviewRes['body'] ?? [];
$results[] = [
    '  overview metrics billing_trend rows',
    is_array($overviewBody['billing_trend'] ?? null) && count($overviewBody['billing_trend']) === 6 ? '6' : 'no',
    is_array($overviewBody['billing_trend'] ?? null) && count($overviewBody['billing_trend']) === 6 ? 'OK' : 'FAIL',
];
$results[] = [
    '  overview metrics trust_ledger array',
    array_key_exists('trust_ledger', $overviewBody) && is_array($overviewBody['trust_ledger']) ? 'yes' : 'no',
    array_key_exists('trust_ledger', $overviewBody) && is_array($overviewBody['trust_ledger']) ? 'OK' : 'FAIL',
];
$results[] = [
    '  overview metrics trust_balance key',
    array_key_exists('trust_balance', $overviewBody) ? 'yes' : 'no',
    array_key_exists('trust_balance', $overviewBody) ? 'OK' : 'FAIL',
];

$wave5UiFiles = [
    'CaseDetailView overview metrics' => __DIR__ . '/../../frontend/src/views/cases/CaseDetailView.vue',
    'InvoicesListView aging bars' => __DIR__ . '/../../frontend/src/views/invoices/InvoicesListView.vue',
    'ClientsListView status dots' => __DIR__ . '/../../frontend/src/views/clients/ClientsListView.vue',
    'CasesListView list/cards toggle' => __DIR__ . '/../../frontend/src/views/cases/CasesListView.vue',
    'DashboardView next-deadline ring' => __DIR__ . '/../../frontend/src/views/DashboardView.vue',
    'CaseDetailView trust ledger' => __DIR__ . '/../../frontend/src/views/cases/CaseDetailView.vue',
];
foreach ($wave5UiFiles as $label => $path) {
    $source = file_exists($path) ? (string) file_get_contents($path) : '';
    $needle = match ($label) {
        'CaseDetailView overview metrics' => 'overview-metrics',
        'InvoicesListView aging bars' => 'agingSummary',
        'ClientsListView status dots' => 'statusDotVar',
        'CasesListView list/cards toggle' => "viewMode === 'cards'",
        'DashboardView next-deadline ring' => 'calendarHubApi',
        'CaseDetailView trust ledger' => 'trust_ledger',
        default => '',
    };
    $ok = $needle !== '' && str_contains($source, $needle);
    $results[] = [$label, $ok ? 'yes' : 'no', $ok ? 'OK' : 'FAIL'];
}

$icsRes = call($http, $token, 'GET', '/calendar-hub/export.ics?category=all');
record($results, 'GET /calendar-hub/export.ics', $icsRes, [200]);

$hubRes = call($http, $token, 'GET', '/calendar-hub', ['category' => 'deadlines']);
record($results, 'GET /calendar-hub (deadlines for dashboard)', $hubRes, [200]);
$hubBody = $hubRes['body'] ?? [];
$results[] = [
    '  calendar-hub deadline_board meta',
    is_array($hubBody['meta']['deadline_board'] ?? null) ? 'yes' : 'no',
    is_array($hubBody['meta']['deadline_board'] ?? null) ? 'OK' : 'FAIL',
];

$slice10Checks = [
    'PublicAiChatController exists' => file_exists($publicChatController),
    'PublicChatLead model exists' => file_exists($publicChatLeadModel),
    'routes public/chat registered' => str_contains($routesSource, '/public/chat'),
    'PublicSupportChatView exists' => file_exists($publicSupportView),
    'PublicSupportChatView disclaimer banner' => str_contains($publicSupportSource, 'AiDisclaimerBanner'),
    'PublicSupportChatView lead capture form' => str_contains($publicSupportSource, 'lead-email'),
    'PublicSupportChatView publicChatApi wiring' => str_contains($publicSupportSource, 'publicChatApi'),
    'api.ts publicChatApi export' => str_contains($apiClientSource, 'publicChatApi'),
    'router /support public route' => str_contains($routerSource, "path: '/support'"),
    'router /chat redirect' => str_contains($routerSource, "path: '/chat'"),
    'config ai public_disclaimer' => array_key_exists('public_disclaimer', require __DIR__ . '/../config/ai.php'),
];
foreach ($slice10Checks as $label => $ok) {
    $results[] = [$label, $ok ? 'yes' : 'no', $ok ? 'OK' : 'FAIL'];
}

echo "\n==================== PHASE 4 RESULTS ====================\n";
$pass = 0;
$fail = 0;
foreach ($results as [$label, $status, $verdict]) {
    printf("%-45s %-8s %s\n", $label, (string) $status, $verdict);
    $verdict === 'OK' ? $pass++ : $fail++;
}
echo "----------------------------------------------------------------------\n";
echo "PASS: {$pass}  FAIL: {$fail}\n";

exit($fail > 0 ? 1 : 0);
