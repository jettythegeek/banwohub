<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Concerns\ResolvesOrganization;
use App\Http\Controllers\Api\V1\Concerns\ValidatesOrganizationAccess;
use App\Http\Controllers\Controller;
use App\Http\Resources\TrustLedgerEntryResource;
use App\Models\LegalMatter;
use App\Models\TrustLedgerEntry;
use App\Services\TrustLedgerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class TrustLedgerEntryController extends Controller
{
    use ResolvesOrganization;
    use ValidatesOrganizationAccess;

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', TrustLedgerEntry::class);

        $organization = $this->organizationFor($request->user());

        $query = TrustLedgerEntry::query()
            ->where('organization_id', $organization->id)
            ->when($request->filled('legal_matter_id'), fn ($q) => $q->where('legal_matter_id', $request->integer('legal_matter_id')));

        $entries = (clone $query)
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->paginate($request->integer('per_page', 25));

        return TrustLedgerEntryResource::collection($entries)
            ->additional(['meta' => ['summary' => $this->summarize($request, $organization->id, clone $query)]]);
    }

    public function store(Request $request, TrustLedgerService $trustLedger): JsonResponse
    {
        $this->authorize('create', TrustLedgerEntry::class);

        $user = $request->user();
        $organization = $this->organizationFor($user);
        $data = $this->validatedData($request);

        $legalMatter = $this->legalMatterForOrganization((int) $data['legal_matter_id'], $organization->id);

        $entry = TrustLedgerEntry::query()->create([
            ...$data,
            'organization_id' => $organization->id,
            'occurred_at' => $data['occurred_at'] ?? now(),
        ]);

        $trustLedger->syncMatterBalance($legalMatter);

        return (new TrustLedgerEntryResource($entry))
            ->response()
            ->setStatusCode(201);
    }

    public function destroy(TrustLedgerEntry $trustLedgerEntry, TrustLedgerService $trustLedger): JsonResponse
    {
        $this->authorize('delete', $trustLedgerEntry);

        $legalMatter = $trustLedgerEntry->legalMatter;
        $trustLedgerEntry->delete();

        if ($legalMatter instanceof LegalMatter) {
            $trustLedger->syncMatterBalance($legalMatter);
        }

        return response()->json(['message' => 'Trust ledger entry deleted successfully.']);
    }

    /**
     * @return array<string, mixed>
     */
    protected function validatedData(Request $request): array
    {
        $entryType = $request->string('entry_type')->toString();

        return $request->validate([
            'legal_matter_id' => ['required', 'integer', 'exists:legal_matters,id'],
            'entry_type' => ['required', 'string', Rule::in(TrustLedgerEntry::ENTRY_TYPES)],
            'amount' => [
                'required',
                'numeric',
                $entryType === 'adjustment' ? 'not_in:0' : 'min:0.01',
            ],
            'description' => ['nullable', 'string', 'max:2000'],
            'occurred_at' => ['nullable', 'date'],
        ]);
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<TrustLedgerEntry>  $query
     * @return array<string, mixed>
     */
    protected function summarize(Request $request, int $organizationId, $query): array
    {
        $trustLedger = app(TrustLedgerService::class);

        if ($request->filled('legal_matter_id')) {
            $matterId = $request->integer('legal_matter_id');
            $balance = $trustLedger->balanceForMatter($matterId);
            $matter = LegalMatter::query()
                ->where('organization_id', $organizationId)
                ->find($matterId, ['retainer_minimum_amount']);

            return [
                'entry_count' => $query->count(),
                'balance' => $balance,
                'retainer_minimum' => $matter?->retainer_minimum_amount !== null
                    ? (float) $matter->retainer_minimum_amount
                    : null,
            ];
        }

        return [
            'entry_count' => $query->count(),
        ];
    }
}
