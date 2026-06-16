<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Concerns\ResolvesOrganization;
use App\Http\Controllers\Api\V1\Concerns\ValidatesOrganizationAccess;
use App\Http\Controllers\Controller;
use App\Http\Resources\IntakeSubmissionResource;
use App\Models\Client;
use App\Models\IntakeSubmission;
use App\Models\LegalMatter;
use App\Services\AutoTaskService;
use App\Support\InAppNotifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class IntakeSubmissionController extends Controller
{
    use ResolvesOrganization;
    use ValidatesOrganizationAccess;

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', IntakeSubmission::class);

        $organization = $this->organizationFor($request->user());

        $submissions = IntakeSubmission::query()
            ->with(['intakeForm:id,name,case_type', 'reviewer:id,name', 'convertedClient:id,name', 'convertedLegalMatter:id,title,matter_number'])
            ->where('organization_id', $organization->id)
            ->when($request->filled('intake_form_id'), fn ($q) => $q->where('intake_form_id', $request->integer('intake_form_id')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->latest()
            ->paginate($request->integer('per_page', 15));

        return IntakeSubmissionResource::collection($submissions);
    }

    public function store(Request $request, InAppNotifier $notifier, AutoTaskService $autoTasks): JsonResponse
    {
        $this->authorize('create', IntakeSubmission::class);

        $organization = $this->organizationFor($request->user());
        $data = $this->validatedData($request);
        $form = $this->intakeFormForOrganization((int) $data['intake_form_id'], $organization->id);
        $status = $data['status'] ?? 'submitted';

        $submission = IntakeSubmission::query()->create([
            ...$data,
            'organization_id' => $organization->id,
            'intake_form_id' => $form->id,
            'status' => $status,
            'submitted_at' => $status === 'draft' ? null : now(),
        ]);

        if ($status === 'submitted') {
            $notifier->notifyPermission(
                $organization,
                'intake-submissions.view',
                'intake_submitted',
                'New intake submitted',
                $submission->submitter_name ?: $form->name,
                ['intake_submission_id' => $submission->id, 'intake_form_id' => $form->id],
                $request->user()
            );
            $autoTasks->onIntakeSubmitted($submission, $request->user());
        }

        return (new IntakeSubmissionResource($submission->load('intakeForm')))
            ->response()
            ->setStatusCode(201);
    }

    public function show(IntakeSubmission $intakeSubmission): IntakeSubmissionResource
    {
        $this->authorize('view', $intakeSubmission);

        return new IntakeSubmissionResource($intakeSubmission->load(['intakeForm', 'reviewer', 'convertedClient', 'convertedLegalMatter']));
    }

    public function update(Request $request, IntakeSubmission $intakeSubmission, InAppNotifier $notifier, AutoTaskService $autoTasks): IntakeSubmissionResource
    {
        $this->authorize('update', $intakeSubmission);

        $previousStatus = $intakeSubmission->status;
        $data = $this->validatedData($request, partial: true);
        if (isset($data['intake_form_id'])) {
            $this->intakeFormForOrganization((int) $data['intake_form_id'], $intakeSubmission->organization_id);
        }
        if (isset($data['status']) && $data['status'] !== $intakeSubmission->status) {
            if (! in_array($data['status'], ['draft', 'submitted'], true)) {
                $data['reviewed_by'] = $request->user()->id;
                $data['reviewed_at'] = now();
            }
            if ($data['status'] === 'submitted') {
                $data['submitted_at'] = now();
            }
        }

        $intakeSubmission->update($data);

        if (($data['status'] ?? null) === 'submitted' && $previousStatus !== 'submitted') {
            $notifier->notifyPermission(
                $intakeSubmission->organization,
                'intake-submissions.view',
                'intake_submitted',
                'New intake submitted',
                $intakeSubmission->submitter_name ?: $intakeSubmission->intakeForm?->name,
                ['intake_submission_id' => $intakeSubmission->id, 'intake_form_id' => $intakeSubmission->intake_form_id],
                $request->user()
            );
            $autoTasks->onIntakeSubmitted($intakeSubmission, $request->user());
        }

        return new IntakeSubmissionResource($intakeSubmission->fresh()->load(['intakeForm', 'reviewer', 'convertedClient', 'convertedLegalMatter']));
    }

    public function approve(Request $request, IntakeSubmission $intakeSubmission): IntakeSubmissionResource
    {
        $this->authorize('update', $intakeSubmission);

        $intakeSubmission->update([
            'status' => 'approved',
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
            'review_notes' => $request->input('review_notes'),
        ]);

        return new IntakeSubmissionResource($intakeSubmission->fresh()->load(['intakeForm', 'reviewer', 'convertedClient', 'convertedLegalMatter']));
    }

    public function reject(Request $request, IntakeSubmission $intakeSubmission): IntakeSubmissionResource
    {
        $this->authorize('update', $intakeSubmission);

        $data = $request->validate(['review_notes' => ['nullable', 'string']]);
        $intakeSubmission->update([
            'status' => 'rejected',
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
            'review_notes' => $data['review_notes'] ?? null,
        ]);

        return new IntakeSubmissionResource($intakeSubmission->fresh()->load(['intakeForm', 'reviewer', 'convertedClient', 'convertedLegalMatter']));
    }

    public function requestInfo(Request $request, IntakeSubmission $intakeSubmission): IntakeSubmissionResource
    {
        $this->authorize('update', $intakeSubmission);

        $data = $request->validate(['review_notes' => ['required', 'string']]);
        $intakeSubmission->update([
            'status' => 'more_info_requested',
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
            'review_notes' => $data['review_notes'],
        ]);

        return new IntakeSubmissionResource($intakeSubmission->fresh()->load(['intakeForm', 'reviewer', 'convertedClient', 'convertedLegalMatter']));
    }

    public function convert(Request $request, IntakeSubmission $intakeSubmission): IntakeSubmissionResource
    {
        $this->authorize('convert', $intakeSubmission);

        abort_if($intakeSubmission->converted_legal_matter_id, 422, 'Submission has already been converted.');

        $data = $request->validate([
            'client.name' => ['nullable', 'string', 'max:255'],
            'client.email' => ['nullable', 'email', 'max:255'],
            'client.phone' => ['nullable', 'string', 'max:50'],
            'client.type' => ['nullable', 'string', 'in:individual,company'],
            'client.company_name' => ['nullable', 'string', 'max:255'],
            'client.address' => ['nullable', 'string'],
            'case.title' => ['nullable', 'string', 'max:255'],
            'case.practice_area' => ['nullable', 'string', 'max:100'],
            'case.case_type' => ['nullable', 'string', 'max:100'],
            'case.description' => ['nullable', 'string'],
            'case.lead_lawyer_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        $payload = $intakeSubmission->data ?? [];
        $clientInput = $data['client'] ?? [];
        $caseInput = $data['case'] ?? [];

        if (isset($caseInput['lead_lawyer_id'])) {
            $this->userForOrganization((int) $caseInput['lead_lawyer_id'], $intakeSubmission->organization_id);
        }

        DB::transaction(function () use ($request, $intakeSubmission, $payload, $clientInput, $caseInput): void {
            $client = Client::query()->create([
                'organization_id' => $intakeSubmission->organization_id,
                'type' => $clientInput['type'] ?? 'individual',
                'name' => $clientInput['name'] ?? $intakeSubmission->submitter_name ?? $payload['name'] ?? 'Intake Client '.$intakeSubmission->id,
                'email' => $clientInput['email'] ?? $intakeSubmission->submitter_email ?? $payload['email'] ?? null,
                'phone' => $clientInput['phone'] ?? $intakeSubmission->submitter_phone ?? $payload['phone'] ?? null,
                'company_name' => $clientInput['company_name'] ?? $payload['company_name'] ?? null,
                'address' => $clientInput['address'] ?? $payload['address'] ?? null,
                'status' => 'active',
                'notes' => 'Created from intake submission #'.$intakeSubmission->id,
                'created_by' => $request->user()->id,
            ]);

            $matter = LegalMatter::query()->create([
                'organization_id' => $intakeSubmission->organization_id,
                'client_id' => $client->id,
                'title' => $caseInput['title'] ?? $payload['case_title'] ?? $payload['matter_title'] ?? 'Intake Matter '.$intakeSubmission->id,
                'matter_number' => 'INTAKE-'.$intakeSubmission->id,
                'practice_area' => $caseInput['practice_area'] ?? $payload['practice_area'] ?? null,
                'case_type' => $caseInput['case_type'] ?? $intakeSubmission->intakeForm?->case_type ?? $payload['case_type'] ?? null,
                'status' => 'new',
                'priority' => 'normal',
                'description' => $caseInput['description'] ?? $payload['description'] ?? null,
                'lead_lawyer_id' => $caseInput['lead_lawyer_id'] ?? null,
                'created_by' => $request->user()->id,
            ]);

            $intakeSubmission->update([
                'status' => 'approved',
                'reviewed_by' => $request->user()->id,
                'reviewed_at' => now(),
                'converted_client_id' => $client->id,
                'converted_legal_matter_id' => $matter->id,
            ]);
        });

        return new IntakeSubmissionResource($intakeSubmission->fresh()->load(['intakeForm', 'reviewer', 'convertedClient', 'convertedLegalMatter']));
    }

    /**
     * @return array<string, mixed>
     */
    protected function validatedData(Request $request, bool $partial = false): array
    {
        return $request->validate([
            'intake_form_id' => [$partial ? 'sometimes' : 'required', 'integer', 'exists:intake_forms,id'],
            'submitter_name' => ['nullable', 'string', 'max:255'],
            'submitter_email' => ['nullable', 'email', 'max:255'],
            'submitter_phone' => ['nullable', 'string', 'max:50'],
            'status' => ['nullable', 'string', Rule::in(IntakeSubmission::STATUSES)],
            'data' => [$partial ? 'sometimes' : 'required', 'array'],
            'review_notes' => ['nullable', 'string'],
        ]);
    }
}
