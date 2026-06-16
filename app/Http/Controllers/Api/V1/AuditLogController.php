<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Concerns\ResolvesOrganization;
use App\Http\Controllers\Controller;
use App\Http\Resources\AuditLogResource;
use App\Models\Client;
use App\Models\LegalDocument;
use App\Models\LegalMatter;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Spatie\Activitylog\Models\Activity;

class AuditLogController extends Controller
{
    use ResolvesOrganization;

    /** @var array<string, class-string> */
    private const SUBJECT_TYPE_MAP = [
        'case' => LegalMatter::class,
        'client' => Client::class,
        'document' => LegalDocument::class,
        'user' => User::class,
    ];

    public function index(Request $request): AnonymousResourceCollection
    {
        $organization = $this->organizationFor($request->user());

        $orgUserIds = User::query()
            ->where('organization_id', $organization->id)
            ->pluck('id');

        $query = Activity::query()
            ->with('causer:id,name,email')
            ->where(function ($builder) use ($organization, $orgUserIds) {
                $builder->where(function ($q) use ($orgUserIds) {
                    $q->where('causer_type', User::class)
                        ->whereIn('causer_id', $orgUserIds);
                })->orWhere('properties->organization_id', $organization->id);
            })
            ->when($request->filled('user_id'), fn ($q) => $q->where('causer_id', $request->integer('user_id')))
            ->when($request->filled('from_date'), fn ($q) => $q->whereDate('created_at', '>=', $request->date('from_date')))
            ->when($request->filled('to_date'), fn ($q) => $q->whereDate('created_at', '<=', $request->date('to_date')))
            ->when($request->filled('action'), function ($q) use ($request) {
                $action = $request->string('action');
                $q->where(function ($inner) use ($action) {
                    $inner->where('event', $action)
                        ->orWhere('log_name', $action)
                        ->orWhere('description', 'like', '%'.$action.'%');
                });
            })
            ->when($request->filled('subject_type'), function ($q) use ($request) {
                $raw = strtolower((string) $request->string('subject_type'));
                $class = self::SUBJECT_TYPE_MAP[$raw] ?? null;

                if ($class) {
                    $q->where('subject_type', $class);
                } elseif (class_exists($raw)) {
                    $q->where('subject_type', $raw);
                } else {
                    $q->where('log_name', $raw);
                }
            })
            ->latest();

        $logs = $query->paginate($request->integer('per_page', 25));

        return AuditLogResource::collection($logs);
    }
}
