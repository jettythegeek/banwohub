<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Concerns\ResolvesOrganization;
use App\Http\Controllers\Controller;
use App\Services\TaskWorkloadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TaskWorkloadController extends Controller
{
    use ResolvesOrganization;

    public function __construct(
        protected TaskWorkloadService $workloadService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('tasks.view'), 403);

        $organization = $this->organizationFor($request->user());

        return response()->json($this->workloadService->board($organization->id));
    }
}
