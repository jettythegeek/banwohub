<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Concerns\ResolvesOrganization;
use App\Http\Controllers\Controller;
use App\Services\LegalProjectWorkloadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LegalProjectWorkloadController extends Controller
{
    use ResolvesOrganization;

    public function __construct(
        protected LegalProjectWorkloadService $workloadService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('projects.view'), 403);

        $organization = $this->organizationFor($request->user());

        return response()->json($this->workloadService->summary($organization->id));
    }
}
