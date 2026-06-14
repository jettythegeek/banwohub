<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Concerns\ResolvesOrganization;
use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\CalendarEvent;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

class CalendarHubController extends Controller
{
    use ResolvesOrganization;

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', CalendarEvent::class);

        $organization = $this->organizationFor($request->user());

        $data = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'category' => ['nullable', 'string', 'in:all,appointments,hearings,deadlines'],
        ]);

        $category = $data['category'] ?? 'all';
        $from = isset($data['from']) ? Carbon::parse($data['from'])->startOfDay() : now()->startOfMonth();
        $to = isset($data['to']) ? Carbon::parse($data['to'])->endOfDay() : now()->endOfMonth();
        $userId = $data['user_id'] ?? null;

        $items = $this->collectHubItems($request, $organization->id, $category, $from, $to, $userId);

        $deadlineBoard = [];
        if (in_array($category, ['all', 'deadlines'], true)) {
            $deadlineBoard = CalendarEvent::query()
                ->with(['legalMatter:id,title,matter_number', 'user:id,name'])
                ->where('organization_id', $organization->id)
                ->when($userId, fn ($q) => $q->where('user_id', $userId))
                ->whereIn('event_type', CalendarEvent::DEADLINE_EVENT_TYPES)
                ->where('starts_at', '>=', now()->startOfDay())
                ->where('starts_at', '<=', now()->addDays(90)->endOfDay())
                ->orderBy('starts_at')
                ->limit(25)
                ->get()
                ->map(fn (CalendarEvent $event) => $this->normalizeCalendarEvent($event))
                ->values()
                ->all();
        }

        return response()->json([
            'data' => $items,
            'meta' => [
                'from' => $from->toIso8601String(),
                'to' => $to->toIso8601String(),
                'category' => $category,
                'count' => count($items),
                'deadline_board' => $deadlineBoard,
                'hearing_types' => CalendarEvent::HEARING_TYPES,
                'hearing_statuses' => CalendarEvent::HEARING_STATUSES,
                'deadline_subtypes' => CalendarEvent::DEADLINE_SUBTYPES,
            ],
        ]);
    }

    public function exportIcs(Request $request): Response
    {
        $this->authorize('viewAny', CalendarEvent::class);

        $organization = $this->organizationFor($request->user());

        $data = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'category' => ['nullable', 'string', 'in:all,appointments,hearings,deadlines'],
        ]);

        $category = $data['category'] ?? 'all';
        $from = isset($data['from']) ? Carbon::parse($data['from'])->startOfDay() : now()->startOfMonth();
        $to = isset($data['to']) ? Carbon::parse($data['to'])->endOfDay() : now()->addMonths(3)->endOfMonth();
        $userId = $data['user_id'] ?? null;

        $items = $this->collectHubItems($request, $organization->id, $category, $from, $to, $userId);
        $ics = $this->buildIcsFeed($items, $organization->name ?? 'Banwolaw Hub');

        return response($ics, 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="banwolaw-calendar.ics"',
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function collectHubItems(
        Request $request,
        int $organizationId,
        string $category,
        Carbon $from,
        Carbon $to,
        ?int $userId,
    ): array {
        $items = [];

        if (in_array($category, ['all', 'appointments'], true) && $request->user()->can('appointments.view')) {
            $appointments = Appointment::query()
                ->with(['lawyer:id,name', 'client:id,name', 'legalMatter:id,title,matter_number'])
                ->where('organization_id', $organizationId)
                ->when($userId, fn ($q) => $q->where('user_id', $userId))
                ->where('starts_at', '>=', $from)
                ->where('starts_at', '<=', $to)
                ->orderBy('starts_at')
                ->get();

            foreach ($appointments as $appointment) {
                $items[] = $this->normalizeAppointment($appointment);
            }
        }

        if (in_array($category, ['all', 'hearings', 'deadlines'], true)) {
            $events = CalendarEvent::query()
                ->with(['legalMatter:id,title,matter_number', 'user:id,name'])
                ->where('organization_id', $organizationId)
                ->when($userId, fn ($q) => $q->where('user_id', $userId))
                ->when($category === 'hearings', fn ($q) => $q->where('event_type', 'court_hearing'))
                ->when($category === 'deadlines', fn ($q) => $q->whereIn('event_type', CalendarEvent::DEADLINE_EVENT_TYPES))
                ->where('starts_at', '>=', $from)
                ->where('starts_at', '<=', $to)
                ->orderBy('starts_at')
                ->get();

            foreach ($events as $event) {
                $items[] = $this->normalizeCalendarEvent($event);
            }
        }

        usort($items, fn (array $a, array $b) => strcmp($a['starts_at'], $b['starts_at']));

        return $items;
    }

    /**
     * @param  list<array<string, mixed>>  $items
     */
    protected function buildIcsFeed(array $items, string $calendarName): string
    {
        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//Banwolaw Hub//Calendar//EN',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'X-WR-CALNAME:'.$this->escapeIcsText($calendarName),
        ];

        foreach ($items as $item) {
            $uid = Str::slug((string) ($item['id'] ?? 'event')).'@banwolaw.test';
            $startsAt = Carbon::parse($item['starts_at']);
            $endsAt = isset($item['ends_at']) && $item['ends_at']
                ? Carbon::parse($item['ends_at'])
                : $startsAt->copy()->addHour();

            $lines[] = 'BEGIN:VEVENT';
            $lines[] = 'UID:'.$uid;
            $lines[] = 'DTSTAMP:'.now()->utc()->format('Ymd\THis\Z');
            $lines[] = 'DTSTART:'.$startsAt->utc()->format('Ymd\THis\Z');
            $lines[] = 'DTEND:'.$endsAt->utc()->format('Ymd\THis\Z');
            $lines[] = 'SUMMARY:'.$this->escapeIcsText((string) ($item['title'] ?? 'Event'));

            $description = trim((string) ($item['description'] ?? ''));
            if ($description !== '') {
                $lines[] = 'DESCRIPTION:'.$this->escapeIcsText($description);
            }

            $location = trim((string) ($item['location'] ?? ''));
            if ($location !== '') {
                $lines[] = 'LOCATION:'.$this->escapeIcsText($location);
            }

            $lines[] = 'END:VEVENT';
        }

        $lines[] = 'END:VCALENDAR';

        return implode("\r\n", $lines)."\r\n";
    }

    protected function escapeIcsText(string $value): string
    {
        return str_replace(
            ['\\', ';', ',', "\n", "\r"],
            ['\\\\', '\\;', '\\,', '\\n', ''],
            $value,
        );
    }

    /**
     * @return array<string, mixed>
     */
    protected function normalizeAppointment(Appointment $appointment): array
    {
        return [
            'id' => 'appointment-'.$appointment->id,
            'source' => 'appointment',
            'source_id' => $appointment->id,
            'category' => 'appointment',
            'event_type' => 'appointment',
            'title' => $appointment->consultation_type
                ? ucwords(str_replace('_', ' ', $appointment->consultation_type))
                : 'Appointment',
            'description' => $appointment->notes,
            'starts_at' => $appointment->starts_at?->toIso8601String(),
            'ends_at' => $appointment->ends_at?->toIso8601String(),
            'location' => $appointment->location,
            'status' => $appointment->status,
            'consultation_type' => $appointment->consultation_type,
            'user' => $appointment->lawyer ? [
                'id' => $appointment->lawyer->id,
                'name' => $appointment->lawyer->name,
            ] : null,
            'client' => $appointment->client ? [
                'id' => $appointment->client->id,
                'name' => $appointment->client->name,
            ] : null,
            'case' => $appointment->legalMatter ? [
                'id' => $appointment->legalMatter->id,
                'title' => $appointment->legalMatter->title,
                'matter_number' => $appointment->legalMatter->matter_number,
            ] : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function normalizeCalendarEvent(CalendarEvent $event): array
    {
        return [
            'id' => 'event-'.$event->id,
            'source' => 'calendar_event',
            'source_id' => $event->id,
            'category' => CalendarEvent::categoryForType($event->event_type),
            'event_type' => $event->event_type,
            'title' => $event->title,
            'description' => $event->description,
            'starts_at' => $event->starts_at?->toIso8601String(),
            'ends_at' => $event->ends_at?->toIso8601String(),
            'location' => $event->location,
            'reminder_at' => $event->reminder_at?->toIso8601String(),
            'reminder_days_before' => $event->reminder_days_before,
            'hearing_type' => $event->hearing_type,
            'hearing_status' => $event->hearing_status,
            'deadline_subtype' => $event->deadline_subtype,
            'court_name' => $event->court_name,
            'court_room' => $event->court_room,
            'judge_name' => $event->judge_name,
            'user' => $event->user ? [
                'id' => $event->user->id,
                'name' => $event->user->name,
            ] : null,
            'case' => $event->legalMatter ? [
                'id' => $event->legalMatter->id,
                'title' => $event->legalMatter->title,
                'matter_number' => $event->legalMatter->matter_number,
            ] : null,
        ];
    }
}
