<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use App\Models\Client;
use App\Models\LegalMatter;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
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

Permission::findOrCreate('audit.view', 'web');
$admin->givePermissionTo('audit.view');

$token = $admin->createToken('audit-verify')->plainTextToken;
$organizationId = $admin->organization_id;
$results = [];

function call(HttpKernel $http, string $token, string $method, string $uri, array $payload = []): array
{
    $server = [
        'HTTP_Accept' => 'application/json',
    ];
    if ($token !== '') {
        $server['HTTP_Authorization'] = 'Bearer '.$token;
    }

    $request = Request::create('/api/v1'.$uri, $method, $payload, [], [], $server);
    if ($payload !== [] && in_array($method, ['POST', 'PUT', 'PATCH'], true)) {
        $request = Request::create('/api/v1'.$uri, $method, [], [], [], array_merge($server, [
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

function record(array &$results, string $label, array $res, array $okStatuses = [200]): void
{
    $ok = in_array($res['status'], $okStatuses, true);
    $results[] = [$label, (string) $res['status'], $ok ? 'OK' : 'FAIL'];
    if (! $ok) {
        $msg = is_array($res['body']) ? json_encode($res['body']) : (string) $res['body'];
        fwrite(STDERR, "  [FAIL] {$label} => {$res['status']} :: ".substr($msg, 0, 300)."\n");
    }
}

$unique = 'AuditVerify'.substr(uniqid(), -6);

$client = Client::query()->create([
    'organization_id' => $organizationId,
    'type' => 'individual',
    'name' => "{$unique} Client",
    'email' => strtolower($unique).'@example.test',
    'status' => 'active',
    'created_by' => $admin->id,
]);

$matter = LegalMatter::query()->create([
    'organization_id' => $organizationId,
    'client_id' => $client->id,
    'title' => "{$unique} Matter",
    'matter_number' => 'AUD-'.substr($unique, -4),
    'status' => 'new',
    'priority' => 'normal',
    'created_by' => $admin->id,
]);

activity('case')
    ->performedOn($matter)
    ->causedBy($admin)
    ->withProperties([
        'organization_id' => $organizationId,
        'event' => 'created',
        'ip' => '127.0.0.1',
    ])
    ->log('Case created for audit verification');

echo "=== AUDIT LOG API ===\n";

$listRes = call($http, $token, 'GET', '/audit-logs');
record($results, 'GET /audit-logs', $listRes, [200]);

$rows = $listRes['body']['data'] ?? [];
$results[] = [
    '  paginated data present',
    is_array($rows) ? 'yes' : 'no',
    is_array($rows) ? 'OK' : 'FAIL',
];

$matched = collect($rows)->first(fn ($row) => ($row['subject_id'] ?? null) === $matter->id);
$results[] = [
    '  fixture activity listed',
    $matched ? 'yes' : 'no',
    $matched ? 'OK' : 'FAIL',
];
$results[] = [
    '  row has module/action/user',
    ($matched && isset($matched['module'], $matched['action'], $matched['user']['id'])) ? 'yes' : 'no',
    ($matched && isset($matched['module'], $matched['action'], $matched['user']['id'])) ? 'OK' : 'FAIL',
];

$userFilterRes = call($http, $token, 'GET', '/audit-logs?user_id='.$admin->id);
record($results, 'GET /audit-logs?user_id', $userFilterRes, [200]);
$userRows = $userFilterRes['body']['data'] ?? [];
$results[] = [
    '  user filter returns rows',
    count($userRows) >= 1 ? 'yes' : 'no',
    count($userRows) >= 1 ? 'OK' : 'FAIL',
];

$subjectFilterRes = call($http, $token, 'GET', '/audit-logs?subject_type=case');
record($results, 'GET /audit-logs?subject_type=case', $subjectFilterRes, [200]);

$actionFilterRes = call($http, $token, 'GET', '/audit-logs?action=created');
record($results, 'GET /audit-logs?action=created', $actionFilterRes, [200]);
$actionRows = $actionFilterRes['body']['data'] ?? [];
$results[] = [
    '  action filter matches fixture',
    collect($actionRows)->contains(fn ($row) => ($row['subject_id'] ?? null) === $matter->id) ? 'yes' : 'no',
    collect($actionRows)->contains(fn ($row) => ($row['subject_id'] ?? null) === $matter->id) ? 'OK' : 'FAIL',
];

$fromDate = now()->subDay()->toDateString();
$toDate = now()->addDay()->toDateString();
$dateFilterRes = call($http, $token, 'GET', "/audit-logs?from_date={$fromDate}&to_date={$toDate}");
record($results, 'GET /audit-logs?date range', $dateFilterRes, [200]);

$deniedUser = User::query()->create([
    'organization_id' => $organizationId,
    'name' => 'Audit Denied User',
    'email' => 'audit-denied-'.uniqid().'@example.test',
    'password' => Hash::make('AuditDenied123!'),
    'is_active' => true,
]);
$deniedUser->assignRole('Lawyer');
$deniedToken = $deniedUser->createToken('audit-denied')->plainTextToken;
$deniedRes = call($http, $deniedToken, 'GET', '/audit-logs');
record($results, 'GET /audit-logs (no permission)', $deniedRes, [403]);

$unauthRes = call($http, '', 'GET', '/audit-logs');
record($results, 'GET /audit-logs (unauthenticated)', $unauthRes, [401]);

echo "=== AUDIT FRONTEND WIRING ===\n";

$auditView = __DIR__.'/../../frontend/src/views/AuditView.vue';
$settingsView = __DIR__.'/../../frontend/src/views/SettingsView.vue';
$router = __DIR__.'/../../frontend/src/router/index.ts';
$apiLib = __DIR__.'/../../frontend/src/lib/api.ts';
$auditSource = file_exists($auditView) ? (string) file_get_contents($auditView) : '';
$settingsSource = file_exists($settingsView) ? (string) file_get_contents($settingsView) : '';
$routerSource = file_exists($router) ? (string) file_get_contents($router) : '';
$apiSource = file_exists($apiLib) ? (string) file_get_contents($apiLib) : '';

$frontendChecks = [
    'AuditView.vue exists' => file_exists($auditView),
    'AuditView calls auditApi' => str_contains($auditSource, 'auditApi'),
    'router /audit route' => str_contains($routerSource, "path: 'audit'"),
    'router audit.view permission' => str_contains($routerSource, 'audit.view'),
    'SettingsView audit tab/link' => str_contains($settingsSource, '/audit') || str_contains($settingsSource, 'tab=audit'),
    'api.ts auditApi export' => str_contains($apiSource, 'export const auditApi'),
];
foreach ($frontendChecks as $label => $ok) {
    $results[] = [$label, $ok ? 'yes' : 'no', $ok ? 'OK' : 'FAIL'];
}

echo "\n==================== AUDIT RESULTS ====================\n";
$pass = 0;
$fail = 0;
foreach ($results as [$label, $status, $verdict]) {
    printf("%-45s %-8s %s\n", $label, (string) $status, $verdict);
    $verdict === 'OK' ? $pass++ : $fail++;
}
echo "----------------------------------------------------------------------\n";
echo "PASS: {$pass}  FAIL: {$fail}\n";

exit($fail > 0 ? 1 : 0);
