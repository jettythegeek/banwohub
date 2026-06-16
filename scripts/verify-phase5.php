<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use App\Models\Client;
use App\Models\CourtFiling;
use App\Models\CourtFormInstance;
use App\Models\CourtFormTemplate;
use App\Models\BriefCitation;
use App\Models\EvidenceCustodyLog;
use App\Models\EvidenceItem;
use App\Models\LegalBrief;
use App\Models\LegalMotion;
use App\Models\LegalResearchEntry;
use App\Models\MotionTemplate;
use App\Models\ResearchFolder;
use App\Models\ResearchSavedItem;
use App\Models\EdiscoveryCollection;
use App\Models\EdiscoveryDocument;
use App\Models\EdiscoveryReviewAssignment;
use App\Models\EdiscoveryTag;
use App\Models\KnowledgeArticle;
use App\Models\LegalProjectBudget;
use App\Models\LegalProjectMilestone;
use App\Models\TrainingCertificate;
use App\Models\TrainingCourse;
use App\Models\TrainingEnrollment;
use Database\Seeders\KnowledgeArticleSeeder;
use Database\Seeders\LegalResearchEntrySeeder;
use Database\Seeders\TrainingCourseSeeder;
use App\Models\LegalMatter;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Database\Seeders\CourtFormTemplateSeeder;
use Database\Seeders\MotionTemplateSeeder;
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
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

$token = $admin->createToken('phase5-verify')->plainTextToken;

$consultant = User::query()->where('email', 'consultant@banwolaw.com')->first();
if (! $consultant) {
    fwrite(STDERR, "Consultant not found: consultant@banwolaw.com — run php artisan db:seed --class=BanwolawSeeder\n");
    exit(1);
}
$consultantToken = $consultant->createToken('phase5-consultant')->plainTextToken;

$results = [];
$ids = [];

