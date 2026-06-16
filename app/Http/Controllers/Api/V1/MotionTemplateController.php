<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Concerns\ResolvesOrganization;
use App\Http\Controllers\Controller;
use App\Http\Resources\MotionTemplateResource;
use App\Models\MotionTemplate;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class MotionTemplateController extends Controller
{
    use ResolvesOrganization;

    public function index(Request $request): AnonymousResourceCollection
    {
        abort_unless($request->user()?->can('motions.view'), 403);

        $organization = $this->organizationFor($request->user());

        $templates = MotionTemplate::query()
            ->where('is_active', true)
            ->where(function ($query) use ($organization): void {
                $query->whereNull('organization_id')
                    ->orWhere('organization_id', $organization->id);
            })
            ->orderBy('name')
            ->get();

        return MotionTemplateResource::collection($templates);
    }
}
