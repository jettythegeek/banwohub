<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Concerns\ResolvesOrganization;
use App\Http\Resources\ClientResource;
use App\Models\Client;
use App\Services\ClientPortalAccountService;
use App\Services\NumberSequenceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;

class ClientController extends Controller
{
    use ResolvesOrganization;

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Client::class);

        $organization = $this->organizationFor($request->user());

        $clients = Client::query()
            ->withCount('legalMatters')
            ->where('organization_id', $organization->id)
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = '%'.$request->string('search').'%';
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', $search)
                        ->orWhere('email', 'like', $search)
                        ->orWhere('company_name', 'like', $search)
                        ->orWhere('client_number', 'like', $search);
                });
            })
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->latest()
            ->paginate($request->integer('per_page', 15));

        return ClientResource::collection($clients);
    }

    public function store(
        Request $request,
        NumberSequenceService $numbers,
        ClientPortalAccountService $portalAccounts,
    ): JsonResponse {
        $this->authorize('create', Client::class);

        $organization = $this->organizationFor($request->user());

        $data = $request->validate([
            'type' => ['nullable', 'string', 'in:individual,company'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'company_name' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string'],
            'status' => ['nullable', 'string', 'in:active,inactive,prospect'],
            'notes' => ['nullable', 'string'],
            ...$this->portalAccountValidationRules($request, creating: true),
        ]);

        if ($request->boolean('create_portal_account') && empty($data['email'])) {
            throw ValidationException::withMessages([
                'email' => ['An email address is required to create a portal account.'],
            ]);
        }

        $portalOptions = $this->extractPortalOptions($request);

        $client = Client::query()->create([
            ...collect($data)->except(['create_portal_account', 'portal_password_option', 'portal_password', 'reset_portal_password'])->all(),
            'organization_id' => $organization->id,
            'client_number' => $numbers->nextClientNumber($organization->id),
            'created_by' => $request->user()->id,
            'type' => $data['type'] ?? 'individual',
            'status' => $data['status'] ?? 'active',
        ]);

        if ($request->boolean('create_portal_account')) {
            $portalAccounts->createPortalAccount($client, $organization, $portalOptions);
        }

        $client->load('portalUser');

        return (new ClientResource($client))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, Client $client): ClientResource
    {
        $this->authorize('view', $client);

        return new ClientResource(
            $client
                ->loadCount([
                    'legalMatters',
                    'legalMatters as open_legal_matters_count' => fn ($q) => $q->where('status', '!=', 'closed'),
                    'invoices',
                    'contacts',
                    'communicationLogs',
                ])
                ->load([
                    'portalUser',
                    'legalMatters' => fn ($q) => $q
                        ->select('id', 'client_id', 'title', 'status', 'matter_number', 'matter_stage', 'created_at')
                        ->latest(),
                ])
        );
    }

    public function update(
        Request $request,
        Client $client,
        ClientPortalAccountService $portalAccounts,
    ): ClientResource {
        $this->authorize('update', $client);

        $organization = $this->organizationFor($request->user());

        $data = $request->validate([
            'type' => ['sometimes', 'string', 'in:individual,company'],
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'company_name' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string'],
            'status' => ['sometimes', 'string', 'in:active,inactive,prospect'],
            'notes' => ['nullable', 'string'],
            ...$this->portalAccountValidationRules($request, creating: false),
        ]);

        $existingPortalUser = $portalAccounts->findPortalUser($client);
        $effectiveEmail = array_key_exists('email', $data) ? $data['email'] : $client->email;

        if ($request->boolean('create_portal_account') && ! $effectiveEmail) {
            throw ValidationException::withMessages([
                'email' => ['An email address is required to create a portal account.'],
            ]);
        }

        if ($request->boolean('create_portal_account') && $existingPortalUser) {
            throw ValidationException::withMessages([
                'create_portal_account' => ['This client already has a portal account.'],
            ]);
        }

        if ($request->boolean('reset_portal_password') && ! $existingPortalUser) {
            throw ValidationException::withMessages([
                'reset_portal_password' => ['This client does not have a portal account.'],
            ]);
        }

        $portalOptions = $this->extractPortalOptions($request);

        $client->update(collect($data)->except([
            'create_portal_account',
            'portal_password_option',
            'portal_password',
            'reset_portal_password',
        ])->all());

        $client = $client->fresh();

        if ($request->boolean('create_portal_account')) {
            $portalAccounts->createPortalAccount($client, $organization, $portalOptions);
        } elseif ($request->boolean('reset_portal_password') && $existingPortalUser) {
            $portalAccounts->resetPortalPassword($existingPortalUser, $client, $organization, $portalOptions);
        }

        $portalUser = $portalAccounts->findPortalUser($client);
        if ($portalUser && array_key_exists('email', $data) && $data['email'] && $data['email'] !== $portalUser->email) {
            $portalAccounts->syncPortalEmail($portalUser, $data['email']);
        }

        return new ClientResource($client->fresh()->load('portalUser'));
    }

    public function destroy(Client $client): JsonResponse
    {
        $this->authorize('delete', $client);

        $client->delete();

        return response()->json(['message' => 'Client deleted successfully.']);
    }

    /**
     * @return array<string, list<mixed>>
     */
    protected function portalAccountValidationRules(Request $request, bool $creating): array
    {
        $portalAction = $creating
            ? $request->boolean('create_portal_account')
            : ($request->boolean('create_portal_account') || $request->boolean('reset_portal_password'));

        return [
            'create_portal_account' => ['sometimes', 'boolean'],
            'reset_portal_password' => ['sometimes', 'boolean'],
            'portal_password_option' => [
                $portalAction ? 'required' : 'nullable',
                'string',
                'in:manual,email',
            ],
            'portal_password' => [
                ($portalAction && $request->input('portal_password_option') === 'manual') ? 'required' : 'nullable',
                'string',
                PasswordRule::defaults(),
            ],
        ];
    }

    /**
     * @return array{portal_password_option: string, portal_password?: string|null}
     */
    protected function extractPortalOptions(Request $request): array
    {
        return [
            'portal_password_option' => (string) $request->input('portal_password_option'),
            'portal_password' => $request->input('portal_password'),
        ];
    }
}
