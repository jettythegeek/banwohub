<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Concerns\ResolvesOrganization;
use App\Http\Controllers\Api\V1\Concerns\ValidatesOrganizationAccess;
use App\Http\Controllers\Controller;
use App\Http\Resources\LawyerAvailabilitySlotResource;
use App\Models\LawyerAvailabilitySlot;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class LawyerAvailabilityController extends Controller
{
    use ResolvesOrganization;
    use ValidatesOrganizationAccess;

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', LawyerAvailabilitySlot::class);

        $organization = $this->organizationFor($request->user());
        $userId = $request->integer('user_id') ?: $request->user()->id;

        if ($userId !== $request->user()->id && ! $request->user()->can('users.manage')) {
            abort(403);
        }

        $slots = LawyerAvailabilitySlot::query()
            ->with('user:id,name')
            ->where('organization_id', $organization->id)
            ->where('user_id', $userId)
            ->orderBy('day_of_week')
            ->orderBy('start_time')
            ->get();

        return LawyerAvailabilitySlotResource::collection($slots);
    }

    public function update(Request $request): JsonResponse
    {
        $organization = $this->organizationFor($request->user());
        $targetUserId = $request->integer('user_id') ?: $request->user()->id;

        abort_unless(
            $request->user()->can('appointments.manage-availability')
            && ($targetUserId === $request->user()->id || $request->user()->can('users.manage')),
            403
        );

        $this->userForOrganization($targetUserId, $organization->id);

        $data = $request->validate([
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'slots' => ['required', 'array'],
            'slots.*.day_of_week' => ['required', 'integer', 'between:0,6'],
            'slots.*.start_time' => ['required', 'date_format:H:i'],
            'slots.*.end_time' => ['required', 'date_format:H:i', 'after:slots.*.start_time'],
            'slots.*.slot_duration_minutes' => ['nullable', 'integer', 'min:15', 'max:240'],
            'slots.*.consultation_types' => ['nullable', 'array'],
            'slots.*.consultation_types.*' => ['string', Rule::in(LawyerAvailabilitySlot::CONSULTATION_TYPES)],
            'slots.*.consultation_fee' => ['nullable', 'numeric', 'min:0'],
            'slots.*.location' => ['nullable', 'string', 'max:255'],
            'slots.*.online_meeting' => ['nullable', 'boolean'],
            'slots.*.is_active' => ['nullable', 'boolean'],
        ]);

        $slots = DB::transaction(function () use ($organization, $targetUserId, $data) {
            LawyerAvailabilitySlot::query()
                ->where('organization_id', $organization->id)
                ->where('user_id', $targetUserId)
                ->delete();

            $created = [];
            foreach ($data['slots'] as $slot) {
                $created[] = LawyerAvailabilitySlot::query()->create([
                    'organization_id' => $organization->id,
                    'user_id' => $targetUserId,
                    'day_of_week' => $slot['day_of_week'],
                    'start_time' => $slot['start_time'].':00',
                    'end_time' => $slot['end_time'].':00',
                    'slot_duration_minutes' => $slot['slot_duration_minutes'] ?? 30,
                    'consultation_types' => $slot['consultation_types'] ?? ['free_consultation', 'client_meeting'],
                    'consultation_fee' => $slot['consultation_fee'] ?? null,
                    'location' => $slot['location'] ?? null,
                    'online_meeting' => $slot['online_meeting'] ?? false,
                    'is_active' => $slot['is_active'] ?? true,
                ]);
            }

            return $created;
        });

        return LawyerAvailabilitySlotResource::collection(collect($slots))
            ->response()
            ->setStatusCode(200);
    }
}
