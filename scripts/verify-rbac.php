<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use App\Models\User;
use App\Services\TotpService;
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(ConsoleKernel::class)->bootstrap();

/** @var HttpKernel $http */
$http = $app->make(HttpKernel::class);

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

function record(array &$results, string $label, bool $ok): void
{
    $results[] = [$label, $ok ? 'OK' : 'FAIL'];
}

echo "=== RBAC MATRIX ===\n";

$checks = [
    ['Lawyer', 'conflict-checks.update', false],
    ['Paralegal', 'conflict-checks.update', false],
    ['Partner', 'conflict-checks.update', true],
    ['Firm Admin', 'conflict-checks.update', true],
    ['Lawyer', 'intake-submissions.convert', false],
    ['Partner', 'intake-submissions.convert', true],
    ['Secretary', 'cases.update', false],
    ['Paralegal', 'cases.update', true],
    ['Client', 'case-notes.view', false],
];

$fail = 0;
foreach ($checks as [$roleName, $permission, $expected]) {
    $role = Role::where('name', $roleName)->first();
    $has = $role?->hasPermissionTo($permission) ?? false;
    $ok = $has === $expected;
    printf("%-12s %-30s expected=%-5s actual=%-5s %s\n",
        $roleName, $permission,
        $expected ? 'yes' : 'no',
        $has ? 'yes' : 'no',
        $ok ? 'OK' : 'FAIL');
    record($results, "RBAC {$roleName} {$permission}", $ok);
    if (! $ok) {
        $fail++;
    }
}

echo "\n=== TWO-FACTOR API ===\n";

$totp = app(TotpService::class);
$password = 'TwoFactorVerify123!';
$twoFaUser = User::query()->create([
    'organization_id' => User::query()->where('email', getenv('SEED_ADMIN_EMAIL') ?: 'admin@banwolaw.com')->value('organization_id'),
    'name' => '2FA Verify User',
    'email' => 'twofa-verify-'.uniqid().'@example.test',
    'password' => Hash::make($password),
    'is_active' => true,
]);
$twoFaUser->assignRole('Lawyer');
$userToken = $twoFaUser->createToken('2fa-verify')->plainTextToken;

$statusRes = call($http, $userToken, 'GET', '/auth/two-factor/status');
record($results, 'GET /auth/two-factor/status', $statusRes['status'] === 200);
record($results, '  status enabled=false initially', ($statusRes['body']['enabled'] ?? true) === false);

$enableRes = call($http, $userToken, 'POST', '/auth/two-factor/enable');
record($results, 'POST /auth/two-factor/enable', $enableRes['status'] === 200);
$secret = $enableRes['body']['secret'] ?? null;
$otpauth = $enableRes['body']['otpauth_url'] ?? null;
record($results, '  returns secret + otpauth_url', is_string($secret) && is_string($otpauth));

$twoFaUser->refresh();
$code = $totp->currentCode($twoFaUser->two_factor_secret);
$confirmRes = call($http, $userToken, 'POST', '/auth/two-factor/confirm', ['code' => $code]);
record($results, 'POST /auth/two-factor/confirm', $confirmRes['status'] === 200);
record($results, '  confirm enables 2FA', ($confirmRes['body']['enabled'] ?? false) === true);

$twoFaUser->refresh();
record($results, '  user hasTwoFactorEnabled', $twoFaUser->hasTwoFactorEnabled());

$loginRes = call($http, '', 'POST', '/auth/login', [
    'email' => $twoFaUser->email,
    'password' => $password,
]);
record($results, 'POST /auth/login challenges 2FA', $loginRes['status'] === 200);
$challengeToken = $loginRes['body']['challenge_token'] ?? null;
record($results, '  login returns challenge_token', is_string($challengeToken) && ($loginRes['body']['two_factor_required'] ?? false) === true);
record($results, '  login does not return token yet', ! isset($loginRes['body']['token']));

$badVerifyRes = call($http, '', 'POST', '/auth/two-factor/verify', [
    'challenge_token' => $challengeToken,
    'code' => '000000',
]);
record($results, 'POST /auth/two-factor/verify rejects bad code', $badVerifyRes['status'] === 422);

