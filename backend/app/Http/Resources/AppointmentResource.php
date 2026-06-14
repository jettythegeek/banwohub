<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Appointment */
class AppointmentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'calendar_event_id' => $this->calendar_event_id,
            'client_id' => $this->client_id,
            'user_id' => $this->user_id,
            'legal_matter_id' => $this->legal_matter_id,
            'booked_by_user_id' => $this->booked_by_user_id,
            'consultation_type' => $this->consultation_type,
            'status' => $this->status,
            'starts_at' => $this->starts_at?->toIso8601String(),
            'ends_at' => $this->ends_at?->toIso8601String(),
            'location' => $this->location,
            'online_meeting' => $this->online_meeting,
            'fee' => $this->fee,
            'payment_status' => $this->payment_status,
            'notes' => $this->notes,
            'metadata' => $this->metadata ?? [],
            'lawyer' => $this->whenLoaded('lawyer', fn () => [
                'id' => $this->lawyer?->id,
                'name' => $this->lawyer?->name,
            ]),
            'client' => $this->whenLoaded('client', fn () => [
                'id' => $this->client?->id,
                'name' => $this->client?->name,
            ]),
            'case' => $this->whenLoaded('legalMatter', fn () => [
                'id' => $this->legalMatter?->id,
                'title' => $this->legalMatter?->title,
                'matter_number' => $this->legalMatter?->matter_number,
            ]),
            'calendar_event' => $this->whenLoaded('calendarEvent', fn () => new CalendarEventResource($this->calendarEvent)),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
