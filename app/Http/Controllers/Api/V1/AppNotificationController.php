<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Concerns\ResolvesOrganization;
use App\Http\Controllers\Controller;
use App\Http\Resources\AppNotificationResource;
use App\Models\AppNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AppNotificationController extends Controller
{
    use ResolvesOrganization;

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', AppNotification::class);

        $organization = $this->organizationFor($request->user());

        $notifications = AppNotification::query()
            ->with('actor:id,name')
            ->where('organization_id', $organization->id)
            ->where('user_id', $request->user()->id)
            ->when($request->boolean('unread'), fn ($q) => $q->whereNull('read_at'))
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->string('type')))
            ->latest()
            ->paginate($request->integer('per_page', 20));

        return AppNotificationResource::collection($notifications);
    }

    public function show(AppNotification $notification): AppNotificationResource
    {
        $this->authorize('view', $notification);

        return new AppNotificationResource($notification->load('actor'));
    }

    public function markRead(AppNotification $notification): AppNotificationResource
    {
        $this->authorize('update', $notification);

        $notification->update(['read_at' => $notification->read_at ?? now()]);

        return new AppNotificationResource($notification->fresh()->load('actor'));
    }

    public function markAllRead(Request $request): JsonResponse
    {
        $this->authorize('viewAny', AppNotification::class);

        $organization = $this->organizationFor($request->user());

        $count = AppNotification::query()
            ->where('organization_id', $organization->id)
            ->where('user_id', $request->user()->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json(['updated' => $count]);
    }
}
