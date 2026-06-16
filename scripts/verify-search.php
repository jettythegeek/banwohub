<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use App\Models\CaseNote;
use App\Models\Client;
use App\Models\LegalDocument;
use App\Models\LegalMatter;
use App\Models\Message;
use App\Models\MessageThread;
use App\Models\User;
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

$token = $admin->createToken('search-verify')->plainTextToken;
$organizationId = $admin->organization_id;

$results = [];

function call(HttpKernel $http, string $token, string $method, string $uri): array
{
    $server = [
        'HTTP_Accept' => 'application/json',
        'HTTP_Authorization' => 'Bearer '.$token,
    ];

    $request = Request::create('/api/v1'.$uri, $method, [], [], [], $server);
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
}

$unique = 'SearchVerify'.substr(uniqid(), -6);

$client = Client::query()->create([
    'organization_id' => $organizationId,
    'name' => "{$unique} Client Corp",
    'email' => strtolower($unique).'@example.test',
    'status' => 'active',
]);

$matter = LegalMatter::query()->create([
    'organization_id' => $organizationId,
    'client_id' => $client->id,
    'title' => "{$unique} Litigation Matter",
    'matter_number' => 'SRCH-'.substr($unique, -4),
    'status' => 'open',
    'practice_area' => 'litigation',
]);

$document = LegalDocument::query()->create([
    'organization_id' => $organizationId,
    'legal_matter_id' => $matter->id,
    'uploaded_by' => $admin->id,
    'document_type' => 'case_document',
    'name' => "{$unique} Motion Draft",
    'original_filename' => "{$unique}.html",
    'path' => "organizations/{$organizationId}/cases/{$matter->id}/documents/{$unique}.html",
    'disk' => 'local',
    'mime_type' => 'text/html',
    'size' => 128,
    'content_html' => "<p>{$unique} searchable content</p>",
]);

$note = CaseNote::query()->create([
    'organization_id' => $organizationId,
    'legal_matter_id' => $matter->id,
    'author_id' => $admin->id,
    'note_type' => 'strategy_note',
    'visibility' => 'assigned_team',
    'title' => "{$unique} Strategy memo",
    'body' => "Internal {$unique} strategy discussion notes.",
]);

$thread = MessageThread::query()->create([
    'organization_id' => $organizationId,
    'client_id' => $client->id,
    'legal_matter_id' => $matter->id,
    'created_by' => $admin->id,
    'subject' => "{$unique} client thread",
    'last_message_at' => now(),
]);

$message = Message::query()->create([
    'message_thread_id' => $thread->id,
    'sender_user_id' => $admin->id,
    'body' => "Please review the {$unique} filing draft before Friday.",
]);

echo "=== GLOBAL SEARCH MVP ===\n";

$shortRes = call($http, $token, 'GET', '/search?q=a');
record($results, 'GET /search?q=a (too short)', $shortRes, [422]);

$searchRes = call($http, $token, 'GET', '/search?q='.urlencode($unique));
record($results, 'GET /search?q=term', $searchRes, [200]);

$body = $searchRes['body'] ?? [];
$caseHits = $body['results']['cases'] ?? [];
$clientHits = $body['results']['clients'] ?? [];
$docHits = $body['results']['documents'] ?? [];
$noteHits = $body['results']['notes'] ?? [];
$messageHits = $body['results']['messages'] ?? [];
$sections = $body['sections'] ?? [];

$results[] = [
    '  results include cases key',
    isset($body['results']['cases']) ? 'yes' : 'no',
    isset($body['results']['cases']) ? 'OK' : 'FAIL',
];
$results[] = [
    '  results include clients key',
    isset($body['results']['clients']) ? 'yes' : 'no',
    isset($body['results']['clients']) ? 'OK' : 'FAIL',
];
$results[] = [
    '  results include documents key',
    isset($body['results']['documents']) ? 'yes' : 'no',
    isset($body['results']['documents']) ? 'OK' : 'FAIL',
];
$results[] = [
    '  results include notes key',
    isset($body['results']['notes']) ? 'yes' : 'no',
    isset($body['results']['notes']) ? 'OK' : 'FAIL',
];
$results[] = [
    '  results include messages key',
    isset($body['results']['messages']) ? 'yes' : 'no',
    isset($body['results']['messages']) ? 'OK' : 'FAIL',
];
$results[] = [
    '  structured sections present',
    is_array($sections) && count($sections) >= 5 ? 'yes' : 'no',
    is_array($sections) && count($sections) >= 5 ? 'OK' : 'FAIL',
];
$results[] = [
    '  case match found',
    collect($caseHits)->contains(fn ($row) => ($row['id'] ?? null) === $matter->id) ? 'yes' : 'no',
    collect($caseHits)->contains(fn ($row) => ($row['id'] ?? null) === $matter->id) ? 'OK' : 'FAIL',
];
$results[] = [
    '  client match found',
    collect($clientHits)->contains(fn ($row) => ($row['id'] ?? null) === $client->id) ? 'yes' : 'no',
    collect($clientHits)->contains(fn ($row) => ($row['id'] ?? null) === $client->id) ? 'OK' : 'FAIL',
];
$results[] = [
    '  document match found',
    collect($docHits)->contains(fn ($row) => ($row['id'] ?? null) === $document->id) ? 'yes' : 'no',
    collect($docHits)->contains(fn ($row) => ($row['id'] ?? null) === $document->id) ? 'OK' : 'FAIL',
];
$results[] = [
    '  note match found',
    collect($noteHits)->contains(fn ($row) => ($row['id'] ?? null) === $note->id) ? 'yes' : 'no',
    collect($noteHits)->contains(fn ($row) => ($row['id'] ?? null) === $note->id) ? 'OK' : 'FAIL',
];
$results[] = [
    '  message match found',
    collect($messageHits)->contains(fn ($row) => ($row['id'] ?? null) === $message->id) ? 'yes' : 'no',
    collect($messageHits)->contains(fn ($row) => ($row['id'] ?? null) === $message->id) ? 'OK' : 'FAIL',
];
$results[] = [
    '  total count present',
    isset($body['total']) ? (string) $body['total'] : 'missing',
    isset($body['total']) && (int) $body['total'] >= 5 ? 'OK' : 'FAIL',
];

$searchView = __DIR__ . '/../frontend/src/views/SearchView.vue';
$topbar = __DIR__ . '/../frontend/src/components/layout/Topbar.vue';
$searchViewSource = file_exists($searchView) ? (string) file_get_contents($searchView) : '';
$topbarSource = file_exists($topbar) ? (string) file_get_contents($topbar) : '';

$frontendChecks = [
    'SearchView.vue exists' => file_exists($searchView),
    'SearchView calls search API' => str_contains($searchViewSource, 'searchApi'),
    'SearchView notes section' => str_contains($searchViewSource, 'notes'),
    'SearchView messages section' => str_contains($searchViewSource, 'messages'),
    'Topbar navigates to search' => str_contains($topbarSource, 'search'),
];
foreach ($frontendChecks as $label => $ok) {
    $results[] = [$label, $ok ? 'yes' : 'no', $ok ? 'OK' : 'FAIL'];
}

echo "\n==================== SEARCH RESULTS ====================\n";
$pass = 0;
$fail = 0;
foreach ($results as [$label, $status, $verdict]) {
    printf("%-45s %-8s %s\n", $label, (string) $status, $verdict);
    $verdict === 'OK' ? $pass++ : $fail++;
}
echo "----------------------------------------------------------------------\n";
echo "PASS: {$pass}  FAIL: {$fail}\n";

exit($fail > 0 ? 1 : 0);
