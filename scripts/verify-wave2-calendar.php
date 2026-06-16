<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use App\Models\CalendarEvent;
use App\Models\LegalMatter;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Http\Request;
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

$token = $admin->createToken('wave2-calendar-verify')->plainTextToken;
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

    return ['status' => $response->getStatusCode(), 'body' => $body, 'raw' => $content];
}

function record(array &$results, string $label, bool $ok): void
{
    $results[] = [$label, $ok ? 'OK' : 'FAIL'];
    if (! $ok) {
        fwrite(STDERR, "FAIL: {$label}\n");
    }
}

echo "=== WAVE 2: UNIFIED CALENDAR HUB ===\n";

foreach (['hearing_type', 'hearing_status', 'deadline_subtype', 'court_name', 'court_room', 'judge_name', 'reminder_days_before'] as $col) {
    record($results, "calendar_events.{$col} column", Schema::hasColumn('calendar_events', $col));
}

record($results, 'CalendarEvent::HEARING_TYPES count', count(CalendarEvent::HEARING_TYPES) >= 6);
record($results, 'CalendarEvent::HEARING_STATUSES count', count(CalendarEvent::HEARING_STATUSES) >= 4);
record($results, 'CalendarEvent::DEADLINE_SUBTYPES count', count(CalendarEvent::DEADLINE_SUBTYPES) === 4);
record($results, 'CalendarEvent::categoryForType hearing', CalendarEvent::categoryForType('court_hearing') === 'hearing');
record($results, 'CalendarEvent::categoryForType deadline', CalendarEvent::categoryForType('filing_deadline') === 'deadline');

$matter = LegalMatter::query()->where('organization_id', $admin->organization_id)->first();
if (! $matter) {
    fwrite(STDERR, "No legal matter found for org {$admin->organization_id}\n");
    exit(1);
}

$hearingRes = call($http, $token, 'POST', '/calendar-events', [
    'legal_matter_id' => $matter->id,
    'user_id' => $admin->id,
    'event_type' => 'court_hearing',
    'hearing_type' => 'trial',
    'hearing_status' => 'scheduled',
    'title' => 'Wave2 Trial Hearing',
    'starts_at' => now()->addDays(14)->toIso8601String(),
    'court_name' => 'District Court',
    'court_room' => '4B',
    'judge_name' => 'Hon. Smith',
    'reminder_days_before' => 3,
]);
record($results, 'POST /calendar-events (hearing fields)', $hearingRes['status'] === 201);
$hearingBody = $hearingRes['body']['data'] ?? $hearingRes['body'] ?? [];
record($results, '  hearing_type persisted', ($hearingBody['hearing_type'] ?? '') === 'trial');
record($results, '  court_name persisted', ($hearingBody['court_name'] ?? '') === 'District Court');
record($results, '  reminder_days_before persisted', (int) ($hearingBody['reminder_days_before'] ?? 0) === 3);

$deadlineRes = call($http, $token, 'POST', '/calendar-events', [
    'legal_matter_id' => $matter->id,
    'user_id' => $admin->id,
    'event_type' => 'filing_deadline',
    'deadline_subtype' => 'court_date',
    'title' => 'Wave2 Filing Deadline',
    'starts_at' => now()->addDays(7)->toIso8601String(),
    'reminder_days_before' => 5,
]);
record($results, 'POST /calendar-events (deadline subtype)', $deadlineRes['status'] === 201);

$hubAllRes = call($http, $token, 'GET', '/calendar-hub?category=all');
record($results, 'GET /calendar-hub (all)', $hubAllRes['status'] === 200);
$hubAllBody = $hubAllRes['body'] ?? [];
record($results, '  hub data array', is_array($hubAllBody['data'] ?? null));
record($results, '  hub meta deadline_board', is_array($hubAllBody['meta']['deadline_board'] ?? null));

$hubHearingsRes = call($http, $token, 'GET', '/calendar-hub?category=hearings');
record($results, 'GET /calendar-hub (hearings)', $hubHearingsRes['status'] === 200);
$hearingRows = $hubHearingsRes['body']['data'] ?? [];
$hasHearing = false;
foreach ($hearingRows as $row) {
    if (($row['category'] ?? '') === 'hearing') {
        $hasHearing = true;
        break;
    }
}
record($results, '  hearings filter returns hearing category', $hasHearing);

$hubDeadlinesRes = call($http, $token, 'GET', '/calendar-hub?category=deadlines');
record($results, 'GET /calendar-hub (deadlines)', $hubDeadlinesRes['status'] === 200);

$icsRes = call($http, $token, 'GET', '/calendar-hub/export.ics?category=all');
record($results, 'GET /calendar-hub/export.ics', $icsRes['status'] === 200);
$icsBody = is_string($icsRes['raw'] ?? null) ? $icsRes['raw'] : '';
record($results, '  export.ics VCALENDAR body', str_contains($icsBody, 'BEGIN:VCALENDAR') && str_contains($icsBody, 'END:VCALENDAR'));

$filterHearingsRes = call($http, $token, 'GET', '/calendar-events?category=hearings&per_page=50');
record($results, 'GET /calendar-events?category=hearings', $filterHearingsRes['status'] === 200);

$calendarView = __DIR__ . '/../frontend/src/views/CalendarView.vue';
$calendarPanel = __DIR__ . '/../frontend/src/components/cases/CaseCalendarPanel.vue';
$calendarGrid = __DIR__ . '/../frontend/src/lib/calendar-grid.ts';
$enums = __DIR__ . '/../frontend/src/lib/enums.ts';
$calendarViewSrc = (string) @file_get_contents($calendarView);
$calendarPanelSrc = (string) @file_get_contents($calendarPanel);

record($results, 'CalendarView.vue exists', file_exists($calendarView));
record($results, 'CalendarView month/week toggle', str_contains($calendarViewSrc, "viewMode") && str_contains($calendarViewSrc, "'month'") && str_contains($calendarViewSrc, "'week'"));
record($results, 'CalendarView category filters', str_contains($calendarViewSrc, 'categoryFilter') && str_contains($calendarViewSrc, 'CALENDAR_HUB_CATEGORIES'));
record($results, 'CalendarView calendarHubApi', str_contains($calendarViewSrc, 'calendarHubApi'));
record($results, 'CalendarView export iCal button', str_contains($calendarViewSrc, 'exportIcs'));
record($results, 'CalendarView deadlines board', str_contains($calendarViewSrc, 'deadlineBoard'));
record($results, 'CalendarView bw-card layout', str_contains($calendarViewSrc, 'bw-card'));
record($results, 'calendar-grid.ts exists', file_exists($calendarGrid));
record($results, 'CaseCalendarPanel hearing fields', str_contains($calendarPanelSrc, 'HEARING_TYPES') && str_contains($calendarPanelSrc, 'court_name'));
record($results, 'CaseCalendarPanel reminder_days_before', str_contains($calendarPanelSrc, 'reminder_days_before'));
record($results, 'enums CALENDAR_HUB_CATEGORIES', str_contains((string) @file_get_contents($enums), 'CALENDAR_HUB_CATEGORIES'));

$pass = 0;
$fail = 0;
foreach ($results as [, $status]) {
    if ($status === 'OK') {
        $pass++;
    } else {
        $fail++;
    }
}

echo "\nPASS: {$pass}  FAIL: {$fail}\n";
exit($fail > 0 ? 1 : 0);
