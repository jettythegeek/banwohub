<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Concerns\ResolvesOrganization;
use App\Http\Controllers\Controller;
use App\Http\Resources\ResearchSavedItemResource;
use App\Models\ResearchSavedItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ResearchSavedItemController extends Controller
{
    use ResolvesOrganization;

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', ResearchSavedItem::class);

        $organization = $this->organizationFor($request->user());

        $items = ResearchSavedItem::query()
            ->with(['entry', 'folder', 'saver:id,name', 'legalMatter:id,title,matter_number'])
            ->where('organization_id', $organization->id)
            ->when($request->filled('legal_matter_id'), fn ($q) => $q->where('legal_matter_id', $request->integer('legal_matter_id')))
            ->when($request->filled('research_folder_id'), fn ($q) => $q->where('research_folder_id', $request->integer('research_folder_id')))
            ->orderByDesc('created_at')
            ->paginate($request->integer('per_page', 25));

        return ResearchSavedItemResource::collection($items);
    }

    public function destroy(ResearchSavedItem $researchSavedItem): JsonResponse
    {
        $this->authorize('delete', $researchSavedItem);

        $researchSavedItem->delete();

        return response()->json(['message' => 'Saved research item removed successfully.']);
    }
}
