<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Concerns\ResolvesOrganization;
use App\Http\Controllers\Api\V1\Concerns\ValidatesOrganizationAccess;
use App\Http\Controllers\Controller;
use App\Http\Resources\LegalTaskResource;
use App\Models\LegalTask;
use App\Support\InAppNotifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class LegalTaskController extends Controller
{
    use ResolvesOrganization;
    use ValidatesOrganizationAccess;

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', LegalTask::class);

        $organization = $this->organizationFor($request->user());

        $tasks = LegalTask::query()
            ->with(['legalMatter:id,title,matter_number', 'assignee:id,name,email'])
            ->where('organization_id', $organization->id)
            ->when($request->filled('legal_matter_id'), fn ($q) => $q->where('legal_matter_id', $request->integer('legal_matter_id')))
            ->when($request->filled('assignee_id'), fn ($q) => $q->where('assignee_id', $request->integer('assignee_id')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('priority'), fn ($q) => $q->where('priority', $request->string('priority')))
            ->orderByRaw('due_at is null, due_at asc')
            ->latest()
            ->paginate($request->integer('per_page', 15));

        return LegalTaskResource::collection($tasks);
    }

    public function store(Request $request, InAppNotifier $notifier): JsonResponse
    {
        $this->authorize('create', LegalTask::class);

        $organization = $this->organizationFor($request->user());
        $data = $this->validatedData($request);
        $matter = $this->legalMatterForOrganization((int) $data['legal_matter_id'], $organization->id);
        $assignee = $this->userForOrganization((int) $data['assignee_id'], $organization->id);

        $task = LegalTask::query()->create([
            ...$data,
            'organization_id' => $organization->id,
            'legal_matter_id' => $matter->id,
            'assignee_id' => $assignee->id,
            'created_by' => $request->user()->id,
            'status' => $data['status'] ?? 'not_started',
            'priority' => $data['priority'] ?? 'normal',
            'completed_at' => ($data['status'] ?? null) === 'completed' ? now() : null,
        ]);

        $notifier->notifyUser(
            $assignee,
            'task_assigned',
            'Task assigned',
            $task->title,
            ['task_id' => $task->id, 'legal_matter_id' => $matter->id],
            $request->user()
        );

        return (new LegalTaskResource($task->load(['legalMatter', 'assignee'])))
            ->response()
            ->setStatusCode(201);
    }

    public function show(LegalTask $task): LegalTaskResource
    {
        $this->authorize('view', $task);

        return new LegalTaskResource($task->load([
            'legalMatter',
            'assignee',
            'attachments.uploader:id,name',
            'comments.user:id,name',
        ]));
    }

    public function update(Request $request, LegalTask $task): LegalTaskResource
    {
        $this->authorize('update', $task);

        $data = $this->validatedData($request, partial: true);
        if (isset($data['legal_matter_id'])) {
            $this->legalMatterForOrganization((int) $data['legal_matter_id'], $task->organization_id);
        }
        if (isset($data['assignee_id'])) {
            $this->userForOrganization((int) $data['assignee_id'], $task->organization_id);
        }
        if (($data['status'] ?? null) === 'completed' && $task->completed_at === null) {
            $data['completed_at'] = now();
        }
        if (isset($data['status']) && $data['status'] !== 'completed') {
            $data['completed_at'] = null;
        }

        $task->update($data);

        return new LegalTaskResource($task->fresh()->load(['legalMatter', 'assignee']));
    }

    public function destroy(LegalTask $task): JsonResponse
    {
        $this->authorize('delete', $task);

        $task->delete();

        return response()->json(['message' => 'Task deleted successfully.']);
    }

    /**
     * @return array<string, mixed>
     */
    protected function validatedData(Request $request, bool $partial = false): array
    {
        return $request->validate([
            'legal_matter_id' => [$partial ? 'sometimes' : 'required', 'integer', 'exists:legal_matters,id'],
            'assignee_id' => [$partial ? 'sometimes' : 'required', 'integer', 'exists:users,id'],
            'title' => [$partial ? 'sometimes' : 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'status' => ['nullable', 'string', Rule::in(LegalTask::STATUSES)],
            'priority' => ['nullable', 'string', Rule::in(LegalTask::PRIORITIES)],
            'due_at' => ['nullable', 'date'],
            'checklist' => ['nullable', 'array'],
            'checklist.*.id' => ['required_with:checklist', 'string', 'max:64'],
            'checklist.*.label' => ['required_with:checklist', 'string', 'max:255'],
            'checklist.*.done' => ['nullable', 'boolean'],
        ]);
    }
}
