<?php

namespace App\Http\Controllers\Api\V1\Portal;

use App\Http\Concerns\ResolvesPortalClient;
use App\Http\Controllers\Controller;
use App\Http\Resources\IntakeFormResource;
use App\Http\Resources\IntakeSubmissionResource;
use App\Models\IntakeForm;
use App\Models\IntakeSubmission;
use App\Services\AutoTaskService;
use App\Support\InAppNotifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PortalIntakeController extends Controller
{
    use ResolvesPortalClient;

    public function index(Request $request): AnonymousResourceCollection
    {
        $client = $this->portalClientFor($request->user());

        $forms = IntakeForm::query()
            ->where('organization_id', $client->organization_id)
            ->where('status', 'published')
            ->latest()
            ->paginate($request->integer('per_page', 25));

        return IntakeFormResource::collection($forms);
    }

    public function show(Request $request, IntakeForm $intakeForm): IntakeFormResource
    {
        $client = $this->portalClientFor($request->user());

        abort_unless(
            $intakeForm->organization_id === $client->organization_id
            && $intakeForm->status === 'published',
            404
        );

        return new IntakeFormResource($intakeForm);
    }

    public function submit(Request $request, IntakeForm $intakeForm, InAppNotifier $notifier, AutoTaskService $autoTasks): JsonResponse
    {
        $user = $request->user();
        $client = $this->portalClientFor($user);

        abort_unless(
            $intakeForm->organization_id === $client->organization_id
            && $intakeForm->status === 'published',
            404
        );

        $data = $request->validate([
            'data' => ['required', 'array'],
            'submitter_phone' => ['nullable', 'string', 'max:50'],
        ]);

        $submission = IntakeSubmission::query()->create([
            'organization_id' => $client->organization_id,
            'intake_form_id' => $intakeForm->id,
            'client_id' => $client->id,
            'submitter_name' => $user->name,
            'submitter_email' => $user->email,
            'submitter_phone' => $data['submitter_phone'] ?? $client->phone,
            'status' => 'submitted',
            'data' => $data['data'],
            'submitted_at' => now(),
        ]);

        $notifier->notifyPermission(
            $client->organization,
            'intake-submissions.view',
            'intake_submitted',
            'Portal intake submitted',
            "{$user->name} submitted {$intakeForm->name}",
            ['intake_submission_id' => $submission->id, 'intake_form_id' => $intakeForm->id, 'client_id' => $client->id],
            $user
        );
        $autoTasks->onIntakeSubmitted($submission, $user);

        return (new IntakeSubmissionResource($submission->load('intakeForm')))
            ->response()
            ->setStatusCode(201);
    }
}
