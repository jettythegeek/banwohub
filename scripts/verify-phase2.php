<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use App\Models\Client;
use App\Models\LegalMatter;
use App\Models\User;
use Spatie\Permission\Models\Permission;
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;

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

$token = $admin->createToken('phase2-verify')->plainTextToken;

$results = [];
$ids = [];

function call(HttpKernel $http, string $token, string $method, string $uri, array $payload = [], array $files = []): array
{
    $server = [
        'HTTP_Authorization' => 'Bearer ' . $token,
        'HTTP_Accept' => 'application/json',
    ];

    $request = Request::create('/api/v1' . $uri, $method, $payload, [], $files, $server);
    if ($files === [] && $payload !== [] && in_array($method, ['POST', 'PUT', 'PATCH'], true)) {
        // Use JSON body for non-file requests so nested arrays are preserved.
        $request = Request::create('/api/v1' . $uri, $method, [], [], [], array_merge($server, [
            'CONTENT_TYPE' => 'application/json',
        ]), json_encode($payload));
    }

    $response = $http->handle($request);
    $status = $response->getStatusCode();

    if (method_exists($response, 'getContent')) {
        ob_start();
        $response->sendContent();
        $content = ob_get_clean();
    } else {
        $content = '';
    }
    $body = is_string($content) && $content !== '' ? json_decode($content, true) : null;

    return ['status' => $status, 'body' => $body];
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

function record(array &$results, string $label, array $res, array $okStatuses = [200, 201]): void
{
    $ok = in_array($res['status'], $okStatuses, true);
    $results[] = [$label, $res['status'], $ok ? 'OK' : 'FAIL'];
    if (! $ok) {
        $msg = is_array($res['body']) ? json_encode($res['body']) : (string) $res['body'];
        fwrite(STDERR, "  [FAIL] {$label} => {$res['status']} :: " . substr($msg, 0, 300) . "\n");
    }
}

// --- Prerequisites: a client + case in the admin's org ---
$orgId = $admin->organization_id;
$client = Client::query()->create([
    'organization_id' => $orgId,
    'type' => 'individual',
    'name' => 'Phase2 Test Client ' . uniqid(),
    'email' => 'phase2client@example.com',
    'status' => 'active',
    'created_by' => $admin->id,
]);
$matter = LegalMatter::query()->create([
    'organization_id' => $orgId,
    'client_id' => $client->id,
    'title' => 'Phase2 Test Matter',
    'matter_number' => 'P2-' . uniqid(),
    'status' => 'new',
    'priority' => 'normal',
    'created_by' => $admin->id,
]);

echo "=== DASHBOARD ===\n";
$dashboardRes = call($http, $token, 'GET', '/dashboard');
record($results, 'GET /dashboard', $dashboardRes, [200]);
$dashboardBody = $dashboardRes['body'] ?? [];
$results[] = [
    '  dashboard charts.cases_by_status',
    isset($dashboardBody['charts']['cases_by_status']) && is_array($dashboardBody['charts']['cases_by_status']) ? 'yes' : 'no',
    isset($dashboardBody['charts']['cases_by_status']) && is_array($dashboardBody['charts']['cases_by_status']) ? 'OK' : 'FAIL',
];
$results[] = [
    '  dashboard charts.invoices_by_status',
    isset($dashboardBody['charts']['invoices_by_status']) && is_array($dashboardBody['charts']['invoices_by_status']) ? 'yes' : 'no',
    isset($dashboardBody['charts']['invoices_by_status']) && is_array($dashboardBody['charts']['invoices_by_status']) ? 'OK' : 'FAIL',
];
$results[] = [
    '  dashboard charts.revenue_trend',
    isset($dashboardBody['charts']['revenue_trend']) && is_array($dashboardBody['charts']['revenue_trend']) ? 'yes' : 'no',
    isset($dashboardBody['charts']['revenue_trend']) && is_array($dashboardBody['charts']['revenue_trend']) ? 'OK' : 'FAIL',
];
$results[] = [
    '  dashboard charts.task_workload',
    isset($dashboardBody['charts']['task_workload']) && is_array($dashboardBody['charts']['task_workload']) ? 'yes' : 'no',
    isset($dashboardBody['charts']['task_workload']) && is_array($dashboardBody['charts']['task_workload']) ? 'OK' : 'FAIL',
];

echo "=== CASE NOTES ===\n";
$res = call($http, $token, 'POST', '/case-notes', [
    'legal_matter_id' => $matter->id,
    'note_type' => 'private_note',
    'visibility' => 'private',
    'title' => 'Test note',
    'body' => 'Body text',
]);
record($results, 'POST /case-notes', $res, [201]);
$ids['note'] = resId($res);
record($results, 'GET /case-notes', call($http, $token, 'GET', '/case-notes'), [200]);
if ($ids['note']) {
    record($results, 'GET /case-notes/{id}', call($http, $token, 'GET', "/case-notes/{$ids['note']}"), [200]);
    record($results, 'PUT /case-notes/{id}', call($http, $token, 'PUT', "/case-notes/{$ids['note']}", ['body' => 'Updated body']), [200]);
}

echo "=== TASKS ===\n";
$res = call($http, $token, 'POST', '/tasks', [
    'legal_matter_id' => $matter->id,
    'assignee_id' => $admin->id,
    'title' => 'Test task',
    'status' => 'not_started',
    'priority' => 'high',
    'due_at' => now()->subDay()->toDateTimeString(),
]);
record($results, 'POST /tasks', $res, [201]);
$ids['task'] = resId($res);
record($results, 'GET /tasks', call($http, $token, 'GET', '/tasks'), [200]);
if ($ids['task']) {
    record($results, 'GET /tasks/{id}', call($http, $token, 'GET', "/tasks/{$ids['task']}"), [200]);
    record($results, 'PUT /tasks/{id}', call($http, $token, 'PUT', "/tasks/{$ids['task']}", ['status' => 'completed']), [200]);
    record($results, 'PATCH /tasks/{id} (kanban status)', call($http, $token, 'PATCH', "/tasks/{$ids['task']}", ['status' => 'in_progress']), [200]);
}

echo "=== TASK ATTACHMENTS + COMMENTS (Slice 9) ===\n";
if ($ids['task'] ?? null) {
    $tmpTask = tempnam(sys_get_temp_dir(), 'p2task') . '.txt';
    file_put_contents($tmpTask, 'task attachment content');
    $taskUpload = new UploadedFile($tmpTask, 'task-attachment.txt', 'text/plain', null, true);
    $attachRes = call($http, $token, 'POST', "/tasks/{$ids['task']}/attachments", [], ['file' => $taskUpload]);
    record($results, 'POST /tasks/{id}/attachments', $attachRes, [201]);
    $ids['task_attachment'] = resId($attachRes);
    record($results, 'GET /tasks/{id}/attachments', call($http, $token, 'GET', "/tasks/{$ids['task']}/attachments"), [200]);
    if ($ids['task_attachment'] ?? null) {
        record($results, 'GET /tasks/{id}/attachments/{id}/download', call($http, $token, 'GET', "/tasks/{$ids['task']}/attachments/{$ids['task_attachment']}/download"), [200]);
        record($results, 'DELETE /tasks/{id}/attachments/{id}', call($http, $token, 'DELETE', "/tasks/{$ids['task']}/attachments/{$ids['task_attachment']}"), [200]);
    }
    @unlink($tmpTask);

    record($results, 'POST /tasks/{id}/comments', call($http, $token, 'POST', "/tasks/{$ids['task']}/comments", ['body' => 'Task comment from verify script']), [201]);
    record($results, 'GET /tasks/{id}/comments', call($http, $token, 'GET', "/tasks/{$ids['task']}/comments"), [200]);
    record($results, 'PATCH /tasks/{id} (checklist)', call($http, $token, 'PATCH', "/tasks/{$ids['task']}", [
        'checklist' => [
            ['id' => 'item-1', 'label' => 'Review filing', 'done' => false],
        ],
    ]), [200]);
}

echo "=== FRONTEND KANBAN (Slice 1) ===\n";
$tasksPanel = __DIR__ . '/../frontend/src/components/cases/CaseTasksPanel.vue';
$panelSource = file_exists($tasksPanel) ? (string) file_get_contents($tasksPanel) : '';
$packageJsonPath = __DIR__ . '/../frontend/package.json';
$packageJson = file_exists($packageJsonPath)
    ? json_decode((string) file_get_contents($packageJsonPath), true)
    : [];
$hasDraggableDep = is_array($packageJson)
    && isset($packageJson['dependencies']['vue-draggable-plus']);
record($results, 'vue-draggable-plus in package.json', ['status' => $hasDraggableDep ? 200 : 404, 'body' => null], [200]);
record($results, 'CaseTasksPanel imports VueDraggable', ['status' => str_contains($panelSource, 'VueDraggable') ? 200 : 404, 'body' => null], [200]);
record($results, 'CaseTasksPanel PATCH on kanban drop', ['status' => str_contains($panelSource, 'caseTasksApi.update') && str_contains($panelSource, 'onTaskDropped') ? 200 : 404, 'body' => null], [200]);
record($results, 'CaseTasksPanel semantic status dots', ['status' => str_contains($panelSource, 'statusDotVar') ? 200 : 404, 'body' => null], [200]);
$tokensSource = (string) @file_get_contents(__DIR__ . '/../frontend/src/styles/tokens.css');
record($results, 'tokens.css task status dot vars', ['status' => str_contains($tokensSource, '--status-task-not-started') ? 200 : 404, 'body' => null], [200]);

echo "=== FRONTEND TASK DETAIL (Slice 9) ===\n";
record($results, 'CaseTasksPanel attachments section', ['status' => str_contains($panelSource, 'uploadAttachment') && str_contains($panelSource, 'Attachments') ? 200 : 404, 'body' => null], [200]);
record($results, 'CaseTasksPanel comments section', ['status' => str_contains($panelSource, 'addComment') && str_contains($panelSource, 'Comments') ? 200 : 404, 'body' => null], [200]);
record($results, 'CaseTasksPanel checklist section', ['status' => str_contains($panelSource, 'saveChecklist') && str_contains($panelSource, 'Checklist') ? 200 : 404, 'body' => null], [200]);
$apiSource = (string) @file_get_contents(__DIR__ . '/../frontend/src/lib/api.ts');
record($results, 'caseTasksApi attachment + comment methods', ['status' => str_contains($apiSource, 'uploadAttachment') && str_contains($apiSource, 'addComment') ? 200 : 404, 'body' => null], [200]);
$migrationPath = __DIR__ . '/../database/migrations/2026_06_05_200000_create_task_attachments_and_comments_tables.php';
record($results, 'task_attachments + task_comments migration', ['status' => file_exists($migrationPath) ? 200 : 404, 'body' => null], [200]);

echo "=== CALENDAR EVENTS ===\n";
$res = call($http, $token, 'POST', '/calendar-events', [
    'legal_matter_id' => $matter->id,
    'user_id' => $admin->id,
    'event_type' => 'court_hearing',
    'title' => 'Hearing',
    'starts_at' => now()->addDays(3)->toDateTimeString(),
    'ends_at' => now()->addDays(3)->addHour()->toDateTimeString(),
    'reminder_at' => now()->addDays(2)->toDateTimeString(),
]);
record($results, 'POST /calendar-events', $res, [201]);
$ids['event'] = resId($res);
record($results, 'GET /calendar-events', call($http, $token, 'GET', '/calendar-events'), [200]);
if ($ids['event']) {
    record($results, 'GET /calendar-events/{id}', call($http, $token, 'GET', "/calendar-events/{$ids['event']}"), [200]);
    record($results, 'PUT /calendar-events/{id}', call($http, $token, 'PUT', "/calendar-events/{$ids['event']}", ['title' => 'Hearing updated']), [200]);
}

echo "=== DOCUMENTS ===\n";
$tmp = tempnam(sys_get_temp_dir(), 'p2doc') . '.txt';
file_put_contents($tmp, 'phase 2 document content');
$upload = new UploadedFile($tmp, 'test-document.txt', 'text/plain', null, true);
$res = call($http, $token, 'POST', '/documents', [
    'legal_matter_id' => (string) $matter->id,
    'document_type' => 'case_document',
    'name' => 'Test Document',
    'category' => 'pleadings',
], ['file' => $upload]);
record($results, 'POST /documents (upload)', $res, [201]);
$ids['doc'] = resId($res);
record($results, 'GET /documents', call($http, $token, 'GET', '/documents'), [200]);
if ($ids['doc']) {
    record($results, 'GET /documents/{id}', call($http, $token, 'GET', "/documents/{$ids['doc']}"), [200]);
    record($results, 'GET /documents/{id}/download', call($http, $token, 'GET', "/documents/{$ids['doc']}/download"), [200]);
    record($results, 'PUT /documents/{id}', call($http, $token, 'PUT', "/documents/{$ids['doc']}", ['description' => 'updated']), [200]);
}

echo "=== DOCUMENT FOLDERS + CHECKOUT ===\n";

$results[] = [
    'document_folders table',
    Schema::hasTable('document_folders') ? 'yes' : 'no',
    Schema::hasTable('document_folders') ? 'OK' : 'FAIL',
];
$results[] = [
    'legal_documents folder + checkout columns',
    Schema::hasColumns('legal_documents', ['document_folder_id', 'checked_out_by', 'checked_out_at']) ? 'yes' : 'no',
    Schema::hasColumns('legal_documents', ['document_folder_id', 'checked_out_by', 'checked_out_at']) ? 'OK' : 'FAIL',
];

if ($matter && ($ids['doc'] ?? null)) {
    $folderRes = call($http, $token, 'POST', '/document-folders', [
        'legal_matter_id' => $matter->id,
        'name' => 'Pleadings',
    ]);
    record($results, 'POST /document-folders', $folderRes, [201]);
    $ids['doc_folder'] = resId($folderRes);

    if ($ids['doc_folder']) {
        record($results, 'GET /document-folders', call($http, $token, 'GET', '/document-folders', ['legal_matter_id' => $matter->id]), [200]);
        record($results, 'PUT /documents/{id} assign folder', call($http, $token, 'PUT', "/documents/{$ids['doc']}", [
            'document_folder_id' => $ids['doc_folder'],
        ]), [200]);

        $checkoutRes = call($http, $token, 'POST', "/documents/{$ids['doc']}/checkout");
        record($results, 'POST /documents/{id}/checkout', $checkoutRes, [200]);
        $checkoutBody = $checkoutRes['body'] ?? [];
        $results[] = [
            '  document checked out flag',
            ($checkoutBody['is_checked_out'] ?? false) ? 'yes' : 'no',
            ($checkoutBody['is_checked_out'] ?? false) ? 'OK' : 'FAIL',
        ];

        $checkinRes = call($http, $token, 'POST', "/documents/{$ids['doc']}/checkin");
        record($results, 'POST /documents/{id}/checkin', $checkinRes, [200]);
        $checkinBody = $checkinRes['body'] ?? [];
        $results[] = [
            '  document checked in flag',
            ! ($checkinBody['is_checked_out'] ?? true) ? 'yes' : 'no',
            ! ($checkinBody['is_checked_out'] ?? true) ? 'OK' : 'FAIL',
        ];

        record($results, 'DELETE /document-folders/{id}', call($http, $token, 'DELETE', "/document-folders/{$ids['doc_folder']}"), [204]);
    }
}

echo "=== INTAKE FORMS ===\n";
$res = call($http, $token, 'POST', '/intake-forms', [
    'name' => 'New Client Intake',
    'case_type' => 'Civil',
    'status' => 'published',
    'fields' => [
        ['name' => 'full_name', 'label' => 'Full Name', 'type' => 'text', 'required' => true],
        ['name' => 'email', 'label' => 'Email', 'type' => 'email', 'required' => true],
    ],
]);
record($results, 'POST /intake-forms', $res, [201]);
$ids['form'] = resId($res);
record($results, 'GET /intake-forms', call($http, $token, 'GET', '/intake-forms'), [200]);
if ($ids['form']) {
    record($results, 'GET /intake-forms/{id}', call($http, $token, 'GET', "/intake-forms/{$ids['form']}"), [200]);
    record($results, 'PUT /intake-forms/{id}', call($http, $token, 'PUT', "/intake-forms/{$ids['form']}", ['status' => 'archived']), [200]);
}

echo "=== INTAKE SUBMISSIONS ===\n";
if ($ids['form']) {
    $res = call($http, $token, 'POST', '/intake-submissions', [
        'intake_form_id' => $ids['form'],
        'submitter_name' => 'Jane Doe',
        'submitter_email' => 'jane@example.com',
        'data' => ['full_name' => 'Jane Doe', 'email' => 'jane@example.com'],
    ]);
    record($results, 'POST /intake-submissions', $res, [201]);
    $ids['submission'] = resId($res);
    record($results, 'GET /intake-submissions', call($http, $token, 'GET', '/intake-submissions'), [200]);
    if ($ids['submission']) {
        record($results, 'GET /intake-submissions/{id}', call($http, $token, 'GET', "/intake-submissions/{$ids['submission']}"), [200]);
        record($results, 'PUT /intake-submissions/{id}', call($http, $token, 'PUT', "/intake-submissions/{$ids['submission']}", ['status' => 'in_review']), [200]);
        record($results, 'PATCH /intake-submissions/{id} (kanban status)', call($http, $token, 'PATCH', "/intake-submissions/{$ids['submission']}", ['status' => 'rejected']), [200]);
        record($results, 'PATCH /intake-submissions/{id} (kanban restore)', call($http, $token, 'PATCH', "/intake-submissions/{$ids['submission']}", ['status' => 'in_review']), [200]);
        $res = call($http, $token, 'POST', "/intake-submissions/{$ids['submission']}/convert", [
            'client' => ['name' => 'Jane Doe', 'email' => 'jane@example.com', 'type' => 'individual'],
            'case' => ['title' => 'Jane Doe Matter', 'practice_area' => 'Litigation'],
        ]);
        record($results, 'POST /intake-submissions/{id}/convert', $res, [200]);
    }
}

echo "=== CONFLICT CHECKS ===\n";
$res = call($http, $token, 'POST', '/conflict-checks', [
    'search_terms' => ['Jane Doe', 'Acme Corp'],
    'legal_matter_id' => $matter->id,
]);
record($results, 'POST /conflict-checks', $res, [201]);
$ids['conflict'] = resId($res);
record($results, 'GET /conflict-checks', call($http, $token, 'GET', '/conflict-checks'), [200]);
if ($ids['conflict']) {
    record($results, 'GET /conflict-checks/{id}', call($http, $token, 'GET', "/conflict-checks/{$ids['conflict']}"), [200]);
    record($results, 'PUT /conflict-checks/{id} (clear)', call($http, $token, 'PUT', "/conflict-checks/{$ids['conflict']}", ['status' => 'cleared', 'decision' => 'No conflict', 'notes' => 'Reviewed']), [200]);
}

echo "=== NOTIFICATIONS ===\n";
record($results, 'GET /notifications', call($http, $token, 'GET', '/notifications'), [200]);
$res = call($http, $token, 'GET', '/notifications');
$notifId = $res['body']['data'][0]['id'] ?? $res['body'][0]['id'] ?? null;
if ($notifId) {
    record($results, 'GET /notifications/{id}', call($http, $token, 'GET', "/notifications/{$notifId}"), [200]);
    record($results, 'POST /notifications/{id}/read', call($http, $token, 'POST', "/notifications/{$notifId}/read"), [200]);
}
record($results, 'POST /notifications/mark-all-read', call($http, $token, 'POST', '/notifications/mark-all-read'), [200]);

echo "=== PHASE 2 EXTENSIONS ===\n";
if ($ids['form'] ?? null) {
    $tplTmp = tempnam(sys_get_temp_dir(), 'tpl').'.html';
    file_put_contents($tplTmp, '<p>Dear {{client.name}}, re {{case.title}}</p>');
    $res = call($http, $token, 'POST', '/documents', [
        'document_type' => 'organization_template',
        'name' => 'Engagement Template',
        'content_html' => '<p>Dear {{client.name}}, re {{case.title}}</p>',
    ], [
        'file' => new UploadedFile($tplTmp, 'template.html', 'text/html', null, true),
    ]);
    record($results, 'POST /documents (template)', $res, [201]);
    $ids['template'] = resId($res);
}
if (($ids['template'] ?? null) && $matter) {
    record($results, 'POST /documents/generate-draft', call($http, $token, 'POST', '/documents/generate-draft', [
        'template_id' => $ids['template'],
        'legal_matter_id' => $matter->id,
        'name' => 'Generated Draft',
    ]), [201]);
}
record($results, 'GET /cases/{id}/activity', call($http, $token, 'GET', "/cases/{$matter->id}/activity"), [200]);
if ($ids['submission'] ?? null) {
    $draftRes = call($http, $token, 'POST', '/intake-submissions', [
        'intake_form_id' => $ids['form'],
        'status' => 'draft',
        'data' => ['full_name' => 'Draft User'],
    ]);
    record($results, 'POST /intake-submissions (draft)', $draftRes, [201]);
    $draftId = resId($draftRes);
    if ($draftId) {
        record($results, 'PATCH /intake-submissions/{id} (draft→submitted)', call($http, $token, 'PATCH', "/intake-submissions/{$draftId}", ['status' => 'submitted']), [200]);
        record($results, 'POST /intake-submissions/{id}/approve', call($http, $token, 'POST', "/intake-submissions/{$draftId}/approve"), [200]);
        record($results, 'POST /intake-submissions/{id}/request-info', call($http, $token, 'POST', "/intake-submissions/{$draftId}/request-info", ['review_notes' => 'Need ID']), [200]);
    }
}

echo "=== FRONTEND KANBAN (Slice 2 — intake) ===\n";
$intakeView = __DIR__ . '/../frontend/src/views/IntakeView.vue';
$intakeSource = file_exists($intakeView) ? (string) file_get_contents($intakeView) : '';
record($results, 'IntakeView imports VueDraggable', ['status' => str_contains($intakeSource, 'VueDraggable') ? 200 : 404, 'body' => null], [200]);
record($results, 'IntakeView PATCH on kanban drop', ['status' => str_contains($intakeSource, 'intakeSubmissionsApi.update') && str_contains($intakeSource, 'onSubmissionDropped') ? 200 : 404, 'body' => null], [200]);
record($results, 'IntakeView intake pipeline dot tokens', ['status' => str_contains($intakeSource, 'intakePipelineDotVar') ? 200 : 404, 'body' => null], [200]);
record($results, 'tokens.css intake pipeline dot vars', ['status' => str_contains($tokensSource, '--status-intake-pending') ? 200 : 404, 'body' => null], [200]);

echo "=== FRONTEND DASHBOARD CHARTS (Slice 3) ===\n";
$dashboardView = __DIR__ . '/../frontend/src/views/DashboardView.vue';
$dashboardSource = file_exists($dashboardView) ? (string) file_get_contents($dashboardView) : '';
record($results, 'DashboardView cases by status chart', ['status' => str_contains($dashboardSource, 'cases_by_status') && str_contains($dashboardSource, 'statusDotVar') ? 200 : 404, 'body' => null], [200]);
record($results, 'DashboardView invoice donut chart', ['status' => str_contains($dashboardSource, 'invoices_by_status') && str_contains($dashboardSource, 'conic-gradient') ? 200 : 404, 'body' => null], [200]);
record($results, 'DashboardView revenue trend chart', ['status' => str_contains($dashboardSource, 'revenue_trend') && str_contains($dashboardSource, 'polyline') ? 200 : 404, 'body' => null], [200]);
record($results, 'DashboardView task workload chart', ['status' => str_contains($dashboardSource, 'task_workload') ? 200 : 404, 'body' => null], [200]);
record($results, 'tokens.css invoice status dot vars', ['status' => str_contains($tokensSource, '--status-invoice-paid') ? 200 : 404, 'body' => null], [200]);
if ($ids['conflict'] ?? null) {
    record($results, 'GET /conflict-checks/{id}/export', call($http, $token, 'GET', "/conflict-checks/{$ids['conflict']}/export", ['format' => 'csv']), [200]);
}

echo "=== CLIENT CONTACTS ===\n";
foreach ([
    'client-contacts.view',
    'client-contacts.create',
    'client-contacts.update',
    'client-contacts.delete',
] as $perm) {
    Permission::findOrCreate($perm, 'web');
    $admin->givePermissionTo($perm);
}
app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
$results[] = [
    'client_contacts table',
    Schema::hasTable('client_contacts') ? 'yes' : 'no',
    Schema::hasTable('client_contacts') ? 'OK' : 'FAIL',
];

$res = call($http, $token, 'POST', '/client-contacts', [
    'client_id' => $client->id,
    'type' => 'billing',
    'name' => 'Billing Contact',
    'email' => 'billing@example.com',
    'phone' => '+1-555-0100',
    'title' => 'Accounts payable',
]);
record($results, 'POST /client-contacts', $res, [201]);
$ids['client_contact'] = resId($res);
record($results, 'GET /client-contacts', call($http, $token, 'GET', '/client-contacts', ['client_id' => $client->id]), [200]);
if ($ids['client_contact'] ?? null) {
    record($results, 'GET /client-contacts/{id}', call($http, $token, 'GET', "/client-contacts/{$ids['client_contact']}"), [200]);
    record($results, 'PUT /client-contacts/{id}', call($http, $token, 'PUT', "/client-contacts/{$ids['client_contact']}", ['phone' => '+1-555-0101']), [200]);
}

echo "=== FRONTEND CLIENT CONTACTS ===\n";
$contactsPanel = __DIR__ . '/../frontend/src/components/clients/ClientContactsPanel.vue';
$contactsSource = file_exists($contactsPanel) ? (string) file_get_contents($contactsPanel) : '';
$clientDetail = __DIR__ . '/../frontend/src/views/clients/ClientDetailView.vue';
$clientDetailSource = file_exists($clientDetail) ? (string) file_get_contents($clientDetail) : '';
record($results, 'ClientContactsPanel component', ['status' => file_exists($contactsPanel) ? 200 : 404, 'body' => null], [200]);
record($results, 'ClientDetailView uses contacts panel', ['status' => str_contains($clientDetailSource, 'ClientContactsPanel') ? 200 : 404, 'body' => null], [200]);
record($results, 'ClientContactsPanel CRUD API calls', ['status' => str_contains($contactsSource, 'clientContactsApi') ? 200 : 404, 'body' => null], [200]);

echo "=== FRONTEND DOCUMENT FOLDERS UI ===\n";
$docsPanel = __DIR__ . '/../frontend/src/components/cases/CaseDocumentsPanel.vue';
$docsPanelSource = file_exists($docsPanel) ? (string) file_get_contents($docsPanel) : '';
record($results, 'CaseDocumentsPanel folder tree', ['status' => str_contains($docsPanelSource, 'documentFoldersApi') && str_contains($docsPanelSource, 'Folders') ? 200 : 404, 'body' => null], [200]);
record($results, 'CaseDocumentsPanel assign folder', ['status' => str_contains($docsPanelSource, 'assignDocumentFolder') ? 200 : 404, 'body' => null], [200]);
record($results, 'CaseDocumentsPanel checkout toggle', ['status' => str_contains($docsPanelSource, 'toggleCheckout') ? 200 : 404, 'body' => null], [200]);

echo "=== DOCUMENT TYPES ENUM (Wave 7) ===\n";
$docTypes = \App\Models\LegalDocument::CASE_DOCUMENT_TYPES;
$results[] = [
    'LegalDocument::CASE_DOCUMENT_TYPES count',
    (string) count($docTypes),
    count($docTypes) === 10 ? 'OK' : 'FAIL',
];
$results[] = [
    '  includes pleading + court_filing',
    in_array('pleading', $docTypes, true) && in_array('court_filing', $docTypes, true) ? 'yes' : 'no',
    in_array('pleading', $docTypes, true) && in_array('court_filing', $docTypes, true) ? 'OK' : 'FAIL',
];
$docListRes = call($http, $token, 'GET', '/documents', ['legal_matter_id' => $matter->id]);
$docListBody = $docListRes['body'] ?? [];
$results[] = [
    '  GET /documents meta.document_types',
    isset($docListBody['document_types']) && is_array($docListBody['document_types']) ? 'yes' : 'no',
    isset($docListBody['document_types']) && is_array($docListBody['document_types']) ? 'OK' : 'FAIL',
];
$enumsFile = __DIR__ . '/../frontend/src/lib/enums.ts';
$enumsSource = file_exists($enumsFile) ? (string) file_get_contents($enumsFile) : '';
record($results, 'enums.ts DOCUMENT_TYPES', ['status' => str_contains($enumsSource, 'DOCUMENT_TYPES') && str_contains($enumsSource, 'documentTypeBadge') ? 200 : 404, 'body' => null], [200]);
record($results, 'CaseDocumentsPanel type filter', ['status' => str_contains($docsPanelSource, 'typeFilter') && str_contains($docsPanelSource, 'DOCUMENT_TYPES') ? 200 : 404, 'body' => null], [200]);
record($results, 'CaseDocumentsPanel type badges', ['status' => str_contains($docsPanelSource, 'documentTypeBadge') ? 200 : 404, 'body' => null], [200]);

if ($ids['doc'] ?? null) {
    $htmlDocRes = call($http, $token, 'PUT', "/documents/{$ids['doc']}", [
        'content_html' => '<p>PDF export verify content</p>',
    ]);
    record($results, 'PUT /documents/{id} content_html', $htmlDocRes, [200]);
    $exportRequest = Request::create("/api/v1/documents/{$ids['doc']}/export-pdf", 'GET', [], [], [], [
        'HTTP_Authorization' => 'Bearer ' . $token,
        'HTTP_Accept' => 'application/json',
    ]);
    $exportResponse = $http->handle($exportRequest);
    record($results, 'GET /documents/{id}/export-pdf', ['status' => $exportResponse->getStatusCode(), 'body' => null], [200]);
    $contentType = (string) $exportResponse->headers->get('Content-Type');
    $results[] = [
        '  Dompdf class available',
        class_exists(\Dompdf\Dompdf::class) ? 'yes' : 'no',
        class_exists(\Dompdf\Dompdf::class) ? 'OK' : 'FAIL',
    ];
    $results[] = [
        '  export-pdf Content-Type pdf',
        str_contains($contentType, 'application/pdf') ? 'yes' : 'no',
        str_contains($contentType, 'application/pdf') ? 'OK' : 'FAIL',
    ];
}

echo "=== CASE FORM PARITY (Wave 8) ===\n";
$caseFormView = __DIR__ . '/../frontend/src/views/cases/CaseFormView.vue';
$caseFormSource = file_exists($caseFormView) ? (string) file_get_contents($caseFormView) : '';
record($results, 'CaseFormView practice_area field', ['status' => str_contains($caseFormSource, 'practice_area') ? 200 : 404, 'body' => null], [200]);
record($results, 'CaseFormView tags field', ['status' => str_contains($caseFormSource, 'tags_text') ? 200 : 404, 'body' => null], [200]);
record($results, 'CaseFormView trust_balance field', ['status' => str_contains($caseFormSource, 'trust_balance') ? 200 : 404, 'body' => null], [200]);
$results[] = [
    'legal_matters.trust_balance column',
    Schema::hasColumn('legal_matters', 'trust_balance') ? 'yes' : 'no',
    Schema::hasColumn('legal_matters', 'trust_balance') ? 'OK' : 'FAIL',
];
$results[] = [
    'trust_ledger_entries table',
    Schema::hasTable('trust_ledger_entries') ? 'yes' : 'no',
    Schema::hasTable('trust_ledger_entries') ? 'OK' : 'FAIL',
];
$caseDetailView = __DIR__ . '/../frontend/src/views/cases/CaseDetailView.vue';
$caseDetailSource = file_exists($caseDetailView) ? (string) file_get_contents($caseDetailView) : '';
record($results, 'CaseDetailView trust ledger stub', ['status' => str_contains($caseDetailSource, 'trust_ledger') ? 200 : 404, 'body' => null], [200]);

echo "=== DELETES (cleanup) ===\n";
if ($ids['client_contact'] ?? null) {
    record($results, 'DELETE /client-contacts/{id}', call($http, $token, 'DELETE', "/client-contacts/{$ids['client_contact']}"), [200]);
}
if ($ids['conflict'] ?? null) {
    record($results, 'DELETE /conflict-checks/{id}', call($http, $token, 'DELETE', "/conflict-checks/{$ids['conflict']}"), [200]);
}
if ($ids['form'] ?? null) {
    record($results, 'DELETE /intake-forms/{id}', call($http, $token, 'DELETE', "/intake-forms/{$ids['form']}"), [200]);
}
if ($ids['doc'] ?? null) {
    record($results, 'DELETE /documents/{id}', call($http, $token, 'DELETE', "/documents/{$ids['doc']}"), [200]);
}
if ($ids['event'] ?? null) {
    record($results, 'DELETE /calendar-events/{id}', call($http, $token, 'DELETE', "/calendar-events/{$ids['event']}"), [200]);
}
if ($ids['task'] ?? null) {
    record($results, 'DELETE /tasks/{id}', call($http, $token, 'DELETE', "/tasks/{$ids['task']}"), [200]);
}
if ($ids['note'] ?? null) {
    record($results, 'DELETE /case-notes/{id}', call($http, $token, 'DELETE', "/case-notes/{$ids['note']}"), [200]);
}

echo "\n==================== PHASE 2 ENDPOINT RESULTS ====================\n";
$pass = 0;
$fail = 0;
foreach ($results as [$label, $status, $verdict]) {
    printf("%-45s %-5s %s\n", $label, $status, $verdict);
    $verdict === 'OK' ? $pass++ : $fail++;
}
echo "------------------------------------------------------------------\n";
echo "PASS: {$pass}  FAIL: {$fail}\n";

@unlink($tmp);

exit($fail > 0 ? 1 : 0);
