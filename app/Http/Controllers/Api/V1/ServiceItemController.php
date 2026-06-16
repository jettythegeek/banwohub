<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Concerns\ResolvesOrganization;
use App\Http\Controllers\Controller;
use App\Http\Resources\ServiceItemResource;
use App\Models\ServiceItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ServiceItemController extends Controller
{
    use ResolvesOrganization;

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', ServiceItem::class);

        $organization = $this->organizationFor($request->user());

        $items = ServiceItem::query()
            ->where('organization_id', $organization->id)
            ->when($request->boolean('active_only', true), fn ($q) => $q->where('is_active', true))
            ->when($request->filled('search'), function ($q) use ($request) {
                $search = '%'.$request->string('search').'%';
                $q->where(function ($inner) use ($search) {
                    $inner->where('name', 'like', $search)
                        ->orWhere('description', 'like', $search);
                });
            })
            ->orderBy('name')
            ->paginate($request->integer('per_page', 100));

        return ServiceItemResource::collection($items);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', ServiceItem::class);

        $organization = $this->organizationFor($request->user());
        $data = $this->validatedData($request);

        $item = ServiceItem::query()->create([
            ...$data,
            'organization_id' => $organization->id,
        ]);

        return (new ServiceItemResource($item))
            ->response()
            ->setStatusCode(201);
    }

    public function show(ServiceItem $serviceItem): ServiceItemResource
    {
        $this->authorize('view', $serviceItem);

        return new ServiceItemResource($serviceItem);
    }

    public function update(Request $request, ServiceItem $serviceItem): ServiceItemResource
    {
        $this->authorize('update', $serviceItem);

        $serviceItem->update($this->validatedData($request, partial: true));

        return new ServiceItemResource($serviceItem->fresh());
    }

    public function destroy(ServiceItem $serviceItem): JsonResponse
    {
        $this->authorize('delete', $serviceItem);

        $serviceItem->update(['is_active' => false]);

        return response()->json(['message' => 'Service item archived.']);
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedData(Request $request, bool $partial = false): array
    {
        return $request->validate([
            'name' => [$partial ? 'sometimes' : 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'default_rate' => ['nullable', 'numeric', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ]);
    }
}
