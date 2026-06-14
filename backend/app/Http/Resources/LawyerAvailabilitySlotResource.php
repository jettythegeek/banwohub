<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\LawyerAvailabilitySlot */
class LawyerAvailabilitySlotResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'day_of_week' => $this->day_of_week,
            'start_time' => substr((string) $this->start_time, 0, 5),
            'end_time' => substr((string) $this->end_time, 0, 5),
            'slot_duration_minutes' => $this->slot_duration_minutes,
            'consultation_types' => $this->consultation_types ?? [],
            'consultation_fee' => $this->consultation_fee,
            'location' => $this->location,
            'online_meeting' => $this->online_meeting,
            'is_active' => $this->is_active,
            'user' => $this->whenLoaded('user', fn () => [
                'id' => $this->user?->id,
                'name' => $this->user?->name,
            ]),
        ];
    }
}
