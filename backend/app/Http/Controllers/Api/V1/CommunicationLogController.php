<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Concerns\ResolvesOrganization;
use App\Http\Controllers\Api\V1\Concerns\ValidatesOrganizationAccess;
use App\Http\Controllers\Controller;
use App\Http\Resources\CommunicationLogResource;
use App\Models\CommunicationLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class CommunicationLogController extends Controller
{
    use ResolvesOrganization;
    use ValidatesOrganizationAccess;

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', CommunicationLog::class);

        $organization = $this->organizationFor($request->user());

        $logs = CommunicationLog::query()
            ->with([
                'legalMatter:id,title,matter_number',
                'loggedBy:id,name',
            ])
            ->where('organization_id', $organization->id)
            ->when($request->filled('client_id'), fn ($q) => $q->where('client_id', $request->integer('client_id')))
            ->when($request->filled('legal_matter_id'), fn ($q) => $q->where('legal_matter_id', $request->integer('legal_matter_id')))
            ->when($request->filled('channel'), fn ($q) => $q->where('channel', $request->string('channel')))
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->paginate($request->integer('per_page', 25));

        return CommunicationLogResource::collection($logs);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', CommunicationLog::class);

        $user = $request->user();
        $organization = $this->organizationFor($user);
        $data = $this->validatedData($request);

        $client = $this->clientForOrganization((int) $data['client_id'], $organization->id);

        if (! empty($data['legal_matter_id'])) {
            $matter = $this->legalMatterForOrganization((int) $data['legal_matter_id'], $organization->id);
            abort_unless($matter->client_id === $client->id, 422, 'Case does not belong to this client.');
        }

        $log = CommunicationLog::query()->create([
            'organization_id' => $organization->id,
            'client_id' => $client->id,
            'legal_matter_id' => $data['legal_matter_id'] ?? null,
            'channel' => $data['channel'],
            'subject' => $data['subject'] ?? null,
            'body' => $data['body'] ?? null,
            'logged_by_user_id' => $user->id,
            'occurred_at' => $data['occurred_at'] ?? now(),
            'client_feedback' => $data['client_feedback'] ?? null,
            'satisfaction_score' => $data['satisfaction_score'] ?? null,
        ]);

        return (new CommunicationLogResource($log->load(['legalMatter', 'loggedBy'])))
            ->response()
            ->setStatusCode(201);
    }

    public function show(CommunicationLog $communicationLog): CommunicationLogResource
    {
        $this->authorize('view', $communicationLog);

        return new CommunicationLogResource(
            $communicationLog->load(['legalMatter', 'loggedBy'])
        );
    }

    public function update(Request $request, CommunicationLog $communicationLog): CommunicationLogResource
    {
        $this->authorize('update', $communicationLog);

        $organization = $communicationLog->organization_id;
        $data = $this->validatedData($request, partial: true);

        if (array_key_exists('legal_matter_id', $data) && $data['legal_matter_id'] !== null) {
            $matter = $this->legalMatterForOrganization((int) $data['legal_matter_id'], $organization);
            abort_unless($matter->client_id === $communicationLog->client_id, 422, 'Case does not belong to this client.');
        }

        $communicationLog->update($data);

        return new CommunicationLogResource($communicationLog->load(['legalMatter', 'loggedBy']));
    }

    public function destroy(CommunicationLog $communicationLog): JsonResponse
    {
        $this->authorize('delete', $communicationLog);

        $communicationLog->delete();

        return response()->json(['deleted' => true]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedData(Request $request, bool $partial = false): array
    {
        $rules = [
            'client_id' => [$partial ? 'sometimes' : 'required', 'integer'],
            'legal_matter_id' => ['nullable', 'integer'],
            'channel' => [$partial ? 'sometimes' : 'required', 'string', Rule::in(CommunicationLog::CHANNELS)],
            'subject' => ['nullable', 'string', 'max:255'],
            'body' => ['nullable', 'string', 'max:10000'],
            'occurred_at' => ['nullable', 'date'],
            'client_feedback' => ['nullable', 'string', 'max:5000'],
            'satisfaction_score' => ['nullable', 'integer', 'min:1', 'max:5'],
        ];

        return $request->validate($rules);
    }
}
