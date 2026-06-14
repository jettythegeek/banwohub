<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Concerns\ResolvesOrganization;
use App\Http\Controllers\Api\V1\Concerns\ValidatesOrganizationAccess;
use App\Http\Controllers\Controller;
use App\Http\Resources\ClientContactResource;
use App\Models\ClientContact;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class ClientContactController extends Controller
{
    use ResolvesOrganization;
    use ValidatesOrganizationAccess;

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', ClientContact::class);

        $organization = $this->organizationFor($request->user());

        $contacts = ClientContact::query()
            ->with('client:id,name,organization_id')
            ->whereHas('client', fn ($q) => $q->where('organization_id', $organization->id))
            ->when($request->filled('client_id'), fn ($q) => $q->where('client_id', $request->integer('client_id')))
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->string('type')))
            ->orderBy('type')
            ->orderBy('name')
            ->paginate($request->integer('per_page', 50));

        return ClientContactResource::collection($contacts);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', ClientContact::class);

        $organization = $this->organizationFor($request->user());
        $data = $this->validatedData($request);

        $this->clientForOrganization((int) $data['client_id'], $organization->id);

        $contact = ClientContact::query()->create($data);

        return (new ClientContactResource($contact->load('client:id,name')))
            ->response()
            ->setStatusCode(201);
    }

    public function show(ClientContact $clientContact): ClientContactResource
    {
        $this->authorize('view', $clientContact);

        return new ClientContactResource($clientContact->load('client:id,name'));
    }

    public function update(Request $request, ClientContact $clientContact): ClientContactResource
    {
        $this->authorize('update', $clientContact);

        $organization = $this->organizationFor($request->user());
        $data = $this->validatedData($request, partial: true);

        if (array_key_exists('client_id', $data)) {
            $this->clientForOrganization((int) $data['client_id'], $organization->id);
        }

        $clientContact->update($data);

        return new ClientContactResource($clientContact->fresh()->load('client:id,name'));
    }

    public function destroy(ClientContact $clientContact): JsonResponse
    {
        $this->authorize('delete', $clientContact);

        $clientContact->delete();

        return response()->json(['message' => 'Contact deleted.']);
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedData(Request $request, bool $partial = false): array
    {
        return $request->validate([
            'client_id' => [$partial ? 'sometimes' : 'required', 'integer', 'exists:clients,id'],
            'type' => [$partial ? 'sometimes' : 'required', 'string', Rule::in(ClientContact::TYPES)],
            'name' => [$partial ? 'sometimes' : 'required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'title' => ['nullable', 'string', 'max:255'],
        ]);
    }
}
