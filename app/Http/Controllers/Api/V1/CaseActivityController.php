<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Concerns\ResolvesOrganization;
use App\Http\Controllers\Api\V1\Concerns\ValidatesOrganizationAccess;
use App\Http\Controllers\Controller;
use App\Models\LegalMatter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\Activitylog\Models\Activity;

class CaseActivityController extends Controller
{
    use ResolvesOrganization;
    use ValidatesOrganizationAccess;

    public function index(Request $request, LegalMatter $legalMatter): JsonResponse
    {
        $this->authorize('view', $legalMatter);
        $this->legalMatterForOrganization($legalMatter->id, $legalMatter->organization_id);

        $activities = Activity::query()
            ->with('causer:id,name')
            ->where(function ($query) use ($legalMatter) {
                $query->where(function ($q) use ($legalMatter) {
                    $q->where('subject_type', LegalMatter::class)
                        ->where('subject_id', $legalMatter->id);
                })->orWhere(function ($q) use ($legalMatter) {
                    $q->where('properties->legal_matter_id', $legalMatter->id);
                });
            })
            ->latest()
            ->limit($request->integer('limit', 50))
            ->get()
            ->map(fn (Activity $activity) => [
                'id' => $activity->id,
                'description' => $activity->description,
                'event' => $activity->event,
                'log_name' => $activity->log_name,
                'properties' => $activity->properties,
                'actor' => $activity->causer ? [
                    'id' => $activity->causer->id,
                    'name' => $activity->causer->name,
                ] : null,
                'created_at' => $activity->created_at?->toIso8601String(),
            ]);

        return response()->json(['data' => $activities]);
    }
}
