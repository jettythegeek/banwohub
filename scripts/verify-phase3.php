<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use App\Models\Appointment;
use App\Models\CalendarEvent;
use App\Models\CaseExpense;
use App\Models\Client;
use App\Models\CommunicationLog;
use App\Models\IntakeForm;
use App\Models\IntakeSubmission;
use App\Models\Invoice;
use App\Models\LawyerAvailabilitySlot;
use App\Models\LegalDocument;
use App\Models\LegalMatter;
use App\Models\LegalTask;
use App\Models\Message;
use App\Models\MessageThread;
use App\Models\Payment;
use App\Models\User;
use App\Services\InvoicePaymentService;
use App\Models\AppNotification;
use App\Events\MessageSent;
use Spatie\Permission\Models\Permission;
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

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

$token = $admin->createToken('phase3-verify')->plainTextToken;

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

function portalCall(HttpKernel $http, string $token, string $method, string $uri, array $payload = [], array $files = []): array
{
    return call($http, $token, $method, '/portal' . $uri, $payload, $files);
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

// --- Prerequisites: a client + case + task in the admin's org ---
$orgId = $admin->organization_id;
$client = Client::query()->create([
    'organization_id' => $orgId,
    'type' => 'individual',
    'name' => 'Phase3 Test Client ' . uniqid(),
    'email' => 'phase3client@example.com',
    'status' => 'active',
    'created_by' => $admin->id,
]);
$matter = LegalMatter::query()->create([
    'organization_id' => $orgId,
    'client_id' => $client->id,
    'title' => 'Phase3 Test Matter',
    'matter_number' => 'P3-' . uniqid(),
    'status' => 'new',
    'priority' => 'normal',
    'created_by' => $admin->id,
]);
$task = LegalTask::query()->create([
    'organization_id' => $orgId,
    'legal_matter_id' => $matter->id,
    'assignee_id' => $admin->id,
    'created_by' => $admin->id,
    'title' => 'Phase3 Test Task',
    'status' => 'not_started',
    'priority' => 'normal',
]);

echo "=== TIME ENTRIES (manual) ===\n";
$res = call($http, $token, 'POST', '/time-entries', [
    'legal_matter_id' => $matter->id,
    'legal_task_id' => $task->id,
    'description' => 'Drafting pleadings',
    'duration_minutes' => 90,
    'billable' => true,
    'rate' => 1500,
]);
record($results, 'POST /time-entries (manual)', $res, [201]);
$ids['entry'] = resId($res);

// Verify computed amount = 1500 * 1.5 = 2250
$amount = $res['body']['amount'] ?? ($res['body']['data']['amount'] ?? null);
$results[] = ['  amount == 2250', $amount, ((float) $amount === 2250.0) ? 'OK' : 'FAIL'];

$res = call($http, $token, 'POST', '/time-entries', [
    'legal_matter_id' => $matter->id,
    'started_at' => now()->subHours(2)->toDateTimeString(),
    'ended_at' => now()->subHour()->toDateTimeString(),
    'description' => 'Client call',
    'billable' => false,
]);
record($results, 'POST /time-entries (start/end)', $res, [201]);
$ids['entry2'] = resId($res);
// Duration from 1h window should be 60 minutes.
$dur = $res['body']['duration_minutes'] ?? ($res['body']['data']['duration_minutes'] ?? null);
$results[] = ['  duration == 60', $dur, ((int) $dur === 60) ? 'OK' : 'FAIL'];

echo "=== TIME ENTRIES (list / summary) ===\n";
$listRes = call($http, $token, 'GET', '/time-entries?legal_matter_id=' . $matter->id);
record($results, 'GET /time-entries', $listRes, [200]);
$summary = $listRes['body']['meta']['summary'] ?? null;
$results[] = ['  summary present', $summary ? 'yes' : 'no', $summary ? 'OK' : 'FAIL'];
if ($summary) {
    $results[] = ['  summary.total_minutes == 150', $summary['total_minutes'], ((int) $summary['total_minutes'] === 150) ? 'OK' : 'FAIL'];
    $results[] = ['  summary.billable_minutes == 90', $summary['billable_minutes'], ((int) $summary['billable_minutes'] === 90) ? 'OK' : 'FAIL'];
}

if ($ids['entry']) {
    record($results, 'GET /time-entries/{id}', call($http, $token, 'GET', "/time-entries/{$ids['entry']}"), [200]);
    record($results, 'PUT /time-entries/{id}', call($http, $token, 'PUT', "/time-entries/{$ids['entry']}", [
        'description' => 'Drafting pleadings (revised)',
        'duration_minutes' => 120,
    ]), [200]);
}

echo "=== TIMER (start / running / stop) ===\n";
$startRes = call($http, $token, 'POST', '/time-entries/timer/start', [
    'legal_matter_id' => $matter->id,
    'description' => 'Live research',
]);
record($results, 'POST /time-entries/timer/start', $startRes, [200, 201]);
$ids['timer'] = resId($startRes);
$isRunning = $startRes['body']['is_running'] ?? ($startRes['body']['data']['is_running'] ?? null);
$results[] = ['  timer is_running', $isRunning ? 'true' : 'false', $isRunning ? 'OK' : 'FAIL'];

$runningRes = call($http, $token, 'GET', '/time-entries/running');
record($results, 'GET /time-entries/running', $runningRes, [200]);
$runningId = $runningRes['body']['data']['id'] ?? null;
$results[] = ['  running matches timer', $runningId, ((int) $runningId === (int) $ids['timer']) ? 'OK' : 'FAIL'];

if ($ids['timer']) {
    $stopRes = call($http, $token, 'POST', "/time-entries/{$ids['timer']}/stop");
    record($results, 'POST /time-entries/{id}/stop', $stopRes, [200]);
    $stoppedRunning = $stopRes['body']['is_running'] ?? ($stopRes['body']['data']['is_running'] ?? true);
    $results[] = ['  stopped is_running == false', $stoppedRunning ? 'true' : 'false', ! $stoppedRunning ? 'OK' : 'FAIL'];
}

echo "=== APPROVE ===\n";
if ($ids['entry']) {
    $approveRes = call($http, $token, 'POST', "/time-entries/{$ids['entry']}/approve");
    record($results, 'POST /time-entries/{id}/approve', $approveRes, [200]);
    $status = $approveRes['body']['status'] ?? ($approveRes['body']['data']['status'] ?? null);
    $results[] = ['  status == approved', $status, ($status === 'approved') ? 'OK' : 'FAIL'];
}

echo "=== DELETES (cleanup time entries) ===\n";
foreach (['entry2', 'timer'] as $key) {
    if ($ids[$key] ?? null) {
        record($results, "DELETE /time-entries/{$key}", call($http, $token, 'DELETE', "/time-entries/{$ids[$key]}"), [200]);
    }
}

echo "=== INVOICES (manual create) ===\n";
$res = call($http, $token, 'POST', '/invoices', [
    'client_id' => $client->id,
    'legal_matter_id' => $matter->id,
    'issue_date' => now()->toDateString(),
    'due_date' => now()->addDays(14)->toDateString(),
    'tax_rate' => 15,
    'line_items' => [
        [
            'description' => 'Consultation fee',
            'quantity' => 1,
            'unit_price' => 5000,
            'line_type' => 'service',
        ],
    ],
]);
record($results, 'POST /invoices (manual)', $res, [201]);
$ids['invoice_manual'] = resId($res);
$manualTotal = $res['body']['total_amount'] ?? ($res['body']['data']['total_amount'] ?? null);
$results[] = ['  manual total == 5750', $manualTotal, ((float) $manualTotal === 5750.0) ? 'OK' : 'FAIL'];

echo "=== INVOICES (generate from time) ===\n";
if ($ids['entry']) {
    $genRes = call($http, $token, 'POST', '/invoices/generate-from-time-entries', [
        'client_id' => $client->id,
        'legal_matter_id' => $matter->id,
        'time_entry_ids' => [$ids['entry']],
    ]);
    record($results, 'POST /invoices/generate-from-time-entries', $genRes, [201]);
    $ids['invoice_generated'] = resId($genRes);
    $lineCount = count($genRes['body']['line_items'] ?? ($genRes['body']['data']['line_items'] ?? []));
    $results[] = ['  generated line_items == 1', $lineCount, ($lineCount === 1) ? 'OK' : 'FAIL'];
    $genTotal = $genRes['body']['total_amount'] ?? ($genRes['body']['data']['total_amount'] ?? null);
    $results[] = ['  generated total == 3000', $genTotal, ((float) $genTotal === 3000.0) ? 'OK' : 'FAIL'];
}

echo "=== INVOICES (list / show) ===\n";
$listInv = call($http, $token, 'GET', '/invoices?client_id=' . $client->id);
record($results, 'GET /invoices', $listInv, [200]);
$invSummary = $listInv['body']['meta']['summary'] ?? null;
$results[] = ['  invoice summary present', $invSummary ? 'yes' : 'no', $invSummary ? 'OK' : 'FAIL'];

if ($ids['invoice_generated'] ?? null) {
    record($results, 'GET /invoices/{id}', call($http, $token, 'GET', "/invoices/{$ids['invoice_generated']}"), [200]);
}

echo "=== INVOICES (mark sent / payment / export) ===\n";
if ($ids['invoice_generated'] ?? null) {
    $sentRes = call($http, $token, 'POST', "/invoices/{$ids['invoice_generated']}/mark-sent");
    record($results, 'POST /invoices/{id}/mark-sent', $sentRes, [200]);
    $sentStatus = $sentRes['body']['status'] ?? ($sentRes['body']['data']['status'] ?? null);
    $results[] = ['  status == sent', $sentStatus, ($sentStatus === 'sent') ? 'OK' : 'FAIL'];

    $payRes = call($http, $token, 'POST', "/invoices/{$ids['invoice_generated']}/record-payment", [
        'amount' => 1500,
        'payment_method' => 'bank_transfer',
        'notes' => 'Partial payment',
    ]);
    record($results, 'POST /invoices/{id}/record-payment (partial)', $payRes, [200]);
    $partialStatus = $payRes['body']['status'] ?? ($payRes['body']['data']['status'] ?? null);
    $partialBalance = $payRes['body']['balance_due'] ?? ($payRes['body']['data']['balance_due'] ?? null);
    $results[] = ['  partial status', $partialStatus, ($partialStatus === 'partial') ? 'OK' : 'FAIL'];
    $results[] = ['  partial balance == 1500', $partialBalance, ((float) $partialBalance === 1500.0) ? 'OK' : 'FAIL'];

    $payFullRes = call($http, $token, 'POST', "/invoices/{$ids['invoice_generated']}/record-payment", [
        'amount' => 1500,
        'payment_method' => 'bank_transfer',
    ]);
    record($results, 'POST /invoices/{id}/record-payment (full)', $payFullRes, [200]);
    $paidStatus = $payFullRes['body']['status'] ?? ($payFullRes['body']['data']['status'] ?? null);
    $paidBalance = $payFullRes['body']['balance_due'] ?? ($payFullRes['body']['data']['balance_due'] ?? null);
    $results[] = ['  paid status', $paidStatus, ($paidStatus === 'paid') ? 'OK' : 'FAIL'];
    $results[] = ['  paid balance == 0', $paidBalance, ((float) $paidBalance === 0.0) ? 'OK' : 'FAIL'];

    $manualPayments = Payment::query()
        ->where('invoice_id', $ids['invoice_generated'])
        ->where('gateway', 'bank_transfer')
        ->where('status', 'completed')
        ->count();
    $results[] = ['  manual payment rows', $manualPayments, ($manualPayments >= 2) ? 'OK' : 'FAIL'];

    $exportRes = call($http, $token, 'GET', "/invoices/{$ids['invoice_generated']}/export-pdf");
    record($results, 'GET /invoices/{id}/export-pdf', $exportRes, [200]);
}

echo "=== INVOICES (portal checkout fixture) ===\n";
$unpaidRes = call($http, $token, 'POST', '/invoices', [
    'client_id' => $client->id,
    'legal_matter_id' => $matter->id,
    'issue_date' => now()->toDateString(),
    'due_date' => now()->addDays(7)->toDateString(),
    'line_items' => [
        [
            'description' => 'Portal payment test',
            'quantity' => 1,
            'unit_price' => 2500,
            'line_type' => 'service',
        ],
    ],
]);
record($results, 'POST /invoices (portal unpaid)', $unpaidRes, [201]);
$ids['invoice_unpaid'] = resId($unpaidRes);
if ($ids['invoice_unpaid'] ?? null) {
    $sentUnpaid = call($http, $token, 'POST', "/invoices/{$ids['invoice_unpaid']}/mark-sent");
    record($results, 'POST /invoices/{id}/mark-sent (portal)', $sentUnpaid, [200]);
}

echo "=== INVOICES (cleanup) ===\n";
if ($ids['invoice_manual'] ?? null) {
    record($results, 'DELETE /invoices/manual', call($http, $token, 'DELETE', "/invoices/{$ids['invoice_manual']}"), [200]);
}

echo "=== PAYMENTS (service idempotency) ===\n";
if ($ids['invoice_unpaid'] ?? null) {
    $invoiceModel = Invoice::query()->find($ids['invoice_unpaid']);
    $service = app(InvoicePaymentService::class);
    $service->applyPayment($invoiceModel, 1000, 'bank_transfer', 'idem-test-1', ['test' => true], 'bank_transfer');
    $service->applyPayment($invoiceModel->fresh(), 1000, 'bank_transfer', 'idem-test-1', ['test' => true], 'bank_transfer');
    $idemCount = Payment::query()->where('gateway', 'bank_transfer')->where('external_id', 'idem-test-1')->count();
    $results[] = ['  idempotent external_id', $idemCount, ($idemCount === 1) ? 'OK' : 'FAIL'];
}

echo "=== CLIENT PORTAL (setup) ===\n";
$portalPassword = 'PortalTest123!';
$portalUser = User::query()->create([
    'organization_id' => $orgId,
    'client_id' => $client->id,
    'name' => 'Portal Test User',
    'email' => 'portal-' . uniqid() . '@example.com',
    'password' => Hash::make($portalPassword),
    'is_active' => true,
]);
$portalUser->assignRole('Client');
Permission::findOrCreate('portal.invoices.pay', 'web');
Permission::findOrCreate('portal.messages.view', 'web');
Permission::findOrCreate('portal.messages.create', 'web');
Permission::findOrCreate('portal.documents.upload', 'web');
Permission::findOrCreate('portal.profile.update', 'web');
$portalUser->givePermissionTo([
    'portal.invoices.pay',
    'portal.messages.view',
    'portal.messages.create',
    'portal.documents.upload',
    'portal.profile.update',
]);

$otherClient = Client::query()->create([
    'organization_id' => $orgId,
    'type' => 'individual',
    'name' => 'Other Client ' . uniqid(),
    'email' => 'otherclient@example.com',
    'status' => 'active',
    'created_by' => $admin->id,
]);
$otherMatter = LegalMatter::query()->create([
    'organization_id' => $orgId,
    'client_id' => $otherClient->id,
    'title' => 'Other Client Matter',
    'matter_number' => 'P3O-' . uniqid(),
    'status' => 'new',
    'priority' => 'normal',
    'created_by' => $admin->id,
]);

$sharedPath = 'portal-tests/shared-' . uniqid() . '.txt';
$hiddenPath = 'portal-tests/hidden-' . uniqid() . '.txt';
Storage::disk('local')->put($sharedPath, 'shared with client');
Storage::disk('local')->put($hiddenPath, 'internal only');

$sharedDoc = LegalDocument::query()->create([
    'organization_id' => $orgId,
    'legal_matter_id' => $matter->id,
    'uploaded_by' => $admin->id,
    'document_type' => 'case_document',
    'name' => 'Shared Portal Doc',
    'original_filename' => 'shared.txt',
    'mime_type' => 'text/plain',
    'size' => 17,
    'disk' => 'local',
    'path' => $sharedPath,
    'client_visible' => true,
]);
LegalDocument::query()->create([
    'organization_id' => $orgId,
    'legal_matter_id' => $matter->id,
    'uploaded_by' => $admin->id,
    'document_type' => 'case_document',
    'name' => 'Internal Portal Doc',
    'original_filename' => 'hidden.txt',
    'mime_type' => 'text/plain',
    'size' => 14,
    'disk' => 'local',
    'path' => $hiddenPath,
    'client_visible' => false,
]);

$staffLoginBlock = call($http, '', 'POST', '/auth/login', [
    'email' => $portalUser->email,
    'password' => $portalPassword,
]);
$results[] = ['  staff login blocks portal user', $staffLoginBlock['status'], ($staffLoginBlock['status'] === 422) ? 'OK' : 'FAIL'];

$portalLogin = portalCall($http, '', 'POST', '/auth/login', [
    'email' => $portalUser->email,
    'password' => $portalPassword,
]);
record($results, 'POST /portal/auth/login', $portalLogin, [200]);
$portalToken = $portalLogin['body']['token'] ?? null;
$results[] = ['  portal token issued', $portalToken ? 'yes' : 'no', $portalToken ? 'OK' : 'FAIL'];

echo "=== CLIENT PORTAL (scoped access) ===\n";
record($results, 'GET /portal/auth/me', portalCall($http, $portalToken, 'GET', '/auth/me'), [200]);

echo "=== CLIENT PORTAL (profile settings) ===\n";
$profileRes = portalCall($http, $portalToken, 'PATCH', '/auth/profile', [
    'name' => 'Portal User Updated',
    'phone' => '+1-555-9999',
]);
record($results, 'PATCH /portal/auth/profile', $profileRes, [200]);
$profileBody = $profileRes['body']['data'] ?? $profileRes['body'] ?? [];
$results[] = ['  profile name updated', ($profileBody['name'] ?? '') === 'Portal User Updated' ? 'yes' : 'no', (($profileBody['name'] ?? '') === 'Portal User Updated') ? 'OK' : 'FAIL'];
$results[] = ['  profile phone updated', ($profileBody['phone'] ?? '') === '+1-555-9999' ? 'yes' : 'no', (($profileBody['phone'] ?? '') === '+1-555-9999') ? 'OK' : 'FAIL'];

$portalProfileView = __DIR__ . '/../frontend/src/views/portal/PortalProfileSettingsView.vue';
$portalProfileSource = file_exists($portalProfileView) ? (string) file_get_contents($portalProfileView) : '';
record($results, 'PortalProfileSettingsView exists', ['status' => file_exists($portalProfileView) ? 200 : 404, 'body' => null], [200]);
record($results, 'Portal profile updateProfile API', ['status' => str_contains($portalProfileSource, 'updateProfile') ? 200 : 404, 'body' => null], [200]);

echo "=== SERVICE ITEMS + PAYMENT METHODS ===\n";
foreach ([
    'service-items.view',
    'service-items.create',
    'service-items.update',
    'service-items.delete',
] as $perm) {
    Permission::findOrCreate($perm, 'web');
    $admin->givePermissionTo($perm);
}
$results[] = [
    'service_items table',
    \Illuminate\Support\Facades\Schema::hasTable('service_items') ? 'yes' : 'no',
    \Illuminate\Support\Facades\Schema::hasTable('service_items') ? 'OK' : 'FAIL',
];
$results[] = [
    'invoice_line_items.service_item_id',
    \Illuminate\Support\Facades\Schema::hasColumn('invoice_line_items', 'service_item_id') ? 'yes' : 'no',
    \Illuminate\Support\Facades\Schema::hasColumn('invoice_line_items', 'service_item_id') ? 'OK' : 'FAIL',
];
$serviceItemRes = call($http, $token, 'POST', '/service-items', [
    'name' => 'Consultation',
    'description' => 'Initial consultation',
    'default_rate' => 5000,
]);
record($results, 'POST /service-items', $serviceItemRes, [201]);
$ids['service_item'] = resId($serviceItemRes);
record($results, 'GET /service-items', call($http, $token, 'GET', '/service-items'), [200]);
if ($ids['service_item'] ?? null) {
    $svcInvoiceRes = call($http, $token, 'POST', '/invoices', [
        'client_id' => $client->id,
        'legal_matter_id' => $matter->id,
        'issue_date' => now()->toDateString(),
        'line_items' => [
            [
                'description' => 'Consultation from catalog',
                'quantity' => 1,
                'unit_price' => 5000,
                'line_type' => 'service',
                'service_item_id' => $ids['service_item'],
            ],
        ],
    ]);
    record($results, 'POST /invoices (service_item link)', $svcInvoiceRes, [201]);
    $svcLine = $svcInvoiceRes['body']['line_items'][0] ?? ($svcInvoiceRes['body']['data']['line_items'][0] ?? null);
    $results[] = ['  line service_item_id set', ($svcLine['service_item_id'] ?? null) == $ids['service_item'] ? 'yes' : 'no', (($svcLine['service_item_id'] ?? null) == $ids['service_item']) ? 'OK' : 'FAIL'];
    $svcInvoiceId = resId($svcInvoiceRes);
    if ($svcInvoiceId) {
        record($results, 'DELETE /invoices/{service}', call($http, $token, 'DELETE', "/invoices/{$svcInvoiceId}"), [200]);
    }
}
$paymentMethods = \App\Models\Payment::MANUAL_GATEWAYS;
$results[] = ['  Payment::MANUAL_GATEWAYS count', count($paymentMethods), (count($paymentMethods) === 5) ? 'OK' : 'FAIL'];
$invoiceDetailView = __DIR__ . '/../frontend/src/views/invoices/InvoiceDetailView.vue';
$invoiceDetailSource = file_exists($invoiceDetailView) ? (string) file_get_contents($invoiceDetailView) : '';
record($results, 'InvoiceDetailView payment method labels', ['status' => str_contains($invoiceDetailSource, 'paymentMethodLabel') && str_contains($invoiceDetailSource, 'PAYMENT_METHODS') ? 200 : 404, 'body' => null], [200]);
record($results, 'InvoiceDetailView service catalog picker', ['status' => str_contains($invoiceDetailSource, 'serviceItemsApi') && str_contains($invoiceDetailSource, 'applyServiceItem') ? 200 : 404, 'body' => null], [200]);

$dashRes = portalCall($http, $portalToken, 'GET', '/dashboard');
record($results, 'GET /portal/dashboard', $dashRes, [200]);
$dashBody = $dashRes['body'] ?? [];
$results[] = [
    '  portal dashboard insights array',
    isset($dashBody['insights']) && is_array($dashBody['insights']) ? 'yes' : 'no',
    isset($dashBody['insights']) && is_array($dashBody['insights']) ? 'OK' : 'FAIL',
];
$insightTypes = array_column($dashBody['insights'] ?? [], 'type');
$results[] = [
    '  portal insight case_status',
    in_array('case_status', $insightTypes, true) ? 'yes' : 'no',
    in_array('case_status', $insightTypes, true) ? 'OK' : 'FAIL',
];
$portalDashView = __DIR__ . '/../frontend/src/views/portal/PortalDashboardView.vue';
$portalDashSource = file_exists($portalDashView) ? (string) file_get_contents($portalDashView) : '';
record($results, 'PortalDashboardView insights UI', ['status' => str_contains($portalDashSource, 'insights') && str_contains($portalDashSource, 'PhLightbulb') ? 200 : 404, 'body' => null], [200]);

$casesRes = portalCall($http, $portalToken, 'GET', '/cases');
record($results, 'GET /portal/cases', $casesRes, [200]);
$caseIds = array_column($casesRes['body']['data'] ?? [], 'id');
$results[] = ['  portal sees own case', in_array($matter->id, $caseIds, true) ? 'yes' : 'no', in_array($matter->id, $caseIds, true) ? 'OK' : 'FAIL'];
$results[] = ['  portal hides other case', in_array($otherMatter->id, $caseIds, true) ? 'no' : 'yes', ! in_array($otherMatter->id, $caseIds, true) ? 'OK' : 'FAIL'];

record($results, 'GET /portal/cases/{own}', portalCall($http, $portalToken, 'GET', "/cases/{$matter->id}"), [200]);
record($results, 'GET /portal/cases/{other} blocked', portalCall($http, $portalToken, 'GET', "/cases/{$otherMatter->id}"), [404]);

$docsRes = portalCall($http, $portalToken, 'GET', '/documents');
record($results, 'GET /portal/documents', $docsRes, [200]);
$docIds = array_column($docsRes['body']['data'] ?? [], 'id');
$results[] = ['  shared doc visible', in_array($sharedDoc->id, $docIds, true) ? 'yes' : 'no', in_array($sharedDoc->id, $docIds, true) ? 'OK' : 'FAIL'];
$results[] = ['  hidden doc excluded', count($docIds) === 1 ? 'yes' : 'no', (count($docIds) === 1) ? 'OK' : 'FAIL'];

record($results, 'GET /portal/documents/{id}/download', portalCall($http, $portalToken, 'GET', "/documents/{$sharedDoc->id}/download"), [200]);

$invRes = portalCall($http, $portalToken, 'GET', '/invoices');
record($results, 'GET /portal/invoices', $invRes, [200]);
$portalInvIds = array_column($invRes['body']['data'] ?? [], 'id');
$results[] = ['  portal sees sent invoice', in_array($ids['invoice_generated'] ?? 0, $portalInvIds, true) ? 'yes' : 'no', in_array($ids['invoice_generated'] ?? 0, $portalInvIds, true) ? 'OK' : 'FAIL'];

if ($ids['invoice_generated'] ?? null) {
    record($results, 'GET /portal/invoices/{id}', portalCall($http, $portalToken, 'GET', "/invoices/{$ids['invoice_generated']}"), [200]);
}

if ($ids['invoice_unpaid'] ?? null) {
    record($results, 'GET /portal/invoices/{unpaid}', portalCall($http, $portalToken, 'GET', "/invoices/{$ids['invoice_unpaid']}"), [200]);
    $unpaidBody = portalCall($http, $portalToken, 'GET', "/invoices/{$ids['invoice_unpaid']}");
    $gateways = $unpaidBody['body']['payment_gateways'] ?? null;
    $results[] = ['  payment_gateways present', $gateways ? 'yes' : 'no', $gateways ? 'OK' : 'FAIL'];
}

echo "=== CLIENT PORTAL (payment checkout) ===\n";
$gwRes = portalCall($http, $portalToken, 'GET', '/invoices/payment/gateways');
record($results, 'GET /portal/invoices/payment/gateways', $gwRes, [200]);
$stripeGw = $gwRes['body']['gateways']['stripe'] ?? null;
$paypalGw = $gwRes['body']['gateways']['paypal'] ?? null;
$results[] = ['  gateways.stripe key', isset($stripeGw['enabled']) ? 'yes' : 'no', isset($stripeGw['enabled']) ? 'OK' : 'FAIL'];
$results[] = ['  gateways.paypal key', isset($paypalGw['enabled']) ? 'yes' : 'no', isset($paypalGw['enabled']) ? 'OK' : 'FAIL'];

if ($ids['invoice_unpaid'] ?? null) {
    $stripeCheckout = portalCall($http, $portalToken, 'POST', "/invoices/{$ids['invoice_unpaid']}/checkout/stripe");
    $stripeKeys = filled(env('STRIPE_SECRET'));
    $expectedStripe = $stripeKeys ? [200] : [503];
    record($results, 'POST /portal/.../checkout/stripe', $stripeCheckout, $expectedStripe);
    if (! $stripeKeys) {
        $results[] = ['  stripe graceful 503', $stripeCheckout['status'], ($stripeCheckout['status'] === 503) ? 'OK' : 'FAIL'];
        $results[] = ['  stripe message present', isset($stripeCheckout['body']['message']) ? 'yes' : 'no', isset($stripeCheckout['body']['message']) ? 'OK' : 'FAIL'];
    } else {
        $results[] = ['  stripe checkout_url', isset($stripeCheckout['body']['checkout_url']) ? 'yes' : 'no', isset($stripeCheckout['body']['checkout_url']) ? 'OK' : 'FAIL'];
    }

    $paypalCheckout = portalCall($http, $portalToken, 'POST', "/invoices/{$ids['invoice_unpaid']}/checkout/paypal");
    $paypalKeys = filled(env('PAYPAL_CLIENT_ID')) && filled(env('PAYPAL_CLIENT_SECRET'));
    $expectedPaypal = $paypalKeys ? [200] : [503];
    record($results, 'POST /portal/.../checkout/paypal', $paypalCheckout, $expectedPaypal);
    if (! $paypalKeys) {
        $results[] = ['  paypal graceful 503', $paypalCheckout['status'], ($paypalCheckout['status'] === 503) ? 'OK' : 'FAIL'];
    } else {
        $results[] = ['  paypal approval_url', isset($paypalCheckout['body']['approval_url']) ? 'yes' : 'no', isset($paypalCheckout['body']['approval_url']) ? 'OK' : 'FAIL'];
    }
}

echo "=== PAYMENT WEBHOOKS (structure) ===\n";
$stripeHook = call($http, '', 'POST', '/webhooks/stripe', ['type' => 'checkout.session.completed']);
$results[] = ['  stripe webhook rejects bad sig', $stripeHook['status'], ($stripeHook['status'] === 400) ? 'OK' : 'FAIL'];
$paypalHook = call($http, '', 'POST', '/webhooks/paypal', ['event_type' => 'PAYMENT.CAPTURE.COMPLETED', 'resource' => ['id' => 'TEST']]);
$results[] = ['  paypal webhook accepts payload', $paypalHook['status'], ($paypalHook['status'] === 200) ? 'OK' : 'FAIL'];

record($results, 'POST /portal/auth/logout', portalCall($http, $portalToken, 'POST', '/auth/logout'), [200]);

echo "=== IN-APP MESSAGING ===\n";
Permission::findOrCreate('messages.view', 'web');
Permission::findOrCreate('messages.create', 'web');
$admin->givePermissionTo(['messages.view', 'messages.create']);

$threadRes = call($http, $token, 'POST', '/message-threads', [
    'client_id' => $client->id,
    'legal_matter_id' => $matter->id,
    'subject' => 'Phase3 messaging test',
    'body' => 'Hello from staff — initial message.',
]);
record($results, 'POST /message-threads', $threadRes, [201]);
$ids['message_thread'] = resId($threadRes);

if ($ids['message_thread'] ?? null) {
    record($results, 'GET /message-threads', call($http, $token, 'GET', '/message-threads'), [200]);
    record($results, 'GET /message-threads/{id}', call($http, $token, 'GET', "/message-threads/{$ids['message_thread']}"), [200]);

    $replyRes = call($http, $token, 'POST', "/message-threads/{$ids['message_thread']}/messages", [
        'body' => 'Follow-up from staff.',
    ]);
    record($results, 'POST /message-threads/{id}/messages (staff)', $replyRes, [201]);

    $notifCount = AppNotification::query()
        ->where('user_id', $portalUser->id)
        ->where('type', 'portal_message_received')
        ->count();
    $results[] = ['  staff message notifies portal user', $notifCount, ($notifCount >= 1) ? 'OK' : 'FAIL'];
}

$portalLogin2 = portalCall($http, '', 'POST', '/auth/login', [
    'email' => $portalUser->email,
    'password' => $portalPassword,
]);
$portalToken2 = $portalLogin2['body']['token'] ?? null;

if ($portalToken2 && ($ids['message_thread'] ?? null)) {
    record($results, 'GET /portal/message-threads', portalCall($http, $portalToken2, 'GET', '/message-threads'), [200]);

    $portalReply = portalCall($http, $portalToken2, 'POST', "/message-threads/{$ids['message_thread']}/messages", [
        'body' => 'Reply from portal client.',
    ]);
    record($results, 'POST /portal/message-threads/{id}/messages', $portalReply, [201]);

    $staffNotif = AppNotification::query()
        ->where('user_id', $admin->id)
        ->where('type', 'message_received')
        ->count();
    $results[] = ['  portal reply notifies staff', $staffNotif, ($staffNotif >= 1) ? 'OK' : 'FAIL'];

    record($results, 'POST /portal/.../mark-read', portalCall($http, $portalToken2, 'POST', "/message-threads/{$ids['message_thread']}/mark-read"), [200]);
    $unreadAfterRead = Message::query()
        ->where('message_thread_id', $ids['message_thread'])
        ->where('sender_user_id', '!=', $portalUser->id)
        ->whereNull('read_at')
        ->count();
    $results[] = ['  portal mark-read clears unread', $unreadAfterRead, ($unreadAfterRead === 0) ? 'OK' : 'FAIL'];

    $otherThread = MessageThread::query()->create([
        'organization_id' => $orgId,
        'client_id' => $otherClient->id,
        'legal_matter_id' => $otherMatter->id,
        'created_by' => $admin->id,
        'subject' => 'Other client thread',
        'last_message_at' => now(),
    ]);
    Message::query()->create([
        'message_thread_id' => $otherThread->id,
        'sender_user_id' => $admin->id,
        'body' => 'Should not be visible to portal user.',
    ]);
    record($results, 'GET /portal/message-threads/{other} blocked', portalCall($http, $portalToken2, 'GET', "/message-threads/{$otherThread->id}"), [404]);

    $dashRes = portalCall($http, $portalToken2, 'GET', '/dashboard');
    record($results, 'GET /portal/dashboard messages', $dashRes, [200]);
    $dashMessages = $dashRes['body']['messages'] ?? null;
    $results[] = ['  dashboard includes messages', is_array($dashMessages) ? 'yes' : 'no', is_array($dashMessages) ? 'OK' : 'FAIL'];

    record($results, 'POST /portal/auth/logout (messages)', portalCall($http, $portalToken2, 'POST', '/auth/logout'), [200]);
}

echo "=== CLIENT PORTAL (document upload) ===\n";
$portalLogin3 = portalCall($http, '', 'POST', '/auth/login', [
    'email' => $portalUser->email,
    'password' => $portalPassword,
]);
$portalToken3 = $portalLogin3['body']['token'] ?? null;

if ($portalToken3) {
    $pendingBefore = portalCall($http, $portalToken3, 'GET', '/documents?scope=pending&legal_matter_id=' . $matter->id);
    record($results, 'GET /portal/documents?scope=pending', $pendingBefore, [200]);

    $uploadTmp = tempnam(sys_get_temp_dir(), 'p3portal') . '.txt';
    file_put_contents($uploadTmp, 'client portal upload content');
    $portalFile = new UploadedFile($uploadTmp, 'client-upload.txt', 'text/plain', null, true);
    $uploadRes = portalCall($http, $portalToken3, 'POST', '/documents', [
        'legal_matter_id' => (string) $matter->id,
        'name' => 'Client Upload Test',
    ], ['file' => $portalFile]);
    record($results, 'POST /portal/documents (upload)', $uploadRes, [201]);
    $ids['portal_upload_doc'] = resId($uploadRes);

    $uploadBody = $uploadRes['body']['data'] ?? $uploadRes['body'] ?? [];
    $results[] = ['  upload pending status', ($uploadBody['portal_status'] ?? '') === 'pending' ? 'yes' : 'no', (($uploadBody['portal_status'] ?? '') === 'pending') ? 'OK' : 'FAIL'];
    $results[] = ['  upload not client_visible', empty($uploadBody['client_visible']) ? 'yes' : 'no', empty($uploadBody['client_visible']) ? 'OK' : 'FAIL'];

    $staffNotifUpload = AppNotification::query()
        ->where('user_id', $admin->id)
        ->where('type', 'portal_document_uploaded')
        ->count();
    $results[] = ['  portal upload notifies staff', $staffNotifUpload, ($staffNotifUpload >= 1) ? 'OK' : 'FAIL'];

    $pendingAfter = portalCall($http, $portalToken3, 'GET', '/documents?scope=pending&legal_matter_id=' . $matter->id);
    $pendingIds = array_column($pendingAfter['body']['data'] ?? [], 'id');
    $results[] = ['  pending list includes upload', in_array($ids['portal_upload_doc'] ?? 0, $pendingIds, true) ? 'yes' : 'no', in_array($ids['portal_upload_doc'] ?? 0, $pendingIds, true) ? 'OK' : 'FAIL'];

    $sharedAfterUpload = portalCall($http, $portalToken3, 'GET', '/documents?legal_matter_id=' . $matter->id);
    $sharedIdsAfter = array_column($sharedAfterUpload['body']['data'] ?? [], 'id');
    $results[] = ['  shared list excludes pending upload', in_array($ids['portal_upload_doc'] ?? 0, $sharedIdsAfter, true) ? 'no' : 'yes', ! in_array($ids['portal_upload_doc'] ?? 0, $sharedIdsAfter, true) ? 'OK' : 'FAIL'];

    if ($ids['portal_upload_doc'] ?? null) {
        record($results, 'GET /portal/documents/{pending}/download', portalCall($http, $portalToken3, 'GET', "/documents/{$ids['portal_upload_doc']}/download"), [200]);
    }

    if ($ids['portal_upload_doc'] ?? null) {
        $approveRes = call($http, $token, 'PATCH', "/documents/{$ids['portal_upload_doc']}", [
            'client_visible' => true,
        ]);
        record($results, 'PATCH /documents/{id} approve portal upload', $approveRes, [200]);

        $approvedDoc = LegalDocument::query()->find($ids['portal_upload_doc']);
        $results[] = ['  portal_reviewed_at set', $approvedDoc?->portal_reviewed_at ? 'yes' : 'no', $approvedDoc?->portal_reviewed_at ? 'OK' : 'FAIL'];
        $results[] = ['  approved client_visible', $approvedDoc?->client_visible ? 'yes' : 'no', $approvedDoc?->client_visible ? 'OK' : 'FAIL'];

        $sharedAfterApprove = portalCall($http, $portalToken3, 'GET', '/documents?legal_matter_id=' . $matter->id);
        $sharedIdsApproved = array_column($sharedAfterApprove['body']['data'] ?? [], 'id');
        $results[] = ['  shared list includes approved upload', in_array($ids['portal_upload_doc'], $sharedIdsApproved, true) ? 'yes' : 'no', in_array($ids['portal_upload_doc'], $sharedIdsApproved, true) ? 'OK' : 'FAIL'];

        $pendingAfterApprove = portalCall($http, $portalToken3, 'GET', '/documents?scope=pending&legal_matter_id=' . $matter->id);
        $pendingIdsAfter = array_column($pendingAfterApprove['body']['data'] ?? [], 'id');
        $results[] = ['  pending list excludes approved upload', in_array($ids['portal_upload_doc'], $pendingIdsAfter, true) ? 'no' : 'yes', ! in_array($ids['portal_upload_doc'], $pendingIdsAfter, true) ? 'OK' : 'FAIL'];
    }

    $otherUploadTmp = tempnam(sys_get_temp_dir(), 'p3other') . '.txt';
    file_put_contents($otherUploadTmp, 'other case upload');
    $otherFile = new UploadedFile($otherUploadTmp, 'other.txt', 'text/plain', null, true);
    $otherUploadRes = portalCall($http, $portalToken3, 'POST', '/documents', [
        'legal_matter_id' => (string) $otherMatter->id,
        'name' => 'Blocked upload',
    ], ['file' => $otherFile]);
    record($results, 'POST /portal/documents (other case blocked)', $otherUploadRes, [404]);

    record($results, 'POST /portal/auth/logout (uploads)', portalCall($http, $portalToken3, 'POST', '/auth/logout'), [200]);
}

echo "=== APPOINTMENTS (availability + staff) ===\n";
foreach ([
    'appointments.view',
    'appointments.create',
    'appointments.update',
    'appointments.delete',
    'appointments.manage-availability',
] as $perm) {
    Permission::findOrCreate($perm, 'web');
    $admin->givePermissionTo($perm);
}

$dayOffset = 14 + (abs(crc32(uniqid('apptday', true))) % 45);
$tomorrow = now()->addDays($dayOffset)->startOfDay();
$dayOfWeek = $tomorrow->dayOfWeek;
$slotSeed = abs(crc32(uniqid('apptslot', true)));
$availStart = sprintf('%02d:%02d', 8 + ($slotSeed % 2), ($slotSeed >> 3) % 4 * 15);
$availEnd = '18:00';

$availRes = call($http, $token, 'PUT', '/lawyer-availability', [
    'user_id' => $admin->id,
    'slots' => [
        [
            'day_of_week' => $dayOfWeek,
            'start_time' => $availStart,
            'end_time' => $availEnd,
            'slot_duration_minutes' => 30,
            'consultation_types' => ['free_consultation', 'client_meeting'],
            'location' => 'Main office',
            'online_meeting' => true,
        ],
    ],
]);
record($results, 'PUT /lawyer-availability', $availRes, [200]);
$slotCount = LawyerAvailabilitySlot::query()->where('user_id', $admin->id)->count();
$results[] = ['  availability slots saved', $slotCount, ($slotCount >= 1) ? 'OK' : 'FAIL'];

$slotsRes = call($http, $token, 'GET', '/appointments/available-slots?user_id=' . $admin->id . '&date=' . $tomorrow->toDateString());
record($results, 'GET /appointments/available-slots', $slotsRes, [200]);
$slotData = $slotsRes['body']['data'] ?? [];
$results[] = ['  staff slots returned', count($slotData), (count($slotData) >= 2) ? 'OK' : 'FAIL'];

$slotCountAvailable = count($slotData);
$staffIndex = min($slotCountAvailable - 2, max(0, $slotSeed % max(1, $slotCountAvailable - 1)));
$staffSlot = $slotData[$staffIndex] ?? null;
$staffStart = isset($staffSlot['starts_at']) ? \Illuminate\Support\Carbon::parse($staffSlot['starts_at']) : $tomorrow->copy()->setTime(10, 0);
$staffEnd = isset($staffSlot['ends_at']) ? \Illuminate\Support\Carbon::parse($staffSlot['ends_at']) : $staffStart->copy()->addMinutes(30);

$staffApptRes = call($http, $token, 'POST', '/appointments', [
    'user_id' => $admin->id,
    'client_id' => $client->id,
    'legal_matter_id' => $matter->id,
    'consultation_type' => $staffSlot['consultation_type'] ?? 'client_meeting',
    'starts_at' => $staffStart->toDateTimeString(),
    'ends_at' => $staffEnd->toDateTimeString(),
    'location' => 'Conference room',
    'notes' => 'Staff-booked meeting',
    'status' => 'confirmed',
]);
record($results, 'POST /appointments (staff)', $staffApptRes, [201]);
$ids['appointment_staff'] = resId($staffApptRes);
$staffBody = $staffApptRes['body']['data'] ?? $staffApptRes['body'] ?? [];
$results[] = ['  staff appointment confirmed', ($staffBody['status'] ?? '') === 'confirmed' ? 'yes' : 'no', (($staffBody['status'] ?? '') === 'confirmed') ? 'OK' : 'FAIL'];
$results[] = ['  calendar_event linked', ! empty($staffBody['calendar_event_id']) ? 'yes' : 'no', ! empty($staffBody['calendar_event_id']) ? 'OK' : 'FAIL'];

if ($ids['appointment_staff'] ?? null) {
    $calEvent = CalendarEvent::query()->find($staffBody['calendar_event_id'] ?? 0);
    $results[] = ['  calendar event type appointment', ($calEvent?->event_type ?? '') === 'appointment' ? 'yes' : 'no', (($calEvent?->event_type ?? '') === 'appointment') ? 'OK' : 'FAIL'];
    $results[] = ['  calendar reminder_at set', $calEvent?->reminder_at ? 'yes' : 'no', $calEvent?->reminder_at ? 'OK' : 'FAIL'];
    record($results, 'GET /appointments', call($http, $token, 'GET', '/appointments?user_id=' . $admin->id), [200]);
    record($results, 'GET /appointments/{id}', call($http, $token, 'GET', "/appointments/{$ids['appointment_staff']}"), [200]);
}

echo "=== APPOINTMENTS (portal booking) ===\n";
Permission::findOrCreate('portal.appointments.view', 'web');
Permission::findOrCreate('portal.appointments.book', 'web');
$portalUser->givePermissionTo(['portal.appointments.view', 'portal.appointments.book']);

$portalLoginAppt = portalCall($http, '', 'POST', '/auth/login', [
    'email' => $portalUser->email,
    'password' => $portalPassword,
]);
$portalTokenAppt = $portalLoginAppt['body']['token'] ?? null;

if ($portalTokenAppt) {
    record($results, 'GET /portal/lawyers', portalCall($http, $portalTokenAppt, 'GET', '/lawyers'), [200]);

    $portalSlotsRes = portalCall(
        $http,
        $portalTokenAppt,
        'GET',
        '/appointments/available-slots?user_id=' . $admin->id . '&date=' . $tomorrow->toDateString()
    );
    record($results, 'GET /portal/appointments/available-slots', $portalSlotsRes, [200]);

    $portalSlotData = $portalSlotsRes['body']['data'] ?? [];
    $portalSlot = $portalSlotData[0] ?? null;
    $portalStart = isset($portalSlot['starts_at']) ? \Illuminate\Support\Carbon::parse($portalSlot['starts_at']) : $staffEnd->copy()->addMinutes(30);
    $portalEnd = isset($portalSlot['ends_at']) ? \Illuminate\Support\Carbon::parse($portalSlot['ends_at']) : $portalStart->copy()->addMinutes(30);

    $portalBookRes = portalCall($http, $portalTokenAppt, 'POST', '/appointments', [
        'user_id' => $admin->id,
        'legal_matter_id' => $matter->id,
        'consultation_type' => $portalSlot['consultation_type'] ?? 'free_consultation',
        'starts_at' => $portalStart->toDateTimeString(),
        'ends_at' => $portalEnd->toDateTimeString(),
        'notes' => 'Portal booking test',
    ]);
    record($results, 'POST /portal/appointments (book)', $portalBookRes, [201]);
    $ids['appointment_portal'] = resId($portalBookRes);
    $portalBody = $portalBookRes['body']['data'] ?? $portalBookRes['body'] ?? [];
    $results[] = ['  portal booking confirmed', ($portalBody['status'] ?? '') === 'confirmed' ? 'yes' : 'no', (($portalBody['status'] ?? '') === 'confirmed') ? 'OK' : 'FAIL'];

    $lawyerNotif = AppNotification::query()
        ->where('user_id', $admin->id)
        ->where('type', 'appointment_booked')
        ->count();
    $results[] = ['  portal booking notifies lawyer', $lawyerNotif, ($lawyerNotif >= 1) ? 'OK' : 'FAIL'];

    record($results, 'GET /portal/appointments', portalCall($http, $portalTokenAppt, 'GET', '/appointments'), [200]);

    if ($ids['appointment_portal'] ?? null) {
        record($results, 'GET /portal/appointments/{id}', portalCall($http, $portalTokenAppt, 'GET', "/appointments/{$ids['appointment_portal']}"), [200]);
        record($results, 'POST /portal/appointments/{id}/cancel', portalCall($http, $portalTokenAppt, 'POST', "/appointments/{$ids['appointment_portal']}/cancel"), [200]);
    }

    $otherStart = $portalEnd->copy()->addMinutes(30);
    $otherAppt = Appointment::query()->create([
        'organization_id' => $orgId,
        'client_id' => $otherClient->id,
        'user_id' => $admin->id,
        'consultation_type' => 'client_meeting',
        'status' => 'confirmed',
        'starts_at' => $otherStart,
        'ends_at' => $otherStart->copy()->addMinutes(30),
        'payment_status' => 'none',
    ]);
    record($results, 'GET /portal/appointments/{other} blocked', portalCall($http, $portalTokenAppt, 'GET', "/appointments/{$otherAppt->id}"), [404]);

    record($results, 'POST /portal/auth/logout (appointments)', portalCall($http, $portalTokenAppt, 'POST', '/auth/logout'), [200]);
}

echo "=== CRM COMMUNICATION LOGS ===\n";
foreach ([
    'communication-logs.view',
    'communication-logs.create',
    'communication-logs.update',
    'communication-logs.delete',
] as $perm) {
    Permission::findOrCreate($perm, 'web');
    $admin->givePermissionTo($perm);
}

$commBeforeAuto = CommunicationLog::query()
    ->where('client_id', $client->id)
    ->where('channel', 'in_app')
    ->count();

$commRes = call($http, $token, 'POST', '/communication-logs', [
    'client_id' => $client->id,
    'legal_matter_id' => $matter->id,
    'channel' => 'phone',
    'subject' => 'Phase3 comm log test call',
    'body' => 'Discussed case status with client.',
    'occurred_at' => now()->subHour()->toDateTimeString(),
]);
record($results, 'POST /communication-logs', $commRes, [201]);
$ids['communication_log'] = resId($commRes);

if ($ids['communication_log'] ?? null) {
    record($results, 'GET /communication-logs', call($http, $token, 'GET', '/communication-logs?client_id=' . $client->id), [200]);
    record($results, 'GET /communication-logs/{id}', call($http, $token, 'GET', "/communication-logs/{$ids['communication_log']}"), [200]);

    $feedbackRes = call($http, $token, 'PUT', "/communication-logs/{$ids['communication_log']}", [
        'client_feedback' => 'Client satisfied with update.',
        'satisfaction_score' => 4,
    ]);
    record($results, 'PUT /communication-logs/{id} (feedback)', $feedbackRes, [200]);
    $feedbackBody = $feedbackRes['body']['data'] ?? $feedbackRes['body'] ?? [];
    $results[] = [
        '  satisfaction_score saved',
        ($feedbackBody['satisfaction_score'] ?? 0) === 4 ? '4' : (string) ($feedbackBody['satisfaction_score'] ?? 'none'),
        (($feedbackBody['satisfaction_score'] ?? 0) === 4) ? 'OK' : 'FAIL',
    ];

    $results[] = [
        '  in-app messages auto-logged',
        (string) $commBeforeAuto,
        ($commBeforeAuto >= 2) ? 'OK' : 'FAIL',
    ];

    CommunicationLog::query()->create([
        'organization_id' => $orgId,
        'client_id' => $otherClient->id,
        'channel' => 'note',
        'subject' => 'Other client note',
        'body' => 'Should not appear in filtered list.',
        'logged_by_user_id' => $admin->id,
        'occurred_at' => now(),
    ]);
    $scopedListRes = call($http, $token, 'GET', '/communication-logs?client_id=' . $client->id);
    record($results, 'GET /communication-logs?client_id', $scopedListRes, [200]);
    $scopedData = $scopedListRes['body']['data'] ?? [];
    $leaked = false;
    foreach ($scopedData as $row) {
        if (($row['client_id'] ?? 0) !== $client->id) {
            $leaked = true;
            break;
        }
    }
    $results[] = ['  list scoped to client_id', $leaked ? 'leaked' : 'ok', ! $leaked ? 'OK' : 'FAIL'];

    record($results, 'DELETE /communication-logs/{id}', call($http, $token, 'DELETE', "/communication-logs/{$ids['communication_log']}"), [200]);
}

echo "=== CASE EXPENSES ===\n";
foreach ([
    'expenses.view',
    'expenses.view-all',
    'expenses.create',
    'expenses.update',
    'expenses.delete',
] as $perm) {
    Permission::findOrCreate($perm, 'web');
    $admin->givePermissionTo($perm);
}

$expenseRes = call($http, $token, 'POST', '/case-expenses', [
    'legal_matter_id' => $matter->id,
    'category' => 'Filing',
    'description' => 'Court filing fee',
    'amount' => 850,
    'expense_date' => now()->toDateString(),
    'billable' => true,
]);
record($results, 'POST /case-expenses', $expenseRes, [201]);
$ids['case_expense'] = resId($expenseRes);

if ($ids['case_expense'] ?? null) {
    record($results, 'GET /case-expenses', call($http, $token, 'GET', '/case-expenses?legal_matter_id=' . $matter->id), [200]);
    record($results, 'GET /case-expenses/{id}', call($http, $token, 'GET', "/case-expenses/{$ids['case_expense']}"), [200]);

    $expInvoiceRes = call($http, $token, 'POST', '/invoices/generate-from-expenses', [
        'client_id' => $client->id,
        'legal_matter_id' => $matter->id,
        'expense_ids' => [$ids['case_expense']],
    ]);
    record($results, 'POST /invoices/generate-from-expenses', $expInvoiceRes, [201]);
    $expInvoiceBody = $expInvoiceRes['body']['data'] ?? $expInvoiceRes['body'] ?? [];
    $expLineItems = $expInvoiceBody['line_items'] ?? [];
    $results[] = [
        '  expense invoice line_items == 1',
        count($expLineItems),
        (count($expLineItems) === 1) ? 'OK' : 'FAIL',
    ];
    $results[] = [
        '  expense invoice total == 850',
        (string) ($expInvoiceBody['total_amount'] ?? 0),
        ((float) ($expInvoiceBody['total_amount'] ?? 0) === 850.0) ? 'OK' : 'FAIL',
    ];
    $ids['expense_invoice'] = resId($expInvoiceRes);

    if ($ids['expense_invoice'] ?? null) {
        record($results, 'DELETE /invoices/{expense}', call($http, $token, 'DELETE', "/invoices/{$ids['expense_invoice']}"), [200]);
    }
}

echo "=== BILLING TYPES ===\n";
$billingRes = call($http, $token, 'PATCH', "/cases/{$matter->id}", [
    'billing_type' => 'retainer',
    'billing_rate' => 2000,
    'retainer_minimum_amount' => 15000,
]);
record($results, 'PATCH /cases/{id} (billing)', $billingRes, [200]);
$billingBody = $billingRes['body']['data'] ?? $billingRes['body'] ?? [];
$results[] = [
    '  billing_type == retainer',
    $billingBody['billing_type'] ?? 'none',
    (($billingBody['billing_type'] ?? '') === 'retainer') ? 'OK' : 'FAIL',
];
$results[] = [
    '  retainer_minimum saved',
    (string) ($billingBody['retainer_minimum_amount'] ?? 0),
    ((float) ($billingBody['retainer_minimum_amount'] ?? 0) === 15000.0) ? 'OK' : 'FAIL',
];

echo "=== PORTAL INTAKE FORMS ===\n";
Permission::findOrCreate('portal.intake.view', 'web');
Permission::findOrCreate('portal.intake.submit', 'web');
$portalUser->givePermissionTo(['portal.intake.view', 'portal.intake.submit']);

$intakeForm = IntakeForm::query()->create([
    'organization_id' => $orgId,
    'created_by' => $admin->id,
    'name' => 'Phase3 Portal Intake',
    'description' => 'Client-facing intake for verification.',
    'status' => 'published',
    'fields' => [
        ['name' => 'matter_summary', 'label' => 'Brief summary', 'type' => 'long_text', 'required' => true],
        ['name' => 'urgent', 'label' => 'Urgent matter?', 'type' => 'checkbox', 'required' => false],
    ],
]);

$portalLoginIntake = portalCall($http, '', 'POST', '/auth/login', [
    'email' => $portalUser->email,
    'password' => $portalPassword,
]);
$portalTokenIntake = $portalLoginIntake['body']['token'] ?? null;

if ($portalTokenIntake) {
    record($results, 'GET /portal/intake-forms', portalCall($http, $portalTokenIntake, 'GET', '/intake-forms'), [200]);
    $formsList = portalCall($http, $portalTokenIntake, 'GET', '/intake-forms');
    $formIds = array_column($formsList['body']['data'] ?? [], 'id');
    $results[] = [
        '  portal lists published form',
        in_array($intakeForm->id, $formIds, true) ? 'yes' : 'no',
        in_array($intakeForm->id, $formIds, true) ? 'OK' : 'FAIL',
    ];

    record($results, 'GET /portal/intake-forms/{id}', portalCall($http, $portalTokenIntake, 'GET', "/intake-forms/{$intakeForm->id}"), [200]);

    $submitRes = portalCall($http, $portalTokenIntake, 'POST', "/intake-forms/{$intakeForm->id}/submit", [
        'data' => ['matter_summary' => 'Need help with contract dispute.', 'urgent' => true],
    ]);
    record($results, 'POST /portal/intake-forms/{id}/submit', $submitRes, [201]);
    $submissionId = resId($submitRes);
    if ($submissionId) {
        $submission = IntakeSubmission::query()->find($submissionId);
        $results[] = [
            '  portal submission linked to client',
            $submission?->client_id === $client->id ? 'yes' : 'no',
            ($submission?->client_id === $client->id) ? 'OK' : 'FAIL',
        ];
        $staffIntakeNotif = AppNotification::query()
            ->where('user_id', $admin->id)
            ->where('type', 'intake_submitted')
            ->count();
        $results[] = ['  portal intake notifies staff', $staffIntakeNotif, ($staffIntakeNotif >= 1) ? 'OK' : 'FAIL'];
    }

    $draftForm = IntakeForm::query()->create([
        'organization_id' => $orgId,
        'created_by' => $admin->id,
        'name' => 'Draft form hidden',
        'status' => 'draft',
        'fields' => [['name' => 'hidden', 'label' => 'Hidden', 'type' => 'text']],
    ]);
    record($results, 'GET /portal/intake-forms/{draft} blocked', portalCall($http, $portalTokenIntake, 'GET', "/intake-forms/{$draftForm->id}"), [404]);

    record($results, 'POST /portal/auth/logout (intake)', portalCall($http, $portalTokenIntake, 'POST', '/auth/logout'), [200]);
}

echo "=== EMAIL NOTIFICATIONS (messages) ===\n";
Notification::fake();

if ($ids['message_thread'] ?? null) {
    $emailMsgRes = call($http, $token, 'POST', "/message-threads/{$ids['message_thread']}/messages", [
        'body' => 'Staff follow-up triggers email notification.',
    ]);
    record($results, 'POST /message-threads/{id}/messages (email)', $emailMsgRes, [201]);
    $emailNotifs = Notification::sent($portalUser, \App\Notifications\NewMessageNotification::class);
    $results[] = [
        '  message email notification queued',
        count($emailNotifs),
        (count($emailNotifs) >= 1) ? 'OK' : 'FAIL',
    ];
}

echo "=== REPORTING ===\n";
Permission::findOrCreate('reports.view', 'web');
$admin->givePermissionTo('reports.view');

$summaryRes = call($http, $token, 'GET', '/reports/summary');
record($results, 'GET /reports/summary', $summaryRes, [200]);
$summaryBody = $summaryRes['body'] ?? [];
$results[] = [
    '  summary has cases.by_status',
    isset($summaryBody['cases']['by_status']) ? 'yes' : 'no',
    isset($summaryBody['cases']['by_status']) ? 'OK' : 'FAIL',
];
$results[] = [
    '  summary has revenue totals',
    isset($summaryBody['revenue']['total_paid'], $summaryBody['revenue']['unpaid_total']) ? 'yes' : 'no',
    isset($summaryBody['revenue']['total_paid'], $summaryBody['revenue']['unpaid_total']) ? 'OK' : 'FAIL',
];
$results[] = [
    '  summary has time_by_lawyer',
    isset($summaryBody['time_by_lawyer']) ? 'yes' : 'no',
    isset($summaryBody['time_by_lawyer']) ? 'OK' : 'FAIL',
];

$filteredRes = call($http, $token, 'GET', '/reports/summary?from_date=' . now()->subDays(30)->toDateString() . '&to_date=' . now()->toDateString());
record($results, 'GET /reports/summary (date range)', $filteredRes, [200]);

$exportCsvRes = call($http, $token, 'GET', '/reports/export.csv');
record($results, 'GET /reports/export.csv', $exportCsvRes, [200]);

$exportDatasetRes = call($http, $token, 'GET', '/reports/export.csv?dataset=cases');
record($results, 'GET /reports/export.csv?dataset=cases', $exportDatasetRes, [200]);

$deniedRes = call($http, '', 'GET', '/reports/summary');
record($results, 'GET /reports/summary (unauthenticated)', $deniedRes, [401]);

echo "=== SLICE 7: REVERB / BROADCAST MESSAGING ===\n";
$messageSentEvent = __DIR__ . '/../app/Events/MessageSent.php';
$channelsFile = __DIR__ . '/../routes/channels.php';
$messagePanel = __DIR__ . '/../frontend/src/components/messages/MessageThreadPanel.vue';
$echoLib = __DIR__ . '/../frontend/src/lib/echo.ts';
$messagePanelSource = file_exists($messagePanel) ? (string) file_get_contents($messagePanel) : '';
$echoSource = file_exists($echoLib) ? (string) file_get_contents($echoLib) : '';
$threadController = file_exists(__DIR__ . '/../app/Http/Controllers/Api/V1/MessageThreadController.php')
    ? (string) file_get_contents(__DIR__ . '/../app/Http/Controllers/Api/V1/MessageThreadController.php')
    : '';

$broadcastChecks = [
    'MessageSent event exists' => file_exists($messageSentEvent),
    'MessageSent implements ShouldBroadcast' => str_contains((string) file_get_contents($messageSentEvent), 'ShouldBroadcast'),
    'routes/channels.php message-thread channel' => str_contains((string) file_get_contents($channelsFile), 'message-thread'),
    'MessageThreadController broadcasts MessageSent' => str_contains($threadController, 'broadcast(new MessageSent'),
    'MessageThreadPanel Echo listener' => str_contains($messagePanelSource, 'message.sent'),
    'frontend lib/echo.ts exists' => file_exists($echoLib),
    'echo.ts reverb broadcaster' => str_contains($echoSource, "broadcaster: 'reverb'"),
];
foreach ($broadcastChecks as $label => $ok) {
    $results[] = [$label, $ok ? 'yes' : 'no', $ok ? 'OK' : 'FAIL'];
}

if ($ids['message_thread'] ?? null) {
    Event::fake([MessageSent::class]);
    $broadcastMsgRes = call($http, $token, 'POST', "/message-threads/{$ids['message_thread']}/messages", [
        'body' => 'Broadcast verification message.',
    ]);
    record($results, 'POST /message-threads/{id}/messages (broadcast)', $broadcastMsgRes, [201]);
    $dispatched = Event::dispatched(MessageSent::class);
    $results[] = [
        '  MessageSent event dispatched',
        count($dispatched),
        (count($dispatched) >= 1) ? 'OK' : 'FAIL',
    ];
    Event::clearResolvedInstances();
}

echo "\n==================== PHASE 3 RESULTS ====================\n";
$pass = 0;
$fail = 0;
foreach ($results as [$label, $status, $verdict]) {
    printf("%-45s %-8s %s\n", $label, (string) $status, $verdict);
    $verdict === 'OK' ? $pass++ : $fail++;
}
echo "----------------------------------------------------------------------\n";
echo "PASS: {$pass}  FAIL: {$fail}\n";

exit($fail > 0 ? 1 : 0);
