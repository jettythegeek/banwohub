<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Concerns\ResolvesOrganization;
use App\Http\Controllers\Controller;
use App\Services\LegalAnalyticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class LegalAnalyticsController extends Controller
{
    use ResolvesOrganization;

    public function __construct(
        protected LegalAnalyticsService $analyticsService,
    ) {}

    public function dashboard(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('analytics.view'), 403);

        $organization = $this->organizationFor($request->user());
        $filters = $request->validate([
            'from_date' => ['nullable', 'date'],
            'to_date' => ['nullable', 'date', 'after_or_equal:from_date'],
        ]);

        return response()->json($this->analyticsService->dashboard(
            $organization->id,
            isset($filters['from_date']) ? Carbon::parse($filters['from_date'])->toDateString() : null,
            isset($filters['to_date']) ? Carbon::parse($filters['to_date'])->toDateString() : null,
        ));
    }

    public function hints(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('analytics.view'), 403);

        $organization = $this->organizationFor($request->user());

        return response()->json($this->analyticsService->hints($organization->id));
    }
}
