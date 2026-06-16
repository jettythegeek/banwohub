<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use App\Models\Client;
use App\Models\Invoice;
use App\Models\LegalMatter;
use App\Models\User;
use App\Services\NumberSequenceService;
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Http\Request;

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

$token = $admin->createToken('numbering-verify')->plainTextToken;
$orgId = (int) $admin->organization_id;
$numbers = app(NumberSequenceService::class);

$results = [];

function call(HttpKernel $http, string $token, string $method, string $uri, array $payload = []): array
{
    $server = [
        'HTTP_Authorization' => 'Bearer ' . $token,
        'HTTP_Accept' => 'application/json',
    ];

    $request = Request::create('/api/v1' . $uri, $method, [], [], [], array_merge($server, [
        'CONTENT_TYPE' => 'application/json',
    ]), json_encode($payload));

    $response = $http->handle($request);
    $content = method_exists($response, 'getContent') ? $response->getContent() : '';
    $body = is_string($content) && $content !== '' ? json_decode($content, true) : null;

    return ['status' => $response->getStatusCode(), 'body' => $body];
}

function record(array &$results, string $label, bool $ok): void
{
    $results[] = [$label, $ok ? 'OK' : 'FAIL'];
    if (! $ok) {
        fwrite(STDERR, "FAIL: {$label}\n");
    }
}

// Service unit checks
$caseNum = $numbers->nextCaseNumber($orgId);
record($results, 'NumberSequenceService case format CASE-####', (bool) preg_match('/^CASE-\d{4}$/', $caseNum));

$clientNum = $numbers->nextClientNumber($orgId);
record($results, 'NumberSequenceService client format CL-######', (bool) preg_match('/^CL-\d{6}$/', $clientNum));

$invoiceNum = $numbers->nextInvoiceNumber($orgId);
$ym = date('Ym');
record($results, 'NumberSequenceService invoice format INV-YYYYMM-######', (bool) preg_match('/^INV-'.$ym.'-\d{6}$/', $invoiceNum));

// API create client
$clientRes = call($http, $token, 'POST', '/clients', [
    'name' => 'Numbering Verify Client '.uniqid(),
    'type' => 'individual',
]);
$clientBody = $clientRes['body']['data'] ?? $clientRes['body'] ?? [];
$clientNumber = $clientBody['client_number'] ?? null;
record($results, 'POST /clients returns client_number', $clientRes['status'] === 201 && is_string($clientNumber) && preg_match('/^CL-\d{6}$/', $clientNumber));
$clientId = (int) ($clientBody['id'] ?? 0);

// API create case
if ($clientId > 0) {
    $caseRes = call($http, $token, 'POST', '/cases', [
        'client_id' => $clientId,
        'title' => 'Numbering Verify Case '.uniqid(),
        'stage' => 'lead',
        'matter_stage' => 'intake',
        'priority' => 'normal',
    ]);
    $caseBody = $caseRes['body']['data'] ?? $caseRes['body'] ?? [];
    $matterNumber = $caseBody['matter_number'] ?? null;
    record($results, 'POST /cases auto-assigns CASE-####', $caseRes['status'] === 201 && is_string($matterNumber) && preg_match('/^CASE-\d{4}$/', $matterNumber));
    record($results, 'POST /cases returns stage + matter_stage', ($caseBody['stage'] ?? null) === 'lead' && ($caseBody['matter_stage'] ?? null) === 'intake');
    $caseId = (int) ($caseBody['id'] ?? 0);
} else {
    record($results, 'POST /cases auto-assigns CASE-####', false);
    record($results, 'POST /cases returns stage + matter_stage', false);
    $caseId = 0;
}

// API create invoice
if ($clientId > 0) {
    $invRes = call($http, $token, 'POST', '/invoices', [
        'client_id' => $clientId,
        'legal_matter_id' => $caseId > 0 ? $caseId : null,
        'line_items' => [
            ['description' => 'Verify line', 'quantity' => 1, 'unit_price' => 100],
        ],
    ]);
    $invBody = $invRes['body']['data'] ?? $invRes['body'] ?? [];
    $invNumber = $invBody['invoice_number'] ?? null;
    record($results, 'POST /invoices auto-assigns INV-YYYYMM-######', $invRes['status'] === 201 && is_string($invNumber) && preg_match('/^INV-'.$ym.'-\d{6}$/', $invNumber));
} else {
    record($results, 'POST /invoices auto-assigns INV-YYYYMM-######', false);
}

// Migration columns
record($results, 'clients.client_number column exists', \Illuminate\Support\Facades\Schema::hasColumn('clients', 'client_number'));
record($results, 'legal_matters.stage column exists', \Illuminate\Support\Facades\Schema::hasColumn('legal_matters', 'stage'));
record($results, 'legal_matters.matter_stage column exists', \Illuminate\Support\Facades\Schema::hasColumn('legal_matters', 'matter_stage'));

// Frontend wiring
$frontendRoot = dirname(__DIR__).'/frontend/src';
record($results, 'CaseFormView stage + matter_stage fields', is_file($frontendRoot.'/views/cases/CaseFormView.vue') && str_contains(file_get_contents($frontendRoot.'/views/cases/CaseFormView.vue'), 'matter_stage'));
record($results, 'ClientsListView shows client_number', is_file($frontendRoot.'/views/clients/ClientsListView.vue') && str_contains(file_get_contents($frontendRoot.'/views/clients/ClientsListView.vue'), 'client_number'));

echo "\n=== verify-numbering.php ===\n";
foreach ($results as [$label, $status]) {
    echo sprintf("%-55s %s\n", $label, $status);
}

$failed = count(array_filter($results, fn ($r) => $r[1] === 'FAIL'));
exit($failed > 0 ? 1 : 0);
