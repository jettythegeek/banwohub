<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Concerns\ResolvesOrganization;
use App\Http\Controllers\Api\V1\Concerns\ValidatesOrganizationAccess;
use App\Http\Controllers\Controller;
use App\Http\Resources\CalendarEventResource;
use App\Models\CalendarEvent;
use App\Services\AutoTaskService;
use App\Support\InAppNotifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class CalendarEventController extends Controller
{
    use ResolvesOrganization;
    use ValidatesOrganizationAccess;

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', CalendarEvent::class);

        $organization = $this->organizationFor($request->user());

        $events = CalendarEvent::query()
            ->with(['legalMatter:id,title,matter_number', 'user:id,name'])
            ->where('organization_id', $organization->id)
            ->when($request->filled('legal_matter_id'), fn ($q) => $q->where('legal_matter_id', $request->integer('legal_matter_id')))
            ->when($request->filled('user_id'), fn ($q) => $q->where('user_id', $request->integer('user_id')))
            ->when($request->filled('event_type'), fn ($q) => $q->where('event_type', $request->string('event_type')))
            ->when($request->string('category') === 'hearings', fn ($q) => $q->where('event_type', 'court_hearing'))
            ->when($request->string('category') === 'deadlines', fn ($q) => $q->whereIn('event_type', CalendarEvent::DEADLINE_EVENT_TYPES))
            ->when($request->filled('from'), fn ($q) => $q->where('starts_at', '>=', $request->date('from')))
            ->when($request->filled('to'), fn ($q) => $q->where('starts_at', '<=', $request->date('to')))
            ->orderBy('starts_at')
            ->paginate($request->integer('per_page', 15));

        return CalendarEventResource::collection($events);
    }

    public function store(Request $request, InAppNotifier $notifier, AutoTaskService $autoTasks): JsonResponse
    {
        $this->authorize('create', CalendarEvent::class);

        $organization = $this->organizationFor($request->user());
        $data = $this->validatedData($request);
        $user = $this->userForOrganization((int) $data['user_id'], $organization->id);
        $matterId = null;
        if (! empty($data['legal_matter_id'])) {
            $matterId = $this->legalMatterForOrganization((int) $data['legal_matter_id'], $organization->id)->id;
        }

        $event = new CalendarEvent([
            ...$data,
            'organization_id' => $organization->id,
            'legal_matter_id' => $matterId,
            'user_id' => $user->id,
            'created_by' => $request->user()->id,
        ]);
        $event->applyReminderDaysBefore();
        $event->save();

        $notifier->notifyUser(
            $user,
            'calendar_event_created',
            'Calendar event created',
            $event->title,
            ['calendar_event_id' => $event->id, 'legal_matter_id' => $matterId, 'starts_at' => $event->starts_at?->toIso8601String()],
            $request->user()
        );

        if (in_array($event->event_type, ['court_hearing', 'filing_deadline', 'limitation_deadline'], true)) {
            $autoTasks->onCourtDateAdded($event, $request->user());
        }

        activity('calendar')
            ->performedOn($event)
            ->causedBy($request->user())
            ->withProperties(['legal_matter_id' => $matterId])
            ->log('Calendar event created');

        return (new CalendarEventResource($event->load(['legalMatter', 'user'])))
            ->response()
            ->setStatusCode(201);
    }

    public function show(CalendarEvent $calendarEvent): CalendarEventResource
    {
        $this->authorize('view', $calendarEvent);

        return new CalendarEventResource($calendarEvent->load(['legalMatter', 'user']));
    }

    public function update(Request $request, CalendarEvent $calendarEvent): CalendarEventResource
    {
        $this->authorize('update', $calendarEvent);

        $data = $this->validatedData($request, partial: true);
        if (isset($data['legal_matter_id'])) {
            $this->legalMatterForOrganization((int) $data['legal_matter_id'], $calendarEvent->organization_id);
        }
        if (isset($data['user_id'])) {
            $this->userForOrganization((int) $data['user_id'], $calendarEvent->organization_id);
        }

        $calendarEvent->fill($data);
        $calendarEvent->applyReminderDaysBefore();
        $calendarEvent->save();

        return new CalendarEventResource($calendarEvent->fresh()->load(['legalMatter', 'user']));
    }

    public function destroy(CalendarEvent $calendarEvent): JsonResponse
    {
        $this->authorize('delete', $calendarEvent);

        $calendarEvent->delete();

        return response()->json(['message' => 'Calendar event deleted successfully.']);
    }

    /**
     * @return array<string, mixed>
     */
    protected function validatedData(Request $request, bool $partial = false): array
    {
        return $request->validate([
            'legal_matter_id' => [$partial ? 'sometimes' : 'nullable', 'integer', 'exists:legal_matters,id'],
            'user_id' => [$partial ? 'sometimes' : 'required', 'integer', 'exists:users,id'],
            'event_type' => [$partial ? 'sometimes' : 'required', 'string', Rule::in(CalendarEvent::TYPES)],
            'hearing_type' => ['nullable', 'string', Rule::in(CalendarEvent::HEARING_TYPES)],
            'hearing_status' => ['nullable', 'string', Rule::in(CalendarEvent::HEARING_STATUSES)],
            'deadline_subtype' => ['nullable', 'string', Rule::in(CalendarEvent::DEADLINE_SUBTYPES)],
            'title' => [$partial ? 'sometimes' : 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'starts_at' => [$partial ? 'sometimes' : 'required', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'location' => ['nullable', 'string', 'max:255'],
            'court_name' => ['nullable', 'string', 'max:255'],
            'court_room' => ['nullable', 'string', 'max:100'],
            'judge_name' => ['nullable', 'string', 'max:255'],
            'reminder_at' => ['nullable', 'date'],
            'reminder_days_before' => ['nullable', 'integer', 'min:0', 'max:365'],
            'metadata' => ['nullable', 'array'],
        ]);
    }
}