$goodCode = $totp->currentCode($twoFaUser->two_factor_secret);
$verifyRes = call($http, '', 'POST', '/auth/two-factor/verify', [
    'challenge_token' => $challengeToken,
    'code' => $goodCode,
]);
record($results, 'POST /auth/two-factor/verify accepts valid code', $verifyRes['status'] === 200);
record($results, '  verify returns bearer token', is_string($verifyRes['body']['token'] ?? null));

$disableCode = $totp->currentCode($twoFaUser->two_factor_secret);
$disableRes = call($http, $userToken, 'POST', '/auth/two-factor/disable', [
    'password' => $password,
    'code' => $disableCode,
]);
record($results, 'POST /auth/two-factor/disable', $disableRes['status'] === 200);
record($results, '  disable turns off 2FA', ($disableRes['body']['enabled'] ?? true) === false);

$otherUser = User::query()->create([
    'organization_id' => $twoFaUser->organization_id,
    'name' => '2FA Other User',
    'email' => 'twofa-other-'.uniqid().'@example.test',
    'password' => Hash::make($password),
    'is_active' => true,
]);
$otherUser->assignRole('Paralegal');
$otherToken = $otherUser->createToken('2fa-other')->plainTextToken;
$otherEnableRes = call($http, $otherToken, 'POST', '/auth/two-factor/enable');
record($results, 'User manages own 2FA (enable)', $otherEnableRes['status'] === 200);

echo "\n=== TWO-FACTOR FRONTEND WIRING ===\n";

$settingsView = __DIR__.'/../frontend/src/views/SettingsView.vue';
$loginView = __DIR__.'/../frontend/src/views/LoginView.vue';
$panel = __DIR__.'/../frontend/src/components/settings/TwoFactorSecurityPanel.vue';
$apiLib = __DIR__.'/../frontend/src/lib/api.ts';
$authStore = __DIR__.'/../frontend/src/stores/auth.ts';
$settingsSource = file_exists($settingsView) ? (string) file_get_contents($settingsView) : '';
$loginSource = file_exists($loginView) ? (string) file_get_contents($loginView) : '';
$panelSource = file_exists($panel) ? (string) file_get_contents($panel) : '';
$apiSource = file_exists($apiLib) ? (string) file_get_contents($apiLib) : '';
$authSource = file_exists($authStore) ? (string) file_get_contents($authStore) : '';

$frontendChecks = [
    'TwoFactorSecurityPanel.vue exists' => file_exists($panel),
    'SettingsView security tab' => str_contains($settingsSource, 'tab=security') && str_contains($settingsSource, 'TwoFactorSecurityPanel'),
    'LoginView 2FA challenge' => str_contains($loginSource, 'challengeToken') && str_contains($loginSource, 'verifyTwoFactor'),
    'api.ts twoFactorApi export' => str_contains($apiSource, 'export const twoFactorApi'),
    'auth store verifyTwoFactor' => str_contains($authSource, 'verifyTwoFactor'),
    'panel uses twoFactorApi' => str_contains($panelSource, 'twoFactorApi'),
];
foreach ($frontendChecks as $label => $ok) {
    record($results, $label, $ok);
}

$migration = __DIR__.'/../database/migrations/2026_06_06_110000_add_two_factor_to_users_table.php';
$migrationSource = file_exists($migration) ? (string) file_get_contents($migration) : '';
record($results, 'users two_factor migration', str_contains($migrationSource, 'two_factor_secret')
    && str_contains($migrationSource, 'two_factor_enabled')
    && str_contains($migrationSource, 'two_factor_confirmed_at'));

echo "\n==================== RBAC + 2FA RESULTS ====================\n";
$pass = 0;
foreach ($results as [$label, $verdict]) {
    printf("%-45s %s\n", $label, $verdict);
    $verdict === 'OK' ? $pass++ : $fail++;
}
echo "----------------------------------------------------------------------\n";
echo "PASS: {$pass}  FAIL: {$fail}\n";

exit($fail > 0 ? 1 : 0);
