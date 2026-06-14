<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Concerns\ResolvesOrganization;
use App\Http\Controllers\Controller;
use App\Http\Resources\IntakeFormResource;
use App\Models\IntakeForm;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class IntakeFormController extends Controller
{
    use ResolvesOrganization;

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', IntakeForm::class);

        $organization = $this->organizationFor($request->user());

        $forms = IntakeForm::query()
            ->with('creator:id,name')
            ->withCount('submissions')
            ->where('organization_id', $organization->id)
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('case_type'), fn ($q) => $q->where('case_type', $request->string('case_type')))
            ->latest()
            ->paginate($request->integer('per_page', 15));

        return IntakeFormResource::collection($forms);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', IntakeForm::class);

        $organization = $this->organizationFor($request->user());
        $data = $this->validatedData($request);

        $form = IntakeForm::query()->create([
            ...$data,
            'organization_id' => $organization->id,
            'created_by' => $request->user()->id,
            'status' => $data['status'] ?? 'draft',
        ]);

        return (new IntakeFormResource($form->load('creator')))
            ->response()
            ->setStatusCode(201);
    }

    public function show(IntakeForm $intakeForm): IntakeFormResource
    {
        $this->authorize('view', $intakeForm);

        return new IntakeFormResource($intakeForm->load('creator')->loadCount('submissions'));
    }

    public function update(Request $request, IntakeForm $intakeForm): IntakeFormResource
    {
        $this->authorize('update', $intakeForm);

        $intakeForm->update($this->validatedData($request, partial: true));

        return new IntakeFormResource($intakeForm->fresh()->load('creator')->loadCount('submissions'));
    }

    public function destroy(IntakeForm $intakeForm): JsonResponse
    {
        $this->authorize('delete', $intakeForm);

        $intakeForm->delete();

        return response()->json(['message' => 'Intake form deleted successfully.']);
    }

    /**
     * @return array<string, mixed>
     */
    protected function validatedData(Request $request, bool $partial = false): array
    {
        return $request->validate([
            'name' => [$partial ? 'sometimes' : 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'case_type' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'string', Rule::in(IntakeForm::STATUSES)],
            'fields' => [$partial ? 'sometimes' : 'required', 'array'],
            'fields.*.name' => ['required_with:fields', 'string', 'max:100'],
            'fields.*.label' => ['required_with:fields', 'string', 'max:255'],
            'fields.*.type' => ['required_with:fields', 'string', Rule::in([
                'text',
                'long_text',
                'email',
                'phone',
                'date',
                'file',
                'dropdown',
                'checkbox',
                'radio',
                'signature',
                'conditional',
            ])],
            'fields.*.required' => ['nullable', 'boolean'],
            'fields.*.options' => ['nullable', 'array'],
            'fields.*.conditions' => ['nullable', 'array'],
        ]);
    }
}
