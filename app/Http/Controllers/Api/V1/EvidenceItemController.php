<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Concerns\ResolvesOrganization;
use App\Http\Controllers\Api\V1\Concerns\ValidatesOrganizationAccess;
use App\Http\Controllers\Controller;
use App\Http\Resources\EvidenceCustodyLogResource;
use App\Http\Resources\EvidenceItemResource;
use App\Models\EvidenceCustodyLog;
use App\Models\EvidenceItem;
use App\Services\EvidenceBundleExportService;
use App\Services\EvidenceExhibitService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class EvidenceItemController extends Controller
{
    use ResolvesOrganization;
    use ValidatesOrganizationAccess;

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', EvidenceItem::class);

        $organization = $this->organizationFor($request->user());

        $items = EvidenceItem::query()
            ->with(['legalMatter:id,title,matter_number', 'uploader:id,name'])
            ->where('organization_id', $organization->id)
            ->when($request->filled('legal_matter_id'), fn ($q) => $q->where('legal_matter_id', $request->integer('legal_matter_id')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('evidence_type'), fn ($q) => $q->where('evidence_type', $request->string('evidence_type')))
            ->when($request->boolean('exhibits_only'), fn ($q) => $q->whereNotNull('exhibit_number'))
            ->orderByDesc('updated_at')
            ->paginate($request->integer('per_page', 25));

        return EvidenceItemResource::collection($items)
            ->additional(['meta' => [
                'statuses' => EvidenceItem::STATUSES,
                'evidence_types' => EvidenceItem::EVIDENCE_TYPES,
                'custody_actions' => EvidenceCustodyLog::ACTIONS,
            ]]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', EvidenceItem::class);

        $user = $request->user();
        $organization = $this->organizationFor($user);
        $data = $this->validatedData($request);

        $this->legalMatterForOrganization((int) $data['legal_matter_id'], $organization->id);

        $path = null;
        $originalFilename = null;
        $mimeType = null;
        $size = 0;

        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $pathPrefix = "organizations/{$organization->id}/cases/{$data['legal_matter_id']}/evidence";
            $path = $file->store($pathPrefix, 'local');
            $originalFilename = $file->getClientOriginalName();
            $mimeType = $file->getClientMimeType();
            $size = $file->getSize() ?: 0;
        }

        $item = EvidenceItem::query()->create([
            ...$data,
            'organization_id' => $organization->id,
            'uploaded_by' => $user->id,
            'created_by' => $user->id,
            'status' => $data['status'] ?? 'uploaded',
            'path' => $path,
            'original_filename' => $originalFilename,
            'mime_type' => $mimeType,
            'size' => $size,
            'disk' => 'local',
        ]);

        EvidenceCustodyLog::query()->create([
            'organization_id' => $organization->id,
            'evidence_item_id' => $item->id,
            'action' => 'received',
            'notes' => 'Evidence uploaded to case file.',
            'logged_by' => $user->id,
            'logged_at' => now(),
        ]);

        activity('evidence')
            ->performedOn($item)
            ->causedBy($user)
            ->withProperties(['legal_matter_id' => $item->legal_matter_id])
            ->log('Evidence uploaded');

        return (new EvidenceItemResource($item->load(['legalMatter', 'uploader'])))
            ->response()
            ->setStatusCode(201);
    }

    public function show(EvidenceItem $evidenceItem): EvidenceItemResource
    {
        $this->authorize('view', $evidenceItem);

        return new EvidenceItemResource(
            $evidenceItem->load(['legalMatter', 'uploader', 'custodyLogs.logger', 'custodyLogs.fromUser', 'custodyLogs.toUser'])
        );
    }

    public function update(Request $request, EvidenceItem $evidenceItem): EvidenceItemResource
    {
        $this->authorize('update', $evidenceItem);

        if ($request->filled('legal_matter_id')) {
            $this->legalMatterForOrganization((int) $request->integer('legal_matter_id'), $evidenceItem->organization_id);
        }

        $evidenceItem->update($this->validatedData($request, partial: true));

        return new EvidenceItemResource($evidenceItem->fresh()->load(['legalMatter', 'uploader']));
    }

    public function destroy(EvidenceItem $evidenceItem): JsonResponse
    {
        $this->authorize('delete', $evidenceItem);

        if ($evidenceItem->path && Storage::disk($evidenceItem->disk)->exists($evidenceItem->path)) {
            Storage::disk($evidenceItem->disk)->delete($evidenceItem->path);
        }

        $evidenceItem->delete();

        return response()->json(['message' => 'Evidence item deleted successfully.']);
    }

    public function updateStatus(Request $request, EvidenceItem $evidenceItem): EvidenceItemResource
    {
        $this->authorize('update', $evidenceItem);

        $data = $request->validate([
            'status' => ['required', 'string', Rule::in(EvidenceItem::STATUSES)],
        ]);

        $nextStatus = $data['status'];
        if (! $evidenceItem->canTransitionTo($nextStatus)) {
            throw ValidationException::withMessages([
                'status' => ["Cannot transition from {$evidenceItem->status} to {$nextStatus}."],
            ]);
        }

        $evidenceItem->update(['status' => $nextStatus]);

        return new EvidenceItemResource($evidenceItem->fresh()->load(['legalMatter', 'uploader']));
    }

    public function assignExhibit(
        Request $request,
        EvidenceItem $evidenceItem,
        EvidenceExhibitService $exhibitService,
    ): EvidenceItemResource {
        $this->authorize('update', $evidenceItem);

        $data = $request->validate([
            'exhibit_number' => ['nullable', 'string', 'max:50'],
        ]);

        $updated = $exhibitService->assignExhibitNumber($evidenceItem, $data['exhibit_number'] ?? null);

        EvidenceCustodyLog::query()->create([
            'organization_id' => $evidenceItem->organization_id,
            'evidence_item_id' => $evidenceItem->id,
            'action' => 'reviewed',
            'notes' => 'Exhibit number assigned: '.$updated->exhibit_number,
            'logged_by' => $request->user()->id,
            'logged_at' => now(),
        ]);

        return new EvidenceItemResource($updated->load(['legalMatter', 'uploader']));
    }

    public function custodyLogs(EvidenceItem $evidenceItem): AnonymousResourceCollection
    {
        $this->authorize('view', $evidenceItem);

        $logs = $evidenceItem->custodyLogs()
            ->with(['logger:id,name', 'fromUser:id,name', 'toUser:id,name'])
            ->get();

        return EvidenceCustodyLogResource::collection($logs);
    }

    public function storeCustodyLog(Request $request, EvidenceItem $evidenceItem): JsonResponse
    {
        $this->authorize('update', $evidenceItem);

        $data = $request->validate([
            'action' => ['required', 'string', Rule::in(EvidenceCustodyLog::ACTIONS)],
            'notes' => ['nullable', 'string'],
            'location' => ['nullable', 'string', 'max:255'],
            'from_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'to_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'logged_at' => ['nullable', 'date'],
        ]);

        $log = EvidenceCustodyLog::query()->create([
            ...$data,
            'organization_id' => $evidenceItem->organization_id,
            'evidence_item_id' => $evidenceItem->id,
            'logged_by' => $request->user()->id,
            'logged_at' => $data['logged_at'] ?? now(),
        ]);

        return (new EvidenceCustodyLogResource($log->load(['logger', 'fromUser', 'toUser'])))
            ->response()
            ->setStatusCode(201);
    }

    public function exhibitIndex(Request $request, EvidenceBundleExportService $exportService): JsonResponse
    {
        $this->authorize('viewAny', EvidenceItem::class);

        $organization = $this->organizationFor($request->user());
        $data = $request->validate([
            'legal_matter_id' => ['required', 'integer', 'exists:legal_matters,id'],
        ]);

        $this->legalMatterForOrganization((int) $data['legal_matter_id'], $organization->id);

        return response()->json($exportService->exhibitIndex((int) $data['legal_matter_id'], $organization->id));
    }

    public function exportBundle(Request $request, EvidenceBundleExportService $exportService): BinaryFileResponse
    {
        $this->authorize('viewAny', EvidenceItem::class);

        $organization = $this->organizationFor($request->user());
        $data = $request->validate([
            'legal_matter_id' => ['required', 'integer', 'exists:legal_matters,id'],
        ]);

        $this->legalMatterForOrganization((int) $data['legal_matter_id'], $organization->id);

        $zipPath = $exportService->createBundleZip((int) $data['legal_matter_id'], $organization->id);

        EvidenceItem::query()
            ->where('organization_id', $organization->id)
            ->where('legal_matter_id', $data['legal_matter_id'])
            ->whereNotNull('exhibit_number')
            ->each(function (EvidenceItem $item) use ($request): void {
                EvidenceCustodyLog::query()->create([
                    'organization_id' => $item->organization_id,
                    'evidence_item_id' => $item->id,
                    'action' => 'exported',
                    'notes' => 'Included in exhibit bundle export.',
                    'logged_by' => $request->user()->id,
                    'logged_at' => now(),
                ]);
            });

        return response()->download($zipPath, 'exhibit-bundle.zip')->deleteFileAfterSend(true);
    }

    /**
     * @return array<string, mixed>
     */
    protected function validatedData(Request $request, bool $partial = false): array
    {
        $rules = [
            'legal_matter_id' => [$partial ? 'sometimes' : 'required', 'integer', 'exists:legal_matters,id'],
            'title' => [$partial ? 'sometimes' : 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'evidence_type' => [$partial ? 'sometimes' : 'required', 'string', Rule::in(EvidenceItem::EVIDENCE_TYPES)],
            'source' => ['nullable', 'string', 'max:255'],
            'date_obtained' => ['nullable', 'date'],
            'relevance' => ['nullable', 'string', 'max:255'],
            'exhibit_number' => ['nullable', 'string', 'max:50'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['string', 'max:100'],
            'status' => ['nullable', 'string', Rule::in(EvidenceItem::STATUSES)],
            'file' => ['nullable', 'file', 'max:20480'],
        ];

        return $request->validate($rules);
    }
}
