<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Concerns\ResolvesOrganization;
use App\Http\Controllers\Api\V1\Concerns\ValidatesOrganizationAccess;
use App\Http\Controllers\Controller;
use App\Http\Resources\TimeEntryResource;
use App\Models\TimeEntry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class TimeEntryController extends Controller
{
    use ResolvesOrganization;
    use ValidatesOrganizationAccess;

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', TimeEntry::class);

        $user = $request->user();
        $organization = $this->organizationFor($user);

        $query = TimeEntry::query()
            ->with(['legalMatter:id,title,matter_number', 'legalTask:id,title', 'user:id,name,email'])
            ->where('organization_id', $organization->id)
            ->when(! $user->can('time-entries.view-all'), fn ($q) => $q->where('user_id', $user->id))
            ->when($request->filled('legal_matter_id'), fn ($q) => $q->where('legal_matter_id', $request->integer('legal_matter_id')))
            ->when($request->filled('legal_task_id'), fn ($q) => $q->where('legal_task_id', $request->integer('legal_task_id')))
            ->when($user->can('time-entries.view-all') && $request->filled('user_id'), fn ($q) => $q->where('user_id', $request->integer('user_id')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->boolean('running'), fn ($q) => $q->where('is_running', true))
            ->when($request->has('billable'), fn ($q) => $q->where('billable', $request->boolean('billable')));

        $entries = (clone $query)
            ->orderByDesc('started_at')
            ->latest()
            ->paginate($request->integer('per_page', 25));

        return TimeEntryResource::collection($entries)
            ->additional(['meta' => ['summary' => $this->summarize(clone $query)]]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', TimeEntry::class);

        $user = $request->user();
        $organization = $this->organizationFor($user);
        $data = $this->validatedData($request);

        $entry = TimeEntry::query()->create($this->buildAttributes($request, $data, $organization->id, $user));

        return (new TimeEntryResource($entry->load(['legalMatter', 'legalTask', 'user'])))
            ->response()
            ->setStatusCode(201);
    }

    public function show(TimeEntry $timeEntry): TimeEntryResource
    {
        $this->authorize('view', $timeEntry);

        return new TimeEntryResource($timeEntry->load(['legalMatter', 'legalTask', 'user', 'approver']));
    }

    public function update(Request $request, TimeEntry $timeEntry): TimeEntryResource
    {
        $this->authorize('update', $timeEntry);

        $data = $this->validatedData($request, partial: true);

        if (array_key_exists('legal_matter_id', $data) && $data['legal_matter_id'] !== null) {
            $this->legalMatterForOrganization((int) $data['legal_matter_id'], $timeEntry->organization_id);
        }
        if (array_key_exists('legal_task_id', $data) && $data['legal_task_id'] !== null) {
            $this->legalTaskForOrganization((int) $data['legal_task_id'], $timeEntry->organization_id);
        }

        $started = array_key_exists('started_at', $data)
            ? ($data['started_at'] ? Carbon::parse($data['started_at']) : null)
            : $timeEntry->started_at;
        $ended = array_key_exists('ended_at', $data)
            ? ($data['ended_at'] ? Carbon::parse($data['ended_at']) : null)
            : $timeEntry->ended_at;

        if ($started && $ended) {
            $data['duration_minutes'] = max(0, $started->diffInMinutes($ended));
        }

        $timeEntry->update($data);

        return new TimeEntryResource($timeEntry->fresh()->load(['legalMatter', 'legalTask', 'user', 'approver']));
    }

    public function destroy(TimeEntry $timeEntry): JsonResponse
    {
        $this->authorize('delete', $timeEntry);

        $timeEntry->delete();

        return response()->json(['message' => 'Time entry deleted successfully.']);
    }

    /**
     * Start a live timer for the authenticated user.
     */
    public function startTimer(Request $request): JsonResponse
    {
        $this->authorize('create', TimeEntry::class);

        $user = $request->user();
        $organization = $this->organizationFor($user);

        $existing = TimeEntry::query()
            ->where('organization_id', $organization->id)
            ->where('user_id', $user->id)
            ->where('is_running', true)
            ->first();

        if ($existing) {
            return (new TimeEntryResource($existing->load(['legalMatter', 'legalTask', 'user'])))
                ->response()
                ->setStatusCode(200);
        }

        $data = $request->validate([
            'legal_matter_id' => ['nullable', 'integer', 'exists:legal_matters,id'],
            'legal_task_id' => ['nullable', 'integer', 'exists:legal_tasks,id'],
            'description' => ['nullable', 'string'],
            'billable' => ['nullable', 'boolean'],
            'rate' => ['nullable', 'numeric', 'min:0'],
        ]);

        if (! empty($data['legal_matter_id'])) {
            $this->legalMatterForOrganization((int) $data['legal_matter_id'], $organization->id);
        }
        if (! empty($data['legal_task_id'])) {
            $this->legalTaskForOrganization((int) $data['legal_task_id'], $organization->id);
        }

        $entry = TimeEntry::query()->create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'created_by' => $user->id,
            'legal_matter_id' => $data['legal_matter_id'] ?? null,
            'legal_task_id' => $data['legal_task_id'] ?? null,
            'description' => $data['description'] ?? null,
            'billable' => $data['billable'] ?? true,
            'rate' => $data['rate'] ?? null,
            'started_at' => now(),
            'ended_at' => null,
            'duration_minutes' => 0,
            'status' => 'draft',
            'is_running' => true,
        ]);

        return (new TimeEntryResource($entry->load(['legalMatter', 'legalTask', 'user'])))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Stop a running timer and persist the elapsed duration.
     */
    public function stopTimer(Request $request, TimeEntry $timeEntry): TimeEntryResource
    {
        $this->authorize('update', $timeEntry);

        abort_unless($timeEntry->is_running, 422, 'This time entry is not running.');

        $endedAt = now();
        $startedAt = $timeEntry->started_at ?? $endedAt;

        $timeEntry->update([
            'ended_at' => $endedAt,
            'duration_minutes' => max(1, $startedAt->diffInMinutes($endedAt)),
            'is_running' => false,
        ]);

        return new TimeEntryResource($timeEntry->fresh()->load(['legalMatter', 'legalTask', 'user']));
    }

    /**
     * Return the authenticated user's currently running timer, if any.
     */
    public function running(Request $request): JsonResponse
    {
        $this->authorize('viewAny', TimeEntry::class);

        $user = $request->user();
        $organization = $this->organizationFor($user);

        $entry = TimeEntry::query()
            ->with(['legalMatter:id,title,matter_number', 'legalTask:id,title', 'user:id,name,email'])
            ->where('organization_id', $organization->id)
            ->where('user_id', $user->id)
            ->where('is_running', true)
            ->first();

        return response()->json(['data' => $entry ? new TimeEntryResource($entry) : null]);
    }

    public function approve(Request $request, TimeEntry $timeEntry): TimeEntryResource
    {
        $this->authorize('approve', $timeEntry);

        abort_if($timeEntry->is_running, 422, 'Stop the timer before approving this entry.');

        $timeEntry->update([
            'status' => 'approved',
            'approved_by' => $request->user()->id,
            'approved_at' => now(),
        ]);

        return new TimeEntryResource($timeEntry->fresh()->load(['legalMatter', 'legalTask', 'user', 'approver']));
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function buildAttributes(Request $request, array $data, int $organizationId, \App\Models\User $user): array
    {
        if (! empty($data['legal_matter_id'])) {
            $this->legalMatterForOrganization((int) $data['legal_matter_id'], $organizationId);
        }
        if (! empty($data['legal_task_id'])) {
            $this->legalTaskForOrganization((int) $data['legal_task_id'], $organizationId);
        }

        $ownerId = $user->id;
        if (! empty($data['user_id']) && $user->can('time-entries.update-all')) {
            $owner = $this->userForOrganization((int) $data['user_id'], $organizationId);
            $ownerId = $owner->id;
        }

        $started = ! empty($data['started_at']) ? Carbon::parse($data['started_at']) : null;
        $ended = ! empty($data['ended_at']) ? Carbon::parse($data['ended_at']) : null;
        $duration = $data['duration_minutes'] ?? 0;
        if ($started && $ended) {
            $duration = max(0, $started->diffInMinutes($ended));
        }

        return [
            'organization_id' => $organizationId,
            'user_id' => $ownerId,
            'created_by' => $user->id,
            'legal_matter_id' => $data['legal_matter_id'] ?? null,
            'legal_task_id' => $data['legal_task_id'] ?? null,
            'description' => $data['description'] ?? null,
            'started_at' => $started,
            'ended_at' => $ended,
            'duration_minutes' => $duration,
            'billable' => $data['billable'] ?? true,
            'rate' => $data['rate'] ?? null,
            'status' => $data['status'] ?? 'draft',
            'is_running' => false,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function validatedData(Request $request, bool $partial = false): array
    {
        return $request->validate([
            'legal_matter_id' => ['nullable', 'integer', 'exists:legal_matters,id'],
            'legal_task_id' => ['nullable', 'integer', 'exists:legal_tasks,id'],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'description' => ['nullable', 'string'],
            'started_at' => ['nullable', 'date'],
            'ended_at' => ['nullable', 'date', 'after_or_equal:started_at'],
            'duration_minutes' => [$partial ? 'sometimes' : 'nullable', 'integer', 'min:0'],
            'billable' => ['nullable', 'boolean'],
            'rate' => ['nullable', 'numeric', 'min:0'],
            'status' => ['nullable', 'string', Rule::in(TimeEntry::STATUSES)],
        ]);
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<TimeEntry>  $query
     * @return array<string, mixed>
     */
    protected function summarize($query): array
    {
        $rows = $query->get(['duration_minutes', 'billable', 'rate']);

        $totalMinutes = (int) $rows->sum('duration_minutes');
        $billableMinutes = (int) $rows->where('billable', true)->sum('duration_minutes');
        $billableAmount = $rows->where('billable', true)->reduce(function (float $carry, TimeEntry $entry): float {
            return $carry + ($entry->rate !== null ? (float) $entry->rate * ($entry->duration_minutes / 60) : 0);
        }, 0.0);

        return [
            'total_minutes' => $totalMinutes,
            'total_hours' => round($totalMinutes / 60, 2),
            'billable_minutes' => $billableMinutes,
            'billable_hours' => round($billableMinutes / 60, 2),
            'non_billable_minutes' => $totalMinutes - $billableMinutes,
            'billable_amount' => round($billableAmount, 2),
            'entry_count' => $rows->count(),
        ];
    }
}