function call(HttpKernel $http, string $token, string $method, string $uri, array $payload = [], array $files = []): array
{
    $server = [
        'HTTP_Accept' => 'application/json',
    ];
    if ($token !== '') {
        $server['HTTP_Authorization'] = 'Bearer ' . $token;
    }

    $request = Request::create('/api/v1' . $uri, $method, $payload, [], $files, $server);
    if ($files === [] && $payload !== [] && in_array($method, ['POST', 'PUT', 'PATCH'], true)) {
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

function resId(array $res): ?int
{
    $body = $res['body'] ?? [];
    if (isset($body['id'])) {
        return (int) $body['id'];
    }
    if (isset($body['data']['id'])) {
        return (int) $body['data']['id'];
    }

    return null;
}

echo "=== PHASE 5 SLICE 1: FILING TRACKER + COURT FORMS ===\n";

$results[] = ['court_form_templates table', Schema::hasTable('court_form_templates') ? 'yes' : 'no', Schema::hasTable('court_form_templates') ? 'OK' : 'FAIL'];
$results[] = ['court_form_instances table', Schema::hasTable('court_form_instances') ? 'yes' : 'no', Schema::hasTable('court_form_instances') ? 'OK' : 'FAIL'];
$results[] = ['court_filings table', Schema::hasTable('court_filings') ? 'yes' : 'no', Schema::hasTable('court_filings') ? 'OK' : 'FAIL'];

foreach (['filings.view', 'filings.create', 'filings.update', 'filings.delete', 'court-forms.view', 'court-forms.create', 'court-forms.update'] as $perm) {
    Permission::findOrCreate($perm, 'web');
}
$admin->givePermissionTo([
    'filings.view', 'filings.create', 'filings.update', 'filings.delete',
    'court-forms.view', 'court-forms.create', 'court-forms.update',
]);

(new CourtFormTemplateSeeder())->run();
$templateCount = CourtFormTemplate::query()->whereNull('organization_id')->count();
$results[] = ['court form templates seeded', (string) $templateCount, $templateCount >= 2 ? 'OK' : 'FAIL'];

$organization = $admin->organization;
$client = Client::query()->where('organization_id', $organization->id)->first();
if (! $client) {
    $client = Client::query()->create([
        'organization_id' => $organization->id,
        'type' => 'individual',
        'name' => 'Phase5 Filing Client',
        'email' => 'phase5-filing@example.com',
        'status' => 'active',
        'created_by' => $admin->id,
    ]);
}

$matter = LegalMatter::query()->firstOrCreate(
    [
        'organization_id' => $organization->id,
        'matter_number' => 'P5-FILING-001',
    ],
    [
        'client_id' => $client->id,
        'title' => 'Phase 5 Filing Matter',
        'status' => 'open',
        'court_jurisdiction' => 'U.S. District Court',
        'case_type' => 'civil',
        'lead_lawyer_id' => $admin->id,
        'created_by' => $admin->id,
    ]
);

$templatesRes = call($http, $token, 'GET', '/court-form-templates');
record($results, 'GET /court-form-templates', $templatesRes, [200]);
$templates = $templatesRes['body']['data'] ?? [];
$templateId = isset($templates[0]['id']) ? (int) $templates[0]['id'] : null;
$results[] = [
    '  templates list non-empty',
    $templateId ? 'yes' : 'no',
    $templateId ? 'OK' : 'FAIL',
];

if ($templateId) {
    $prefillRes = call($http, $token, 'POST', "/court-form-templates/{$templateId}/prefill", [
        'legal_matter_id' => $matter->id,
    ]);
    record($results, 'POST /court-form-templates/{id}/prefill', $prefillRes, [200]);
    $prefillBody = $prefillRes['body'] ?? [];
    $results[] = [
        '  prefill has field_values',
        isset($prefillBody['field_values']) && is_array($prefillBody['field_values']) ? 'yes' : 'no',
        isset($prefillBody['field_values']) && is_array($prefillBody['field_values']) ? 'OK' : 'FAIL',
    ];
    $results[] = [
        '  prefill client_name populated',
        ! empty($prefillBody['field_values']['client_name'] ?? null) ? 'yes' : 'no',
        ! empty($prefillBody['field_values']['client_name'] ?? null) ? 'OK' : 'FAIL',
    ];
}

$instanceRes = call($http, $token, 'POST', '/court-form-instances', [
    'legal_matter_id' => $matter->id,
    'court_form_template_id' => $templateId,
]);
record($results, 'POST /court-form-instances', $instanceRes, [201]);
$ids['form_instance'] = resId($instanceRes);
$instanceBody = $instanceRes['body'] ?? [];
$results[] = [
    '  form instance status draft',
    ($instanceBody['status'] ?? '') === 'draft' ? 'yes' : 'no',
    ($instanceBody['status'] ?? '') === 'draft' ? 'OK' : 'FAIL',
];

$listFormsRes = call($http, $token, 'GET', '/court-form-instances?legal_matter_id=' . $matter->id);
record($results, 'GET /court-form-instances (scoped)', $listFormsRes, [200]);

$filingRes = call($http, $token, 'POST', '/court-filings', [
    'legal_matter_id' => $matter->id,
    'title' => 'Motion to Dismiss',
    'court' => 'U.S. District Court',
    'filing_method' => 'manual',
    'notes' => 'Manual filing test',
]);
record($results, 'POST /court-filings', $filingRes, [201]);
$ids['filing'] = resId($filingRes);

if ($ids['form_instance']) {
    $linkFilingRes = call($http, $token, 'POST', "/court-form-instances/{$ids['form_instance']}/create-filing");
    record($results, 'POST /court-form-instances/{id}/create-filing', $linkFilingRes, [201]);
    $linkBody = $linkFilingRes['body'] ?? [];
    $linkedFilingId = $linkBody['court_filing_id'] ?? $linkBody['data']['court_filing_id'] ?? null;
    $results[] = [
        '  form linked to filing',
        ! empty($linkedFilingId) ? 'yes' : 'no',
        ! empty($linkedFilingId) ? 'OK' : 'FAIL',
    ];
}

$listFilingsRes = call($http, $token, 'GET', '/court-filings?legal_matter_id=' . $matter->id);
record($results, 'GET /court-filings (scoped)', $listFilingsRes, [200]);
$listBody = $listFilingsRes['body'] ?? [];
$results[] = [
    '  filings meta statuses',
    isset($listBody['meta']['statuses']) && is_array($listBody['meta']['statuses']) ? 'yes' : 'no',
    isset($listBody['meta']['statuses']) && is_array($listBody['meta']['statuses']) ? 'OK' : 'FAIL',
];

if ($ids['filing']) {
    $statusRes = call($http, $token, 'PATCH', "/court-filings/{$ids['filing']}/status", [
        'status' => 'ready_to_file',
    ]);
    record($results, 'PATCH /court-filings/{id}/status (ready_to_file)', $statusRes, [200]);

    $filedRes = call($http, $token, 'PATCH', "/court-filings/{$ids['filing']}/status", [
        'status' => 'filed',
        'court_reference_number' => 'CV-2026-00123',
    ]);
    record($results, 'PATCH /court-filings/{id}/status (filed)', $filedRes, [200]);
    $filedBody = $filedRes['body'] ?? [];
    $results[] = [
        '  filed sets reference number',
        ($filedBody['court_reference_number'] ?? '') === 'CV-2026-00123' ? 'yes' : 'no',
        ($filedBody['court_reference_number'] ?? '') === 'CV-2026-00123' ? 'OK' : 'FAIL',
    ];

    $invalidRes = call($http, $token, 'PATCH', "/court-filings/{$ids['filing']}/status", [
        'status' => 'draft',
    ]);
    record($results, 'PATCH status invalid transition', $invalidRes, [422]);
}

$consultantFilings = call($http, $consultantToken, 'GET', '/court-filings');
record($results, 'GET /court-filings (consultant blocked)', $consultantFilings, [403]);

$filingModel = $ids['filing'] ? CourtFiling::query()->find($ids['filing']) : null;
$results[] = [
    'CourtFiling::STATUSES count',
    (string) count(CourtFiling::STATUSES),
    count(CourtFiling::STATUSES) >= 11 ? 'OK' : 'FAIL',
];
$results[] = [
    'CourtFormInstance model exists',
    class_exists(CourtFormInstance::class) ? 'yes' : 'no',
    class_exists(CourtFormInstance::class) ? 'OK' : 'FAIL',
];

$filingsView = __DIR__ . '/../frontend/src/views/FilingsView.vue';
$filingsPanel = __DIR__ . '/../frontend/src/components/cases/CaseFilingsPanel.vue';
$caseDetailView = __DIR__ . '/../frontend/src/views/cases/CaseDetailView.vue';
$routerSource = (string) @file_get_contents(__DIR__ . '/../frontend/src/router/index.ts');
$sidebarSource = (string) @file_get_contents(__DIR__ . '/../frontend/src/components/layout/Sidebar.vue');
$uiChecks = [
    'FilingsView.vue exists' => file_exists($filingsView),
    'CaseFilingsPanel.vue exists' => file_exists($filingsPanel),
    'CaseFilingsPanel auto-fill wiring' => str_contains((string) @file_get_contents($filingsPanel), 'courtFormInstancesApi.create'),
    'CaseDetailView filings tab' => str_contains((string) @file_get_contents($caseDetailView), "key: 'filings'"),
    'Router filings route' => str_contains($routerSource, "name: 'filings'"),
    'Router filings workspace tab' => str_contains($routerSource, 'filings'),
    'Sidebar filings nav' => str_contains($sidebarSource, "name: 'filings'"),
];
foreach ($uiChecks as $label => $ok) {
    $results[] = [$label, $ok ? 'yes' : 'no', $ok ? 'OK' : 'FAIL'];
}

echo "\n=== PHASE 5 SLICE 2: EVIDENCE MANAGEMENT ===\n";

$results[] = ['evidence_items table', Schema::hasTable('evidence_items') ? 'yes' : 'no', Schema::hasTable('evidence_items') ? 'OK' : 'FAIL'];
$results[] = ['evidence_custody_logs table', Schema::hasTable('evidence_custody_logs') ? 'yes' : 'no', Schema::hasTable('evidence_custody_logs') ? 'OK' : 'FAIL'];

foreach (['evidence.view', 'evidence.create', 'evidence.update', 'evidence.delete'] as $perm) {
    Permission::findOrCreate($perm, 'web');
}
$admin->givePermissionTo([
    'evidence.view', 'evidence.create', 'evidence.update', 'evidence.delete',
]);

$evidenceMatter = LegalMatter::query()->firstOrCreate(
    [
        'organization_id' => $organization->id,
        'matter_number' => 'P5-EVIDENCE-001',
    ],
    [
        'client_id' => $client->id,
        'title' => 'Phase 5 Evidence Matter',
        'status' => 'open',
        'court_jurisdiction' => 'U.S. District Court',
        'case_type' => 'civil',
        'lead_lawyer_id' => $admin->id,
        'created_by' => $admin->id,
    ]
);

$uploadTmp = tempnam(sys_get_temp_dir(), 'p5evidence') . '.txt';
file_put_contents($uploadTmp, 'Phase 5 evidence upload content');
$evidenceFile = new UploadedFile($uploadTmp, 'witness-statement.txt', 'text/plain', null, true);
$evidenceRes = call($http, $token, 'POST', '/evidence-items', [
    'legal_matter_id' => (string) $evidenceMatter->id,
    'title' => 'Witness Statement',
    'description' => 'Initial witness statement',
    'evidence_type' => 'statement',
    'source' => 'Client interview',
    'date_obtained' => '2026-06-01',
], ['file' => $evidenceFile]);
record($results, 'POST /evidence-items (upload)', $evidenceRes, [201]);
$ids['evidence'] = resId($evidenceRes);
$evidenceBody = $evidenceRes['body'] ?? [];
$results[] = [
    '  evidence status uploaded',
    ($evidenceBody['status'] ?? '') === 'uploaded' ? 'yes' : 'no',
    ($evidenceBody['status'] ?? '') === 'uploaded' ? 'OK' : 'FAIL',
];
$results[] = [
    '  evidence has file',
    ! empty($evidenceBody['has_file']) ? 'yes' : 'no',
    ! empty($evidenceBody['has_file']) ? 'OK' : 'FAIL',
];

$listEvidenceRes = call($http, $token, 'GET', '/evidence-items?legal_matter_id=' . $evidenceMatter->id);
record($results, 'GET /evidence-items (scoped)', $listEvidenceRes, [200]);
$listEvidenceBody = $listEvidenceRes['body'] ?? [];
$results[] = [
    '  evidence meta statuses',
    isset($listEvidenceBody['meta']['statuses']) && is_array($listEvidenceBody['meta']['statuses']) ? 'yes' : 'no',
    isset($listEvidenceBody['meta']['statuses']) && is_array($listEvidenceBody['meta']['statuses']) ? 'OK' : 'FAIL',
];

if ($ids['evidence']) {
    $reviewRes = call($http, $token, 'PATCH', "/evidence-items/{$ids['evidence']}/status", [
        'status' => 'under_review',
    ]);
    record($results, 'PATCH /evidence-items/{id}/status (under_review)', $reviewRes, [200]);

    $approveRes = call($http, $token, 'PATCH', "/evidence-items/{$ids['evidence']}/status", [
        'status' => 'approved',
    ]);
    record($results, 'PATCH /evidence-items/{id}/status (approved)', $approveRes, [200]);

    $invalidRes = call($http, $token, 'PATCH', "/evidence-items/{$ids['evidence']}/status", [
        'status' => 'uploaded',
    ]);
    record($results, 'PATCH evidence invalid transition', $invalidRes, [422]);

    $exhibitRes = call($http, $token, 'POST', "/evidence-items/{$ids['evidence']}/assign-exhibit");
    record($results, 'POST /evidence-items/{id}/assign-exhibit', $exhibitRes, [200]);
    $exhibitBody = $exhibitRes['body'] ?? [];
    $results[] = [
        '  exhibit number assigned',
        ! empty($exhibitBody['exhibit_number']) ? 'yes' : 'no',
        ! empty($exhibitBody['exhibit_number']) ? 'OK' : 'FAIL',
    ];

    $custodyRes = call($http, $token, 'POST', "/evidence-items/{$ids['evidence']}/custody-logs", [
        'action' => 'transferred',
        'notes' => 'Moved to secure storage.',
    ]);
    record($results, 'POST /evidence-items/{id}/custody-logs', $custodyRes, [201]);

    $custodyListRes = call($http, $token, 'GET', "/evidence-items/{$ids['evidence']}/custody-logs");
    record($results, 'GET /evidence-items/{id}/custody-logs', $custodyListRes, [200]);
    $custodyList = $custodyListRes['body']['data'] ?? $custodyListRes['body'] ?? [];
    $results[] = [
        '  custody logs non-empty',
        is_array($custodyList) && count($custodyList) >= 2 ? 'yes' : 'no',
        is_array($custodyList) && count($custodyList) >= 2 ? 'OK' : 'FAIL',
    ];

    $indexRes = call($http, $token, 'GET', '/evidence-items/exhibit-index?legal_matter_id=' . $evidenceMatter->id);
    record($results, 'GET /evidence-items/exhibit-index', $indexRes, [200]);
    $indexBody = $indexRes['body'] ?? [];
    $results[] = [
        '  exhibit index has items',
        isset($indexBody['items']) && count($indexBody['items']) >= 1 ? 'yes' : 'no',
        isset($indexBody['items']) && count($indexBody['items']) >= 1 ? 'OK' : 'FAIL',
    ];

    $bundleRes = call($http, $token, 'GET', '/evidence-items/export-bundle?legal_matter_id=' . $evidenceMatter->id);
    record($results, 'GET /evidence-items/export-bundle', $bundleRes, [200]);
}

$consultantEvidence = call($http, $consultantToken, 'GET', '/evidence-items');
record($results, 'GET /evidence-items (consultant blocked)', $consultantEvidence, [403]);

$results[] = [
    'EvidenceItem::STATUSES count',
    (string) count(EvidenceItem::STATUSES),
    count(EvidenceItem::STATUSES) >= 7 ? 'OK' : 'FAIL',
];
$results[] = [
    'EvidenceCustodyLog::ACTIONS count',
    (string) count(EvidenceCustodyLog::ACTIONS),
    count(EvidenceCustodyLog::ACTIONS) >= 5 ? 'OK' : 'FAIL',
];

$evidenceView = __DIR__ . '/../frontend/src/views/EvidenceView.vue';
$evidencePanel = __DIR__ . '/../frontend/src/components/cases/CaseEvidencePanel.vue';
$evidenceUiChecks = [
    'EvidenceView.vue exists' => file_exists($evidenceView),
    'CaseEvidencePanel.vue exists' => file_exists($evidencePanel),
    'CaseEvidencePanel upload wiring' => str_contains((string) @file_get_contents($evidencePanel), 'evidenceApi.upload'),
    'CaseDetailView evidence tab' => str_contains((string) @file_get_contents($caseDetailView), "key: 'evidence'"),
    'Router evidence route' => str_contains($routerSource, "name: 'evidence'"),
    'Router evidence workspace tab' => str_contains($routerSource, '|evidence'),
    'Sidebar evidence nav' => str_contains($sidebarSource, "name: 'evidence'"),
];
foreach ($evidenceUiChecks as $label => $ok) {
    $results[] = [$label, $ok ? 'yes' : 'no', $ok ? 'OK' : 'FAIL'];
}

echo "\n=== PHASE 5 SLICE 3: BRIEF WRITING ===\n";

$results[] = ['legal_briefs table', Schema::hasTable('legal_briefs') ? 'yes' : 'no', Schema::hasTable('legal_briefs') ? 'OK' : 'FAIL'];
$results[] = ['brief_citations table', Schema::hasTable('brief_citations') ? 'yes' : 'no', Schema::hasTable('brief_citations') ? 'OK' : 'FAIL'];

foreach (['briefs.view', 'briefs.create', 'briefs.update', 'briefs.delete'] as $perm) {
    Permission::findOrCreate($perm, 'web');
}
$admin->givePermissionTo([
    'briefs.view', 'briefs.create', 'briefs.update', 'briefs.delete',
]);
Permission::findOrCreate('ai.use', 'web');
$admin->givePermissionTo('ai.use');

$briefMatter = LegalMatter::query()->firstOrCreate(
    [
        'organization_id' => $organization->id,
        'matter_number' => 'P5-BRIEF-001',
    ],
    [
        'client_id' => $client->id,
        'title' => 'Phase 5 Brief Matter',
        'status' => 'open',
        'court_jurisdiction' => 'U.S. District Court',
        'case_type' => 'civil',
        'lead_lawyer_id' => $admin->id,
        'created_by' => $admin->id,
    ]
);

$briefRes = call($http, $token, 'POST', '/legal-briefs', [
    'legal_matter_id' => $briefMatter->id,
    'title' => 'Opposition Brief',
    'content_html' => '<p>Initial draft argument.</p>',
]);
record($results, 'POST /legal-briefs', $briefRes, [201]);
$ids['brief'] = resId($briefRes);
$briefBody = $briefRes['body'] ?? [];
$results[] = [
    '  brief status draft',
    ($briefBody['status'] ?? '') === 'draft' ? 'yes' : 'no',
    ($briefBody['status'] ?? '') === 'draft' ? 'OK' : 'FAIL',
];

$listBriefsRes = call($http, $token, 'GET', '/legal-briefs?legal_matter_id=' . $briefMatter->id);
record($results, 'GET /legal-briefs (scoped)', $listBriefsRes, [200]);
$listBriefsBody = $listBriefsRes['body'] ?? [];
$results[] = [
    '  briefs meta statuses',
    isset($listBriefsBody['meta']['statuses']) && is_array($listBriefsBody['meta']['statuses']) ? 'yes' : 'no',
    isset($listBriefsBody['meta']['statuses']) && is_array($listBriefsBody['meta']['statuses']) ? 'OK' : 'FAIL',
];

if ($ids['brief']) {
    $citationRes = call($http, $token, 'POST', "/legal-briefs/{$ids['brief']}/citations", [
        'authority' => 'case',
        'citation_text' => 'Example v. Sample, 123 F.3d 456 (9th Cir. 2020)',
        'source_note' => 'Supports summary judgment standard.',
    ]);
    record($results, 'POST /legal-briefs/{id}/citations', $citationRes, [201]);

    $citationsListRes = call($http, $token, 'GET', "/legal-briefs/{$ids['brief']}/citations");
    record($results, 'GET /legal-briefs/{id}/citations', $citationsListRes, [200]);
    $citationsList = $citationsListRes['body']['data'] ?? $citationsListRes['body'] ?? [];
    $results[] = [
        '  citations non-empty',
        is_array($citationsList) && count($citationsList) >= 1 ? 'yes' : 'no',
        is_array($citationsList) && count($citationsList) >= 1 ? 'OK' : 'FAIL',
    ];

    $reviewRes = call($http, $token, 'PATCH', "/legal-briefs/{$ids['brief']}/status", [
        'status' => 'review',
    ]);
    record($results, 'PATCH /legal-briefs/{id}/status (review)', $reviewRes, [200]);

    $finalRes = call($http, $token, 'PATCH', "/legal-briefs/{$ids['brief']}/status", [
        'status' => 'final',
    ]);
    record($results, 'PATCH /legal-briefs/{id}/status (final)', $finalRes, [200]);

    $invalidRes = call($http, $token, 'PATCH', "/legal-briefs/{$ids['brief']}/status", [
        'status' => 'draft',
    ]);
    record($results, 'PATCH /legal-briefs invalid transition', $invalidRes, [422]);

    $outlineRes = call($http, $token, 'POST', '/ai/brief/outline', [
        'legal_matter_id' => $briefMatter->id,
        'title' => 'Opposition Brief',
        'issue' => 'Summary judgment standard',
    ]);
    record($results, 'POST /ai/brief/outline', $outlineRes, [200]);
    $outlineBody = $outlineRes['body'] ?? [];
    $results[] = [
        '  outline requires review',
        ! empty($outlineBody['requires_review']) ? 'yes' : 'no',
        ! empty($outlineBody['requires_review']) ? 'OK' : 'FAIL',
    ];
    $results[] = [
        '  outline governance log',
        ! empty($outlineBody['governance_log_id']) ? 'yes' : 'no',
        ! empty($outlineBody['governance_log_id']) ? 'OK' : 'FAIL',
    ];

    $rewriteRes = call($http, $token, 'POST', '/ai/brief/rewrite', [
        'legal_matter_id' => $briefMatter->id,
        'section_html' => '<p>Argument section to rewrite.</p>',
        'instruction' => 'Improve clarity',
    ]);
    record($results, 'POST /ai/brief/rewrite', $rewriteRes, [200]);
}

$consultantBriefs = call($http, $consultantToken, 'GET', '/legal-briefs');
record($results, 'GET /legal-briefs (consultant blocked)', $consultantBriefs, [403]);

$results[] = [
    'LegalBrief::STATUSES count',
    (string) count(LegalBrief::STATUSES),
    count(LegalBrief::STATUSES) === 3 ? 'OK' : 'FAIL',
];
$results[] = [
    'LegalBrief model exists',
    class_exists(LegalBrief::class) ? 'yes' : 'no',
    class_exists(LegalBrief::class) ? 'OK' : 'FAIL',
];
$results[] = [
    'BriefCitation model exists',
    class_exists(BriefCitation::class) ? 'yes' : 'no',
    class_exists(BriefCitation::class) ? 'OK' : 'FAIL',
];

$briefsView = __DIR__ . '/../frontend/src/views/BriefsView.vue';
$briefsPanel = __DIR__ . '/../frontend/src/components/cases/CaseBriefsPanel.vue';
$briefEditorView = __DIR__ . '/../frontend/src/views/briefs/BriefEditorView.vue';
$briefsUiChecks = [
    'BriefsView.vue exists' => file_exists($briefsView),
    'CaseBriefsPanel.vue exists' => file_exists($briefsPanel),
    'BriefEditorView.vue exists' => file_exists($briefEditorView),
    'CaseBriefsPanel create wiring' => str_contains((string) @file_get_contents($briefsPanel), 'briefsApi.create'),
    'BriefEditorView RichTextEditor' => str_contains((string) @file_get_contents($briefEditorView), 'RichTextEditor'),
    'BriefEditorView AI outline' => str_contains((string) @file_get_contents($briefEditorView), 'briefOutline'),
    'CaseDetailView briefs tab' => str_contains((string) @file_get_contents($caseDetailView), "key: 'briefs'"),
    'Router briefs route' => str_contains($routerSource, "name: 'briefs'"),
    'Router briefs workspace tab' => str_contains($routerSource, '|briefs'),
    'Sidebar briefs nav' => str_contains($sidebarSource, "name: 'briefs'"),
];
foreach ($briefsUiChecks as $label => $ok) {
    $results[] = [$label, $ok ? 'yes' : 'no', $ok ? 'OK' : 'FAIL'];
}

echo "\n=== PHASE 5 SLICE 4: MOTION WRITING ===\n";

$results[] = ['motion_templates table', Schema::hasTable('motion_templates') ? 'yes' : 'no', Schema::hasTable('motion_templates') ? 'OK' : 'FAIL'];
$results[] = ['legal_motions table', Schema::hasTable('legal_motions') ? 'yes' : 'no', Schema::hasTable('legal_motions') ? 'OK' : 'FAIL'];
$results[] = ['court_filings.legal_motion_id column', Schema::hasColumn('court_filings', 'legal_motion_id') ? 'yes' : 'no', Schema::hasColumn('court_filings', 'legal_motion_id') ? 'OK' : 'FAIL'];

foreach (['motions.view', 'motions.create', 'motions.update', 'motions.delete'] as $perm) {
    Permission::findOrCreate($perm, 'web');
}
$admin->givePermissionTo([
    'motions.view', 'motions.create', 'motions.update', 'motions.delete',
]);

(new MotionTemplateSeeder())->run();
$templateCount = MotionTemplate::query()->whereNull('organization_id')->count();
$results[] = ['motion templates seeded', (string) $templateCount, $templateCount >= 8 ? 'OK' : 'FAIL'];

$templatesRes = call($http, $token, 'GET', '/motion-templates');
record($results, 'GET /motion-templates', $templatesRes, [200]);
$templatesBody = $templatesRes['body'] ?? [];
$motionTemplates = is_array($templatesBody) && array_is_list($templatesBody)
    ? $templatesBody
    : ($templatesBody['data'] ?? []);
$motionTemplateId = isset($motionTemplates[0]['id']) ? (int) $motionTemplates[0]['id'] : null;
$results[] = [
    '  motion templates non-empty',
    $motionTemplateId ? 'yes' : 'no',
    $motionTemplateId ? 'OK' : 'FAIL',
];

$motionMatter = LegalMatter::query()->firstOrCreate(
    [
        'organization_id' => $organization->id,
        'matter_number' => 'P5-MOTION-001',
    ],
    [
        'client_id' => $client->id,
        'title' => 'Phase 5 Motion Matter',
        'status' => 'open',
        'court_jurisdiction' => 'U.S. District Court',
        'case_type' => 'civil',
        'lead_lawyer_id' => $admin->id,
        'created_by' => $admin->id,
    ]
);

$motionRes = call($http, $token, 'POST', '/legal-motions', [
    'legal_matter_id' => $motionMatter->id,
    'title' => 'Motion to Dismiss',
    'motion_template_id' => $motionTemplateId,
]);
record($results, 'POST /legal-motions', $motionRes, [201]);
$ids['motion'] = resId($motionRes);
$motionBody = $motionRes['body'] ?? [];
$results[] = [
    '  motion status draft',
    ($motionBody['status'] ?? '') === 'draft' ? 'yes' : 'no',
    ($motionBody['status'] ?? '') === 'draft' ? 'OK' : 'FAIL',
];
$results[] = [
    '  motion template applied',
    ! empty($motionBody['content_html']) ? 'yes' : 'no',
    ! empty($motionBody['content_html']) ? 'OK' : 'FAIL',
];

$listMotionsRes = call($http, $token, 'GET', '/legal-motions?legal_matter_id=' . $motionMatter->id);
record($results, 'GET /legal-motions (scoped)', $listMotionsRes, [200]);
$listMotionsBody = $listMotionsRes['body'] ?? [];
$results[] = [
    '  motions meta statuses',
    isset($listMotionsBody['meta']['statuses']) && is_array($listMotionsBody['meta']['statuses']) ? 'yes' : 'no',
    isset($listMotionsBody['meta']['statuses']) && is_array($listMotionsBody['meta']['statuses']) ? 'OK' : 'FAIL',
];

if ($ids['motion']) {
    $reviewRes = call($http, $token, 'PATCH', "/legal-motions/{$ids['motion']}/status", [
        'status' => 'review',
    ]);
    record($results, 'PATCH /legal-motions/{id}/status (review)', $reviewRes, [200]);

    $approvedRes = call($http, $token, 'PATCH', "/legal-motions/{$ids['motion']}/status", [
        'status' => 'approved',
    ]);
    record($results, 'PATCH /legal-motions/{id}/status (approved)', $approvedRes, [200]);

    $structureRes = call($http, $token, 'POST', '/ai/motion/structure-check', [
        'legal_matter_id' => $motionMatter->id,
        'title' => 'Motion to Dismiss',
        'motion_type' => 'motion_to_dismiss',
        'content_html' => '<h1>Motion</h1><p>Argument section.</p>',
        'required_sections' => ['caption', 'introduction', 'argument', 'conclusion'],
    ]);
    record($results, 'POST /ai/motion/structure-check', $structureRes, [200]);
    $structureBody = $structureRes['body'] ?? [];
    $results[] = [
        '  structure check requires review',
        ! empty($structureBody['requires_review']) ? 'yes' : 'no',
        ! empty($structureBody['requires_review']) ? 'OK' : 'FAIL',
    ];
    $results[] = [
        '  structure check governance log',
        ! empty($structureBody['governance_log_id']) ? 'yes' : 'no',
        ! empty($structureBody['governance_log_id']) ? 'OK' : 'FAIL',
    ];

    $filingFromMotionRes = call($http, $token, 'POST', "/legal-motions/{$ids['motion']}/create-filing");
    record($results, 'POST /legal-motions/{id}/create-filing', $filingFromMotionRes, [201]);
    $filingFromMotionBody = $filingFromMotionRes['body'] ?? [];
    $filingFromMotionData = $filingFromMotionBody['data'] ?? $filingFromMotionBody;
    $results[] = [
        '  motion linked to filing',
        ! empty($filingFromMotionData['court_filing_id']) ? 'yes' : 'no',
        ! empty($filingFromMotionData['court_filing_id']) ? 'OK' : 'FAIL',
    ];
    $results[] = [
        '  motion status filing_ready',
        ($filingFromMotionData['status'] ?? '') === 'filing_ready' ? 'yes' : 'no',
        ($filingFromMotionData['status'] ?? '') === 'filing_ready' ? 'OK' : 'FAIL',
    ];

    $invalidRes = call($http, $token, 'PATCH', "/legal-motions/{$ids['motion']}/status", [
        'status' => 'draft',
    ]);
    record($results, 'PATCH motion invalid transition', $invalidRes, [422]);
}

$consultantMotions = call($http, $consultantToken, 'GET', '/legal-motions');
record($results, 'GET /legal-motions (consultant blocked)', $consultantMotions, [403]);

$results[] = [
    'LegalMotion::STATUSES count',
    (string) count(LegalMotion::STATUSES),
    count(LegalMotion::STATUSES) === 4 ? 'OK' : 'FAIL',
];
$results[] = [
    'LegalMotion model exists',
    class_exists(LegalMotion::class) ? 'yes' : 'no',
    class_exists(LegalMotion::class) ? 'OK' : 'FAIL',
];
$results[] = [
    'MotionTemplate model exists',
    class_exists(MotionTemplate::class) ? 'yes' : 'no',
    class_exists(MotionTemplate::class) ? 'OK' : 'FAIL',
];

$motionsView = __DIR__ . '/../frontend/src/views/MotionsView.vue';
$motionsPanel = __DIR__ . '/../frontend/src/components/cases/CaseMotionsPanel.vue';
$motionEditorView = __DIR__ . '/../frontend/src/views/motions/MotionEditorView.vue';
$motionsUiChecks = [
    'MotionsView.vue exists' => file_exists($motionsView),
    'CaseMotionsPanel.vue exists' => file_exists($motionsPanel),
    'MotionEditorView.vue exists' => file_exists($motionEditorView),
    'CaseMotionsPanel create wiring' => str_contains((string) @file_get_contents($motionsPanel), 'motionsApi.create'),
    'MotionEditorView structure check' => str_contains((string) @file_get_contents($motionEditorView), 'motionStructureCheck'),
    'CaseDetailView motions tab' => str_contains((string) @file_get_contents($caseDetailView), "key: 'motions'"),
    'Router motions route' => str_contains($routerSource, "name: 'motions'"),
    'Router motions workspace tab' => str_contains($routerSource, '|motions'),
    'Sidebar motions nav' => str_contains($sidebarSource, "name: 'motions'"),
];
foreach ($motionsUiChecks as $label => $ok) {
    $results[] = [$label, $ok ? 'yes' : 'no', $ok ? 'OK' : 'FAIL'];
}

echo "\n=== PHASE 5 SLICE 5: LEGAL RESEARCH ===\n";

$results[] = ['legal_research_entries table', Schema::hasTable('legal_research_entries') ? 'yes' : 'no', Schema::hasTable('legal_research_entries') ? 'OK' : 'FAIL'];
$results[] = ['research_folders table', Schema::hasTable('research_folders') ? 'yes' : 'no', Schema::hasTable('research_folders') ? 'OK' : 'FAIL'];
$results[] = ['research_saved_items table', Schema::hasTable('research_saved_items') ? 'yes' : 'no', Schema::hasTable('research_saved_items') ? 'OK' : 'FAIL'];

foreach (['research.view', 'research.create', 'research.update', 'research.delete'] as $perm) {
    Permission::findOrCreate($perm, 'web');
}
$admin->givePermissionTo([
    'research.view', 'research.create', 'research.update', 'research.delete',
]);

(new LegalResearchEntrySeeder())->run();
$starterCount = LegalResearchEntry::query()->whereNull('organization_id')->count();
$results[] = ['research starter entries seeded', (string) $starterCount, $starterCount >= 5 ? 'OK' : 'FAIL'];

$researchMatter = LegalMatter::query()->firstOrCreate(
    [
        'organization_id' => $organization->id,
        'matter_number' => 'P5-RESEARCH-001',
    ],
    [
        'client_id' => $client->id,
        'title' => 'Phase 5 Research Matter',
        'status' => 'open',
        'court_jurisdiction' => 'U.S. District Court',
        'case_type' => 'civil',
        'lead_lawyer_id' => $admin->id,
        'created_by' => $admin->id,
    ]
);

$entryRes = call($http, $token, 'POST', '/legal-research-entries', [
    'title' => 'Internal Research Note',
    'citation' => 'Firm Memo 2026-01',
    'summary' => 'Summary judgment burden-shifting checklist.',
    'jurisdiction' => 'Federal',
    'document_type' => 'note',
    'tags' => ['summary judgment', 'checklist'],
]);
record($results, 'POST /legal-research-entries', $entryRes, [201]);
$ids['research_entry'] = resId($entryRes);

$listEntriesRes = call($http, $token, 'GET', '/legal-research-entries?keyword=summary');
record($results, 'GET /legal-research-entries (keyword search)', $listEntriesRes, [200]);
$listEntriesBody = $listEntriesRes['body'] ?? [];
$results[] = [
    '  entries meta document_types',
    isset($listEntriesBody['meta']['document_types']) && is_array($listEntriesBody['meta']['document_types']) ? 'yes' : 'no',
    isset($listEntriesBody['meta']['document_types']) && is_array($listEntriesBody['meta']['document_types']) ? 'OK' : 'FAIL',
];
$entryRows = $listEntriesBody['data'] ?? [];
$results[] = [
    '  keyword search non-empty',
    is_array($entryRows) && count($entryRows) >= 1 ? 'yes' : 'no',
    is_array($entryRows) && count($entryRows) >= 1 ? 'OK' : 'FAIL',
];

$filterRes = call($http, $token, 'GET', '/legal-research-entries?jurisdiction=Federal&document_type=note');
record($results, 'GET /legal-research-entries (filters)', $filterRes, [200]);

if ($ids['research_entry']) {
    $updateEntryRes = call($http, $token, 'PUT', "/legal-research-entries/{$ids['research_entry']}", [
        'summary' => 'Updated summary judgment checklist.',
    ]);
    record($results, 'PUT /legal-research-entries/{id}', $updateEntryRes, [200]);
}

$folderRes = call($http, $token, 'POST', '/research-folders', [
    'legal_matter_id' => $researchMatter->id,
    'name' => 'Summary Judgment Research',
    'practice_area' => 'Civil litigation',
    'legal_issue' => 'Summary judgment standard',
]);
record($results, 'POST /research-folders', $folderRes, [201]);
$ids['research_folder'] = resId($folderRes);

$listFoldersRes = call($http, $token, 'GET', '/research-folders?legal_matter_id=' . $researchMatter->id);
record($results, 'GET /research-folders (scoped)', $listFoldersRes, [200]);

$starterEntry = LegalResearchEntry::query()->whereNull('organization_id')->first();
$saveEntryId = $ids['research_entry'] ?: $starterEntry?->id;
if ($ids['research_folder'] && $saveEntryId) {
    $saveItemRes = call($http, $token, 'POST', "/research-folders/{$ids['research_folder']}/items", [
        'legal_research_entry_id' => $saveEntryId,
        'legal_matter_id' => $researchMatter->id,
        'notes' => 'Key authority for motion practice.',
    ]);
    record($results, 'POST /research-folders/{id}/items', $saveItemRes, [201]);
    $ids['research_saved_item'] = resId($saveItemRes);

    $folderItemsRes = call($http, $token, 'GET', "/research-folders/{$ids['research_folder']}/items");
    record($results, 'GET /research-folders/{id}/items', $folderItemsRes, [200]);
    $folderItems = $folderItemsRes['body']['data'] ?? $folderItemsRes['body'] ?? [];
    $results[] = [
        '  folder items non-empty',
        is_array($folderItems) && count($folderItems) >= 1 ? 'yes' : 'no',
        is_array($folderItems) && count($folderItems) >= 1 ? 'OK' : 'FAIL',
    ];

    $duplicateRes = call($http, $token, 'POST', "/research-folders/{$ids['research_folder']}/items", [
        'legal_research_entry_id' => $saveEntryId,
    ]);
    record($results, 'POST duplicate saved item blocked', $duplicateRes, [422]);
}

$listSavedRes = call($http, $token, 'GET', '/research-saved-items?legal_matter_id=' . $researchMatter->id);
record($results, 'GET /research-saved-items (scoped)', $listSavedRes, [200]);

if ($ids['research_saved_item']) {
    $deleteSavedRes = call($http, $token, 'DELETE', "/research-saved-items/{$ids['research_saved_item']}");
    record($results, 'DELETE /research-saved-items/{id}', $deleteSavedRes, [200]);
}

$consultantResearch = call($http, $consultantToken, 'GET', '/legal-research-entries');
record($results, 'GET /legal-research-entries (consultant blocked)', $consultantResearch, [403]);

$results[] = [
    'LegalResearchEntry::DOCUMENT_TYPES count',
    (string) count(LegalResearchEntry::DOCUMENT_TYPES),
    count(LegalResearchEntry::DOCUMENT_TYPES) >= 7 ? 'OK' : 'FAIL',
];
$results[] = [
    'LegalResearchEntry model exists',
    class_exists(LegalResearchEntry::class) ? 'yes' : 'no',
    class_exists(LegalResearchEntry::class) ? 'OK' : 'FAIL',
];
$results[] = [
    'ResearchFolder model exists',
    class_exists(ResearchFolder::class) ? 'yes' : 'no',
    class_exists(ResearchFolder::class) ? 'OK' : 'FAIL',
];
$results[] = [
    'ResearchSavedItem model exists',
    class_exists(ResearchSavedItem::class) ? 'yes' : 'no',
    class_exists(ResearchSavedItem::class) ? 'OK' : 'FAIL',
];

$researchView = __DIR__ . '/../frontend/src/views/ResearchView.vue';
$researchPanel = __DIR__ . '/../frontend/src/components/cases/CaseResearchPanel.vue';
$researchUiChecks = [
    'ResearchView.vue exists' => file_exists($researchView),
    'CaseResearchPanel library search' => str_contains((string) @file_get_contents($researchPanel), 'researchEntriesApi.list'),
    'CaseResearchPanel saved items' => str_contains((string) @file_get_contents($researchPanel), 'researchSavedItemsApi.list'),
    'CaseResearchPanel folder create' => str_contains((string) @file_get_contents($researchPanel), 'researchFoldersApi.create'),
    'ResearchView search filters' => str_contains((string) @file_get_contents($researchView), 'document_type'),
    'Router research route' => str_contains($routerSource, "name: 'research'"),
    'Sidebar research nav' => str_contains($sidebarSource, "name: 'research'"),
];
foreach ($researchUiChecks as $label => $ok) {
    $results[] = [$label, $ok ? 'yes' : 'no', $ok ? 'OK' : 'FAIL'];
}

echo "\n=== PHASE 5 SLICE 6: E-DISCOVERY ===\n";

$results[] = ['ediscovery_collections table', Schema::hasTable('ediscovery_collections') ? 'yes' : 'no', Schema::hasTable('ediscovery_collections') ? 'OK' : 'FAIL'];
$results[] = ['ediscovery_documents table', Schema::hasTable('ediscovery_documents') ? 'yes' : 'no', Schema::hasTable('ediscovery_documents') ? 'OK' : 'FAIL'];
$results[] = ['ediscovery_tags table', Schema::hasTable('ediscovery_tags') ? 'yes' : 'no', Schema::hasTable('ediscovery_tags') ? 'OK' : 'FAIL'];
$results[] = ['ediscovery_review_assignments table', Schema::hasTable('ediscovery_review_assignments') ? 'yes' : 'no', Schema::hasTable('ediscovery_review_assignments') ? 'OK' : 'FAIL'];

foreach (['ediscovery.view', 'ediscovery.create', 'ediscovery.update', 'ediscovery.delete'] as $perm) {
    Permission::findOrCreate($perm, 'web');
}
$admin->givePermissionTo([
    'ediscovery.view', 'ediscovery.create', 'ediscovery.update', 'ediscovery.delete',
]);

$ediscoveryMatter = LegalMatter::query()->firstOrCreate(
    [
        'organization_id' => $organization->id,
        'matter_number' => 'P5-EDISCOVERY-001',
    ],
    [
        'client_id' => $client->id,
        'title' => 'Phase 5 E-discovery Matter',
        'status' => 'open',
        'court_jurisdiction' => 'U.S. District Court',
        'case_type' => 'civil',
        'lead_lawyer_id' => $admin->id,
        'created_by' => $admin->id,
    ]
);

$collectionRes = call($http, $token, 'POST', '/ediscovery-collections', [
    'legal_matter_id' => $ediscoveryMatter->id,
    'name' => 'Initial Production Set',
    'description' => 'First document production batch.',
]);
record($results, 'POST /ediscovery-collections', $collectionRes, [201]);
$ids['ediscovery_collection'] = resId($collectionRes);
$collectionBody = $collectionRes['body'] ?? [];
$results[] = [
    '  collection status open',
    ($collectionBody['status'] ?? '') === 'open' ? 'yes' : 'no',
    ($collectionBody['status'] ?? '') === 'open' ? 'OK' : 'FAIL',
];

$listCollectionsRes = call($http, $token, 'GET', '/ediscovery-collections?legal_matter_id=' . $ediscoveryMatter->id);
record($results, 'GET /ediscovery-collections (scoped)', $listCollectionsRes, [200]);

$ediscoveryTagName = 'Hot Document ' . uniqid();
$tagRes = call($http, $token, 'POST', '/ediscovery-tags', [
    'legal_matter_id' => $ediscoveryMatter->id,
    'name' => $ediscoveryTagName,
    'category' => 'custom',
]);
record($results, 'POST /ediscovery-tags', $tagRes, [201]);
$ids['ediscovery_tag'] = resId($tagRes);

$uploadTmp1 = tempnam(sys_get_temp_dir(), 'p5edisc1') . '.txt';
file_put_contents($uploadTmp1, 'Phase 5 discovery document one');
$uploadTmp2 = tempnam(sys_get_temp_dir(), 'p5edisc2') . '.txt';
file_put_contents($uploadTmp2, 'Phase 5 discovery document two');
$bulkFile1 = new UploadedFile($uploadTmp1, 'email-thread-001.txt', 'text/plain', null, true);
$bulkFile2 = new UploadedFile($uploadTmp2, 'contract-draft-002.txt', 'text/plain', null, true);

if ($ids['ediscovery_collection']) {
    $bulkRes = call($http, $token, 'POST', '/ediscovery-documents/bulk-upload', [
        'legal_matter_id' => (string) $ediscoveryMatter->id,
        'ediscovery_collection_id' => (string) $ids['ediscovery_collection'],
        'default_privilege' => 'none',
        'default_relevance' => 'needs_review',
        'custom_tags' => ['production-batch-1'],
    ], ['files' => [$bulkFile1, $bulkFile2]]);
    record($results, 'POST /ediscovery-documents/bulk-upload', $bulkRes, [201]);
    $bulkBody = $bulkRes['body'] ?? [];
    $results[] = [
        '  bulk upload count',
        (string) ($bulkBody['count'] ?? 0),
        ($bulkBody['count'] ?? 0) >= 2 ? 'OK' : 'FAIL',
    ];
    $bulkDocs = $bulkBody['data'] ?? [];
    $ids['ediscovery_document'] = isset($bulkDocs[0]['id']) ? (int) $bulkDocs[0]['id'] : null;
}

$listDocsRes = call($http, $token, 'GET', '/ediscovery-documents?legal_matter_id=' . $ediscoveryMatter->id);
record($results, 'GET /ediscovery-documents (scoped)', $listDocsRes, [200]);
$listDocsBody = $listDocsRes['body'] ?? [];
$results[] = [
    '  documents meta privileges',
    isset($listDocsBody['meta']['privileges']) && is_array($listDocsBody['meta']['privileges']) ? 'yes' : 'no',
    isset($listDocsBody['meta']['privileges']) && is_array($listDocsBody['meta']['privileges']) ? 'OK' : 'FAIL',
];

if ($ids['ediscovery_document']) {
    $tagDocRes = call($http, $token, 'PATCH', "/ediscovery-documents/{$ids['ediscovery_document']}/tags", [
        'privilege' => 'attorney_client',
        'relevance' => 'hot',
        'custom_tags' => [$ediscoveryTagName, 'production-batch-1'],
        'notes' => 'Attorney-client communication thread.',
    ]);
    record($results, 'PATCH /ediscovery-documents/{id}/tags', $tagDocRes, [200]);
    $tagDocBody = $tagDocRes['body'] ?? [];
    $results[] = [
        '  privilege tagged',
        ($tagDocBody['privilege'] ?? '') === 'attorney_client' ? 'yes' : 'no',
        ($tagDocBody['privilege'] ?? '') === 'attorney_client' ? 'OK' : 'FAIL',
    ];
    $results[] = [
        '  relevance tagged',
        ($tagDocBody['relevance'] ?? '') === 'hot' ? 'yes' : 'no',
        ($tagDocBody['relevance'] ?? '') === 'hot' ? 'OK' : 'FAIL',
    ];

    $reviewRes = call($http, $token, 'PATCH', "/ediscovery-documents/{$ids['ediscovery_document']}/review-status", [
        'review_status' => 'in_progress',
    ]);
    record($results, 'PATCH /ediscovery-documents/{id}/review-status (in_progress)', $reviewRes, [200]);

    $assignRes = call($http, $token, 'POST', '/ediscovery-review-assignments', [
        'ediscovery_document_id' => $ids['ediscovery_document'],
        'reviewer_id' => $admin->id,
        'notes' => 'Primary reviewer for hot docs.',
    ]);
    record($results, 'POST /ediscovery-review-assignments', $assignRes, [201]);
    $ids['ediscovery_assignment'] = resId($assignRes);

    if ($ids['ediscovery_assignment']) {
        $updateAssignRes = call($http, $token, 'PUT', "/ediscovery-review-assignments/{$ids['ediscovery_assignment']}", [
            'review_status' => 'in_progress',
        ]);
        record($results, 'PUT /ediscovery-review-assignments/{id}', $updateAssignRes, [200]);
    }

    $reviewedRes = call($http, $token, 'PATCH', "/ediscovery-documents/{$ids['ediscovery_document']}/review-status", [
        'review_status' => 'reviewed',
    ]);
    record($results, 'PATCH /ediscovery-documents/{id}/review-status (reviewed)', $reviewedRes, [200]);

    $invalidRes = call($http, $token, 'PATCH', "/ediscovery-documents/{$ids['ediscovery_document']}/review-status", [
        'review_status' => 'pending',
    ]);
    record($results, 'PATCH review-status invalid transition', $invalidRes, [422]);
}

$filterPrivilegeRes = call($http, $token, 'GET', '/ediscovery-documents?legal_matter_id=' . $ediscoveryMatter->id . '&privilege=attorney_client');
record($results, 'GET /ediscovery-documents (privilege filter)', $filterPrivilegeRes, [200]);
$filterPrivilegeBody = $filterPrivilegeRes['body'] ?? [];
$privilegeRows = $filterPrivilegeBody['data'] ?? [];
$results[] = [
    '  privilege filter non-empty',
    is_array($privilegeRows) && count($privilegeRows) >= 1 ? 'yes' : 'no',
    is_array($privilegeRows) && count($privilegeRows) >= 1 ? 'OK' : 'FAIL',
];

$filterTagRes = call($http, $token, 'GET', '/ediscovery-documents?legal_matter_id=' . $ediscoveryMatter->id . '&tag=' . rawurlencode($ediscoveryTagName));
record($results, 'GET /ediscovery-documents (tag filter)', $filterTagRes, [200]);
$filterTagBody = $filterTagRes['body'] ?? [];
$filterTagRows = $filterTagBody['data'] ?? [];
$results[] = [
    '  tag filter non-empty',
    is_array($filterTagRows) && count($filterTagRows) >= 1 ? 'yes' : 'no',
    is_array($filterTagRows) && count($filterTagRows) >= 1 ? 'OK' : 'FAIL',
];

$filterReviewerRes = call($http, $token, 'GET', '/ediscovery-documents?legal_matter_id=' . $ediscoveryMatter->id . '&reviewer_id=' . $admin->id);
record($results, 'GET /ediscovery-documents (reviewer filter)', $filterReviewerRes, [200]);

$progressRes = call($http, $token, 'GET', '/ediscovery-review-progress?legal_matter_id=' . $ediscoveryMatter->id);
record($results, 'GET /ediscovery-review-progress', $progressRes, [200]);
$progressBody = $progressRes['body'] ?? [];
$results[] = [
    '  progress has reviewers',
    isset($progressBody['by_reviewer']) && is_array($progressBody['by_reviewer']) && count($progressBody['by_reviewer']) >= 1 ? 'yes' : 'no',
    isset($progressBody['by_reviewer']) && is_array($progressBody['by_reviewer']) && count($progressBody['by_reviewer']) >= 1 ? 'OK' : 'FAIL',
];
$results[] = [
    '  progress total documents',
    (string) ($progressBody['total_documents'] ?? 0),
    ($progressBody['total_documents'] ?? 0) >= 2 ? 'OK' : 'FAIL',
];

for ($i = 3; $i <= 52; $i++) {
    EdiscoveryDocument::query()->create([
        'organization_id' => $organization->id,
        'legal_matter_id' => $ediscoveryMatter->id,
        'ediscovery_collection_id' => $ids['ediscovery_collection'],
        'title' => "Scale test document {$i}",
        'privilege' => $i % 4 === 0 ? 'privileged' : 'none',
        'relevance' => $i % 3 === 0 ? 'responsive' : 'needs_review',
        'custom_tags' => ['scale-test'],
        'review_status' => $i % 5 === 0 ? 'reviewed' : 'pending',
        'file_type' => 'document',
        'content_preview' => "Scale test content {$i}",
    ]);
}
$scaleFilterRes = call($http, $token, 'GET', '/ediscovery-documents?legal_matter_id=' . $ediscoveryMatter->id . '&relevance=responsive&review_status=reviewed');
record($results, 'GET /ediscovery-documents (scale filters)', $scaleFilterRes, [200]);
$scaleCount = count($scaleFilterRes['body']['data'] ?? []);
$results[] = [
    '  scale filter returns results',
    (string) $scaleCount,
    $scaleCount >= 1 ? 'OK' : 'FAIL',
];

$consultantEdiscovery = call($http, $consultantToken, 'GET', '/ediscovery-documents');
record($results, 'GET /ediscovery-documents (consultant blocked)', $consultantEdiscovery, [403]);

$results[] = [
    'EdiscoveryDocument::PRIVILEGES count',
    (string) count(EdiscoveryDocument::PRIVILEGES),
    count(EdiscoveryDocument::PRIVILEGES) >= 4 ? 'OK' : 'FAIL',
];
$results[] = [
    'EdiscoveryDocument::REVIEW_STATUSES count',
    (string) count(EdiscoveryDocument::REVIEW_STATUSES),
    count(EdiscoveryDocument::REVIEW_STATUSES) === 4 ? 'OK' : 'FAIL',
];
$results[] = [
    'EdiscoveryCollection model exists',
    class_exists(EdiscoveryCollection::class) ? 'yes' : 'no',
    class_exists(EdiscoveryCollection::class) ? 'OK' : 'FAIL',
];
$results[] = [
    'EdiscoveryReviewAssignment model exists',
    class_exists(EdiscoveryReviewAssignment::class) ? 'yes' : 'no',
    class_exists(EdiscoveryReviewAssignment::class) ? 'OK' : 'FAIL',
];
$results[] = [
    'EdiscoveryTag model exists',
    class_exists(EdiscoveryTag::class) ? 'yes' : 'no',
    class_exists(EdiscoveryTag::class) ? 'OK' : 'FAIL',
];

$ediscoveryView = __DIR__ . '/../frontend/src/views/EdiscoveryView.vue';
$ediscoveryPanel = __DIR__ . '/../frontend/src/components/cases/CaseEdiscoveryPanel.vue';
$ediscoveryUiChecks = [
    'EdiscoveryView.vue exists' => file_exists($ediscoveryView),
    'CaseEdiscoveryPanel.vue exists' => file_exists($ediscoveryPanel),
    'CaseEdiscoveryPanel bulk upload wiring' => str_contains((string) @file_get_contents($ediscoveryPanel), 'ediscoveryApi.bulkUpload'),
    'CaseEdiscoveryPanel review progress' => str_contains((string) @file_get_contents($ediscoveryPanel), 'ediscoveryApi.reviewProgress'),
    'CaseEdiscoveryPanel tag updates' => str_contains((string) @file_get_contents($ediscoveryPanel), 'ediscoveryApi.updateTags'),
    'CaseDetailView e-discovery tab' => str_contains((string) @file_get_contents($caseDetailView), "key: 'e-discovery'"),
    'Router e-discovery route' => str_contains($routerSource, "name: 'e-discovery'"),
    'Router e-discovery workspace tab' => str_contains($routerSource, '|e-discovery'),
    'Sidebar e-discovery nav' => str_contains($sidebarSource, "name: 'e-discovery'"),
];
foreach ($ediscoveryUiChecks as $label => $ok) {
    $results[] = [$label, $ok ? 'yes' : 'no', $ok ? 'OK' : 'FAIL'];
}

echo "\n=== PHASE 5 SLICE 7: KNOWLEDGE MANAGEMENT ===\n";

$results[] = ['knowledge_articles table', Schema::hasTable('knowledge_articles') ? 'yes' : 'no', Schema::hasTable('knowledge_articles') ? 'OK' : 'FAIL'];

foreach (['knowledge.view', 'knowledge.create', 'knowledge.update', 'knowledge.delete'] as $perm) {
    Permission::findOrCreate($perm, 'web');
}
$admin->givePermissionTo([
    'knowledge.view', 'knowledge.create', 'knowledge.update', 'knowledge.delete',
]);

(new KnowledgeArticleSeeder())->run();
$starterKbCount = KnowledgeArticle::query()->whereNull('organization_id')->count();
$results[] = ['knowledge starter articles seeded', (string) $starterKbCount, $starterKbCount >= 5 ? 'OK' : 'FAIL'];

$knowledgeMatter = LegalMatter::query()->firstOrCreate(
    [
        'organization_id' => $organization->id,
        'matter_number' => 'P5-KNOWLEDGE-001',
    ],
    [
        'client_id' => $client->id,
        'title' => 'Phase 5 Knowledge Matter',
        'status' => 'open',
        'court_jurisdiction' => 'U.S. District Court',
        'case_type' => 'civil',
        'lead_lawyer_id' => $admin->id,
        'created_by' => $admin->id,
    ]
);

$articleRes = call($http, $token, 'POST', '/knowledge-articles', [
    'title' => 'Case Strategy — Discovery Plan',
    'excerpt' => 'Internal case strategy note for discovery sequencing.',
    'content' => 'Prioritize custodians with direct knowledge. Issue first RFP set within 30 days.',
    'content_type' => 'article',
    'category' => 'case_strategy',
    'practice_area' => 'Civil litigation',
    'legal_matter_id' => $knowledgeMatter->id,
    'tags' => ['discovery', 'strategy'],
]);
record($results, 'POST /knowledge-articles', $articleRes, [201]);
$ids['knowledge_article'] = resId($articleRes);
$articleBody = $articleRes['body'] ?? [];
$results[] = [
    '  article content_type article',
    ($articleBody['content_type'] ?? '') === 'article' ? 'yes' : 'no',
    ($articleBody['content_type'] ?? '') === 'article' ? 'OK' : 'FAIL',
];

$sopRes = call($http, $token, 'POST', '/knowledge-articles', [
    'title' => 'Deposition Prep SOP',
    'excerpt' => 'Firm SOP for preparing witnesses.',
    'content' => 'Schedule prep session. Review exhibits. Draft outline.',
    'content_type' => 'sop',
    'category' => 'sops',
    'tags' => ['depositions', 'witness prep'],
]);
record($results, 'POST /knowledge-articles (sop)', $sopRes, [201]);
$ids['knowledge_sop'] = resId($sopRes);

$clauseRes = call($http, $token, 'POST', '/knowledge-articles', [
    'title' => 'Forum Selection Clause',
    'excerpt' => 'Standard forum selection language.',
    'content' => 'The parties consent to exclusive jurisdiction in the courts of [County], [State].',
    'content_type' => 'clause_snippet',
    'category' => 'clauses',
    'tags' => ['jurisdiction', 'contracts'],
]);
record($results, 'POST /knowledge-articles (clause)', $clauseRes, [201]);
$ids['knowledge_clause'] = resId($clauseRes);

$listArticlesRes = call($http, $token, 'GET', '/knowledge-articles?keyword=discovery');
record($results, 'GET /knowledge-articles (keyword search)', $listArticlesRes, [200]);
$listArticlesBody = $listArticlesRes['body'] ?? [];
$results[] = [
    '  articles meta content_types',
    isset($listArticlesBody['meta']['content_types']) && is_array($listArticlesBody['meta']['content_types']) ? 'yes' : 'no',
    isset($listArticlesBody['meta']['content_types']) && is_array($listArticlesBody['meta']['content_types']) ? 'OK' : 'FAIL',
];
$results[] = [
    '  articles meta categories',
    isset($listArticlesBody['meta']['categories']) && is_array($listArticlesBody['meta']['categories']) ? 'yes' : 'no',
    isset($listArticlesBody['meta']['categories']) && is_array($listArticlesBody['meta']['categories']) ? 'OK' : 'FAIL',
];
$articleRows = $listArticlesBody['data'] ?? [];
$results[] = [
    '  keyword search non-empty',
    is_array($articleRows) && count($articleRows) >= 1 ? 'yes' : 'no',
    is_array($articleRows) && count($articleRows) >= 1 ? 'OK' : 'FAIL',
];

$categoryFilterRes = call($http, $token, 'GET', '/knowledge-articles?category=case_strategy');
record($results, 'GET /knowledge-articles (category filter)', $categoryFilterRes, [200]);

$typeFilterRes = call($http, $token, 'GET', '/knowledge-articles?content_type=sop');
record($results, 'GET /knowledge-articles (content_type filter)', $typeFilterRes, [200]);

$tagFilterRes = call($http, $token, 'GET', '/knowledge-articles?tag=depositions');
record($results, 'GET /knowledge-articles (tag filter)', $tagFilterRes, [200]);

$caseScopedRes = call($http, $token, 'GET', '/knowledge-articles?legal_matter_id=' . $knowledgeMatter->id);
record($results, 'GET /knowledge-articles (case scoped)', $caseScopedRes, [200]);
$caseScopedBody = $caseScopedRes['body'] ?? [];
$caseScopedRows = $caseScopedBody['data'] ?? [];
$results[] = [
    '  case scoped includes firm-wide',
    is_array($caseScopedRows) && count($caseScopedRows) >= 1 ? 'yes' : 'no',
    is_array($caseScopedRows) && count($caseScopedRows) >= 1 ? 'OK' : 'FAIL',
];

if ($ids['knowledge_article']) {
    $updateArticleRes = call($http, $token, 'PUT', "/knowledge-articles/{$ids['knowledge_article']}", [
        'excerpt' => 'Updated discovery sequencing plan.',
    ]);
    record($results, 'PUT /knowledge-articles/{id}', $updateArticleRes, [200]);

    $showArticleRes = call($http, $token, 'GET', "/knowledge-articles/{$ids['knowledge_article']}");
    record($results, 'GET /knowledge-articles/{id}', $showArticleRes, [200]);
}

$consultantKnowledge = call($http, $consultantToken, 'GET', '/knowledge-articles');
record($results, 'GET /knowledge-articles (consultant blocked)', $consultantKnowledge, [403]);

$results[] = [
    'KnowledgeArticle::CONTENT_TYPES count',
    (string) count(KnowledgeArticle::CONTENT_TYPES),
    count(KnowledgeArticle::CONTENT_TYPES) >= 7 ? 'OK' : 'FAIL',
];
$results[] = [
    'KnowledgeArticle::CATEGORIES count',
    (string) count(KnowledgeArticle::CATEGORIES),
    count(KnowledgeArticle::CATEGORIES) >= 9 ? 'OK' : 'FAIL',
];
$results[] = [
    'KnowledgeArticle model exists',
    class_exists(KnowledgeArticle::class) ? 'yes' : 'no',
    class_exists(KnowledgeArticle::class) ? 'OK' : 'FAIL',
];

$knowledgeView = __DIR__ . '/../frontend/src/views/KnowledgeView.vue';
$knowledgePanel = __DIR__ . '/../frontend/src/components/cases/CaseKnowledgePanel.vue';
$aiAssistantView = __DIR__ . '/../frontend/src/views/AiAssistantView.vue';
$knowledgeUiChecks = [
    'KnowledgeView.vue exists' => file_exists($knowledgeView),
    'CaseKnowledgePanel.vue exists' => file_exists($knowledgePanel),
    'KnowledgeView search filters' => str_contains((string) @file_get_contents($knowledgeView), 'content_type'),
    'KnowledgeView AI assistant link' => str_contains((string) @file_get_contents($knowledgeView), "name: 'ai-assistant'"),
    'CaseKnowledgePanel search wiring' => str_contains((string) @file_get_contents($knowledgePanel), 'knowledgeArticlesApi.list'),
    'CaseKnowledgePanel case notes' => str_contains((string) @file_get_contents($knowledgePanel), 'case_strategy'),
    'CaseDetailView knowledge tab' => str_contains((string) @file_get_contents($caseDetailView), "key: 'knowledge'"),
    'Router knowledge route' => str_contains($routerSource, "name: 'knowledge'"),
    'Router knowledge workspace tab' => str_contains($routerSource, '|knowledge'),
    'Sidebar knowledge nav' => str_contains($sidebarSource, "name: 'knowledge'"),
    'AiAssistantView prompt query' => str_contains((string) @file_get_contents($aiAssistantView), 'route.query.prompt'),
];
foreach ($knowledgeUiChecks as $label => $ok) {
    $results[] = [$label, $ok ? 'yes' : 'no', $ok ? 'OK' : 'FAIL'];
}

echo "\n=== PHASE 5 SLICE 8: OPERATIONS & LEARNING ===\n";

$results[] = ['legal_project_milestones table', Schema::hasTable('legal_project_milestones') ? 'yes' : 'no', Schema::hasTable('legal_project_milestones') ? 'OK' : 'FAIL'];
$results[] = ['legal_project_budgets table', Schema::hasTable('legal_project_budgets') ? 'yes' : 'no', Schema::hasTable('legal_project_budgets') ? 'OK' : 'FAIL'];
$results[] = ['training_courses table', Schema::hasTable('training_courses') ? 'yes' : 'no', Schema::hasTable('training_courses') ? 'OK' : 'FAIL'];
$results[] = ['training_enrollments table', Schema::hasTable('training_enrollments') ? 'yes' : 'no', Schema::hasTable('training_enrollments') ? 'OK' : 'FAIL'];
$results[] = ['training_certificates table', Schema::hasTable('training_certificates') ? 'yes' : 'no', Schema::hasTable('training_certificates') ? 'OK' : 'FAIL'];

foreach ([
    'projects.view', 'projects.create', 'projects.update', 'projects.delete',
    'analytics.view',
    'training.view', 'training.create', 'training.update', 'training.delete', 'training.assign',
] as $perm) {
    Permission::findOrCreate($perm, 'web');
}
$admin->givePermissionTo([
    'projects.view', 'projects.create', 'projects.update', 'projects.delete',
    'analytics.view',
    'training.view', 'training.create', 'training.update', 'training.delete', 'training.assign',
]);

$projectMatter = LegalMatter::query()->firstOrCreate(
    [
        'organization_id' => $organization->id,
        'matter_number' => 'P5-PROJECT-001',
    ],
    [
        'client_id' => $client->id,
        'title' => 'Phase 5 Project Matter',
        'status' => 'open',
        'court_jurisdiction' => 'U.S. District Court',
        'case_type' => 'civil',
        'lead_lawyer_id' => $admin->id,
        'opened_at' => now()->subDays(45)->toDateString(),
        'created_by' => $admin->id,
    ]
);

$milestoneRes = call($http, $token, 'POST', '/legal-project-milestones', [
    'legal_matter_id' => $projectMatter->id,
    'title' => 'Discovery plan approved',
    'milestone_type' => 'research_completed',
    'status' => 'in_progress',
    'due_at' => now()->addDays(14)->toDateString(),
    'assigned_to' => $admin->id,
]);
record($results, 'POST /legal-project-milestones', $milestoneRes, [201]);
$ids['project_milestone'] = resId($milestoneRes);

$listMilestonesRes = call($http, $token, 'GET', '/legal-project-milestones?legal_matter_id=' . $projectMatter->id);
record($results, 'GET /legal-project-milestones (scoped)', $listMilestonesRes, [200]);
$milestoneListBody = $listMilestonesRes['body'] ?? [];
$results[] = [
    '  milestones meta milestone_types',
    isset($milestoneListBody['meta']['milestone_types']) && is_array($milestoneListBody['meta']['milestone_types']) ? 'yes' : 'no',
    isset($milestoneListBody['meta']['milestone_types']) && is_array($milestoneListBody['meta']['milestone_types']) ? 'OK' : 'FAIL',
];

if ($ids['project_milestone']) {
    $updateMilestoneRes = call($http, $token, 'PUT', "/legal-project-milestones/{$ids['project_milestone']}", [
        'status' => 'completed',
    ]);
    record($results, 'PUT /legal-project-milestones/{id}', $updateMilestoneRes, [200]);
}

$budgetRes = call($http, $token, 'POST', '/legal-project-budgets', [
    'legal_matter_id' => $projectMatter->id,
    'category' => 'fees',
    'description' => 'Discovery phase fees',
    'budgeted_amount' => 15000,
    'actual_amount' => 4200,
]);
record($results, 'POST /legal-project-budgets', $budgetRes, [201]);
$ids['project_budget'] = resId($budgetRes);

$listBudgetsRes = call($http, $token, 'GET', '/legal-project-budgets?legal_matter_id=' . $projectMatter->id);
record($results, 'GET /legal-project-budgets (scoped)', $listBudgetsRes, [200]);
$budgetListBody = $listBudgetsRes['body'] ?? [];
$results[] = [
    '  budget meta totals',
    isset($budgetListBody['meta']['totals']['budgeted']) ? 'yes' : 'no',
    isset($budgetListBody['meta']['totals']['budgeted']) ? 'OK' : 'FAIL',
];

$workloadRes = call($http, $token, 'GET', '/legal-project-workload');
record($results, 'GET /legal-project-workload', $workloadRes, [200]);
$workloadBody = $workloadRes['body'] ?? [];
$results[] = [
    '  workload by_lawyer array',
    isset($workloadBody['by_lawyer']) && is_array($workloadBody['by_lawyer']) ? 'yes' : 'no',
    isset($workloadBody['by_lawyer']) && is_array($workloadBody['by_lawyer']) ? 'OK' : 'FAIL',
];

$taskWorkloadRes = call($http, $token, 'GET', '/task-workload');
record($results, 'GET /task-workload', $taskWorkloadRes, [200]);
$taskWorkloadBody = $taskWorkloadRes['body'] ?? [];
$results[] = [
    '  task board columns',
    isset($taskWorkloadBody['board']) && is_array($taskWorkloadBody['board']['not_started'] ?? null) ? 'yes' : 'no',
    isset($taskWorkloadBody['board']) && is_array($taskWorkloadBody['board']['not_started'] ?? null) ? 'OK' : 'FAIL',
];
$results[] = [
    '  task by_assignee array',
    isset($taskWorkloadBody['by_assignee']) && is_array($taskWorkloadBody['by_assignee']) ? 'yes' : 'no',
    isset($taskWorkloadBody['by_assignee']) && is_array($taskWorkloadBody['by_assignee']) ? 'OK' : 'FAIL',
];
$projectsViewSource = (string) @file_get_contents(__DIR__ . '/../frontend/src/views/LegalProjectsView.vue');
$results[] = [
    'LegalProjectsView task board UI',
    str_contains($projectsViewSource, 'taskBoard') && str_contains($projectsViewSource, 'Team task board') ? 'yes' : 'no',
    str_contains($projectsViewSource, 'taskBoard') && str_contains($projectsViewSource, 'Team task board') ? 'OK' : 'FAIL',
];

$analyticsDashboardRes = call($http, $token, 'GET', '/legal-analytics/dashboard');
record($results, 'GET /legal-analytics/dashboard', $analyticsDashboardRes, [200]);
$analyticsBody = $analyticsDashboardRes['body'] ?? [];
$results[] = [
    '  analytics disclaimer present',
    ! empty($analyticsBody['disclaimer']) ? 'yes' : 'no',
    ! empty($analyticsBody['disclaimer']) ? 'OK' : 'FAIL',
];
$results[] = [
    '  analytics case_duration',
    isset($analyticsBody['case_duration']['average_days']) ? 'yes' : 'no',
    isset($analyticsBody['case_duration']['average_days']) ? 'OK' : 'FAIL',
];
$results[] = [
    '  analytics outcomes by_status',
    isset($analyticsBody['outcomes']['by_status']) && is_array($analyticsBody['outcomes']['by_status']) ? 'yes' : 'no',
    isset($analyticsBody['outcomes']['by_status']) && is_array($analyticsBody['outcomes']['by_status']) ? 'OK' : 'FAIL',
];

$analyticsHintsRes = call($http, $token, 'GET', '/legal-analytics/hints');
record($results, 'GET /legal-analytics/hints', $analyticsHintsRes, [200]);
$hintsBody = $analyticsHintsRes['body'] ?? [];
$results[] = [
    '  hints disclaimer present',
    ! empty($hintsBody['disclaimer']) ? 'yes' : 'no',
    ! empty($hintsBody['disclaimer']) ? 'OK' : 'FAIL',
];
$results[] = [
    '  hints requires_review',
    ($hintsBody['requires_review'] ?? false) === true ? 'yes' : 'no',
    ($hintsBody['requires_review'] ?? false) === true ? 'OK' : 'FAIL',
];
$results[] = [
    '  hints non-empty',
    is_array($hintsBody['hints'] ?? null) && count($hintsBody['hints']) >= 1 ? 'yes' : 'no',
    is_array($hintsBody['hints'] ?? null) && count($hintsBody['hints']) >= 1 ? 'OK' : 'FAIL',
];

(new TrainingCourseSeeder())->run();
$starterCourseCount = TrainingCourse::query()->whereNull('organization_id')->count();
$results[] = ['training starter courses seeded', (string) $starterCourseCount, $starterCourseCount >= 3 ? 'OK' : 'FAIL'];

$course = TrainingCourse::query()->whereNull('organization_id')->first();
$results[] = ['starter training course exists', $course ? 'yes' : 'no', $course ? 'OK' : 'FAIL'];

$listCoursesRes = call($http, $token, 'GET', '/training-courses');
record($results, 'GET /training-courses', $listCoursesRes, [200]);

if ($course) {
    $enrollRes = call($http, $token, 'POST', '/training-enrollments', [
        'training_course_id' => $course->id,
        'user_id' => $admin->id,
    ]);
    record($results, 'POST /training-enrollments (assign)', $enrollRes, [200, 201]);
    $ids['training_enrollment'] = resId($enrollRes);

    if ($ids['training_enrollment']) {
        $startRes = call($http, $token, 'PUT', "/training-enrollments/{$ids['training_enrollment']}", [
            'status' => 'in_progress',
        ]);
        record($results, 'PUT /training-enrollments/{id} (start)', $startRes, [200]);

        $questions = $course->quiz_questions ?? [];
        $answers = array_map(fn (array $q) => (int) ($q['correct_index'] ?? 0), $questions);
        $quizRes = call($http, $token, 'POST', "/training-enrollments/{$ids['training_enrollment']}/submit-quiz", [
            'answers' => $answers,
        ]);
        record($results, 'POST /training-enrollments/{id}/submit-quiz', $quizRes, [200]);
        $quizBody = $quizRes['body'] ?? [];
        $results[] = [
            '  quiz passed',
            ($quizBody['passed'] ?? false) === true ? 'yes' : 'no',
            ($quizBody['passed'] ?? false) === true ? 'OK' : 'FAIL',
        ];
        $results[] = [
            '  certificate issued',
            ! empty($quizBody['certificate']['certificate_number']) ? 'yes' : 'no',
            ! empty($quizBody['certificate']['certificate_number']) ? 'OK' : 'FAIL',
        ];
    }

    $complianceRes = call($http, $token, 'GET', '/training-compliance/report');
    record($results, 'GET /training-compliance/report', $complianceRes, [200]);
    $complianceBody = $complianceRes['body'] ?? [];
    $results[] = [
        '  compliance rows array',
        isset($complianceBody['rows']) && is_array($complianceBody['rows']) ? 'yes' : 'no',
        isset($complianceBody['rows']) && is_array($complianceBody['rows']) ? 'OK' : 'FAIL',
    ];
}

$consultantProjects = call($http, $consultantToken, 'GET', '/legal-project-milestones');
record($results, 'GET /legal-project-milestones (consultant blocked)', $consultantProjects, [403]);
$consultantAnalytics = call($http, $consultantToken, 'GET', '/legal-analytics/dashboard');
record($results, 'GET /legal-analytics/dashboard (consultant blocked)', $consultantAnalytics, [403]);
$consultantTraining = call($http, $consultantToken, 'GET', '/training-courses');
record($results, 'GET /training-courses (consultant blocked)', $consultantTraining, [403]);

$results[] = [
    'LegalProjectMilestone::MILESTONE_TYPES count',
    (string) count(LegalProjectMilestone::MILESTONE_TYPES),
    count(LegalProjectMilestone::MILESTONE_TYPES) >= 8 ? 'OK' : 'FAIL',
];
$results[] = [
    'LegalProjectBudget::CATEGORIES count',
    (string) count(LegalProjectBudget::CATEGORIES),
    count(LegalProjectBudget::CATEGORIES) >= 4 ? 'OK' : 'FAIL',
];
$results[] = [
    'LegalAnalyticsService exists',
    class_exists(\App\Services\LegalAnalyticsService::class) ? 'yes' : 'no',
    class_exists(\App\Services\LegalAnalyticsService::class) ? 'OK' : 'FAIL',
];
$results[] = [
    'TrainingEnrollment model exists',
    class_exists(TrainingEnrollment::class) ? 'yes' : 'no',
    class_exists(TrainingEnrollment::class) ? 'OK' : 'FAIL',
];
$results[] = [
    'TrainingCertificate model exists',
    class_exists(TrainingCertificate::class) ? 'yes' : 'no',
    class_exists(TrainingCertificate::class) ? 'OK' : 'FAIL',
];

$projectsView = __DIR__ . '/../frontend/src/views/LegalProjectsView.vue';
$analyticsView = __DIR__ . '/../frontend/src/views/LegalAnalyticsView.vue';
$trainingView = __DIR__ . '/../frontend/src/views/TrainingView.vue';
$projectPanel = __DIR__ . '/../frontend/src/components/cases/CaseProjectPanel.vue';
$slice8UiChecks = [
    'LegalProjectsView.vue exists' => file_exists($projectsView),
    'LegalAnalyticsView.vue exists' => file_exists($analyticsView),
    'TrainingView.vue exists' => file_exists($trainingView),
    'CaseProjectPanel.vue exists' => file_exists($projectPanel),
    'LegalProjectsView workload wiring' => str_contains((string) @file_get_contents($projectsView), 'legalProjectsApi.workload'),
    'LegalAnalyticsView disclaimer' => str_contains((string) @file_get_contents($analyticsView), 'AiDisclaimerBanner'),
    'LegalAnalyticsView hints section' => str_contains((string) @file_get_contents($analyticsView), 'AI planning hints'),
    'TrainingView quiz wiring' => str_contains((string) @file_get_contents($trainingView), 'trainingApi.submitQuiz'),
    'TrainingView compliance report' => str_contains((string) @file_get_contents($trainingView), 'complianceReport'),
    'CaseProjectPanel milestones wiring' => str_contains((string) @file_get_contents($projectPanel), 'legalProjectsApi.listMilestones'),
    'CaseProjectPanel budget wiring' => str_contains((string) @file_get_contents($projectPanel), 'legalProjectsApi.listBudgets'),
    'CaseDetailView project tab' => str_contains((string) @file_get_contents($caseDetailView), "key: 'project'"),
    'Router legal-projects route' => str_contains($routerSource, "name: 'legal-projects'"),
    'Router legal-analytics route' => str_contains($routerSource, "name: 'legal-analytics'"),
    'Router training route' => str_contains($routerSource, "name: 'training'"),
    'Router project workspace tab' => str_contains($routerSource, '|project'),
    'Sidebar legal-projects nav' => str_contains($sidebarSource, "name: 'legal-projects'"),
    'Sidebar legal-analytics nav' => str_contains($sidebarSource, "name: 'legal-analytics'"),
    'Sidebar training nav' => str_contains($sidebarSource, "name: 'training'"),
];
foreach ($slice8UiChecks as $label => $ok) {
    $results[] = [$label, $ok ? 'yes' : 'no', $ok ? 'OK' : 'FAIL'];
}

echo "\n==================== PHASE 5 RESULTS ====================\n";
$pass = 0;
$fail = 0;
foreach ($results as [$label, $status, $verdict]) {
    printf("%-45s %-8s %s\n", $label, (string) $status, $verdict);
    $verdict === 'OK' ? $pass++ : $fail++;
}
echo "----------------------------------------------------------------------\n";
echo "PASS: {$pass}  FAIL: {$fail}\n";

exit($fail > 0 ? 1 : 0);
