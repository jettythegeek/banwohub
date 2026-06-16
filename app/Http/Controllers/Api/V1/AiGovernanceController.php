<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Concerns\ResolvesOrganization;
use App\Http\Controllers\Controller;
use App\Http\Resources\AiGovernanceLogResource;
use App\Models\AiGovernanceLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AiGovernanceController extends Controller
{
    use ResolvesOrganization;

    public function settings(): JsonResponse
    {
        return response()->json([
            'disclaimer' => config('ai.disclaimer'),
            'label' => config('ai.label'),
            'review_statuses' => config('ai.review_statuses'),
            'requires_lawyer_approval' => true,
        ]);
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', AiGovernanceLog::class);

        $organization = $this->organizationFor($request->user());

        $logs = AiGovernanceLog::query()
            ->with('user:id,name')
            ->where('organization_id', $organization->id)
            ->when($request->filled('action_type'), fn ($q) => $q->where('action_type', $request->string('action_type')))
            ->when($request->filled('user_id'), fn ($q) => $q->where('user_id', $request->integer('user_id')))
            ->latest()
            ->paginate($request->integer('per_page', 20));

        return AiGovernanceLogResource::collection($logs);
    }
}
