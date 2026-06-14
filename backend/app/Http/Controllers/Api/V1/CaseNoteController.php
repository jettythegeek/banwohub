<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Concerns\ResolvesOrganization;
use App\Http\Controllers\Api\V1\Concerns\ValidatesOrganizationAccess;
use App\Http\Controllers\Controller;
use App\Http\Resources\CaseNoteResource;
use App\Models\CaseNote;
use App\Models\LegalMatter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class CaseNoteController extends Controller
{
    use ResolvesOrganization;
    use ValidatesOrganizationAccess;

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', CaseNote::class);

        $organization = $this->organizationFor($request->user());

        $notes = CaseNote::query()
            ->with(['author:id,name', 'legalMatter:id,title,matter_number,lead_lawyer_id'])
            ->where('organization_id', $organization->id)
            ->when($request->filled('legal_matter_id'), fn ($q) => $q->where('legal_matter_id', $request->integer('legal_matter_id')))
            ->when($request->filled('note_type'), fn ($q) => $q->where('note_type', $request->string('note_type')))
            ->when($request->filled('visibility'), fn ($q) => $q->where('visibility', $request->string('visibility')))
            ->latest()
            ->get()
            ->filter(fn (CaseNote $note) => $this->noteVisibleTo($request->user(), $note))
            ->values();

        return CaseNoteResource::collection($notes);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', CaseNote::class);

        $organization = $this->organizationFor($request->user());
        $data = $this->validatedData($request);
        $matter = $this->legalMatterForOrganization((int) $data['legal_matter_id'], $organization->id);

        $note = CaseNote::query()->create([
            ...$data,
            'organization_id' => $organization->id,
            'legal_matter_id' => $matter->id,
            'author_id' => $request->user()->id,
            'note_type' => $data['note_type'] ?? 'private_note',
            'visibility' => $data['visibility'] ?? 'private',
        ]);

        activity('case_note')
            ->performedOn($note)
            ->causedBy($request->user())
            ->withProperties(['legal_matter_id' => $matter->id])
            ->log('Case note created');

        return (new CaseNoteResource($note->load('author')))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, CaseNote $caseNote): CaseNoteResource
    {
        $this->authorize('view', $caseNote);
        abort_unless($this->noteVisibleTo($request->user(), $caseNote->load('legalMatter.assignedStaff')), 403);

        return new CaseNoteResource($caseNote->load('author'));
    }

    public function update(Request $request, CaseNote $caseNote): CaseNoteResource
    {
        $this->authorize('update', $caseNote);

        $data = $this->validatedData($request, partial: true);
        if (isset($data['legal_matter_id'])) {
            $this->legalMatterForOrganization((int) $data['legal_matter_id'], $caseNote->organization_id);
        }

        $caseNote->update($data);

        return new CaseNoteResource($caseNote->fresh()->load('author'));
    }

    public function destroy(CaseNote $caseNote): JsonResponse
    {
        $this->authorize('delete', $caseNote);

        $caseNote->delete();

        return response()->json(['message' => 'Case note deleted successfully.']);
    }

    /**
     * @return array<string, mixed>
     */
    protected function validatedData(Request $request, bool $partial = false): array
    {
        return $request->validate([
            'legal_matter_id' => [$partial ? 'sometimes' : 'required', 'integer', 'exists:legal_matters,id'],
            'note_type' => ['nullable', 'string', Rule::in(CaseNote::TYPES)],
            'visibility' => ['nullable', 'string', Rule::in(CaseNote::VISIBILITIES)],
            'title' => ['nullable', 'string', 'max:255'],
            'body' => [$partial ? 'sometimes' : 'required', 'string'],
            'metadata' => ['nullable', 'array'],
        ]);
    }

    protected function noteVisibleTo($user, CaseNote $note): bool
    {
        if ($user->hasAnyRole(['System Admin', 'Firm Admin'])) {
            return true;
        }

        if ($note->visibility === 'private') {
            return $note->author_id === $user->id;
        }

        if ($note->visibility === 'assigned_team') {
            $matter = $note->legalMatter instanceof LegalMatter ? $note->legalMatter : $note->legalMatter()->first();

            return $matter?->lead_lawyer_id === $user->id
                || $matter?->assignedStaff->contains('id', $user->id);
        }

        if ($note->visibility === 'senior_lawyers') {
            return $user->hasAnyRole(['Partner', 'System Admin', 'Firm Admin']);
        }

        if ($note->visibility === 'admin') {
            return $user->hasAnyRole(['System Admin', 'Firm Admin']);
        }

        return $user->can('case-notes.view');
    }
}
