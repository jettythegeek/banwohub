<?php

namespace App\Http\Resources;

use App\Models\CalendarEvent;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\CalendarEvent */
class CalendarEventResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'legal_matter_id' => $this->legal_matter_id,
            'user_id' => $this->user_id,
            'event_type' => $this->event_type,
            'category' => CalendarEvent::categoryForType($this->event_type),
            'hearing_type' => $this->hearing_type,
            'hearing_status' => $this->hearing_status,
            'deadline_subtype' => $this->deadline_subtype,
            'title' => $this->title,
            'description' => $this->description,
            'starts_at' => $this->starts_at?->toIso8601String(),
            'ends_at' => $this->ends_at?->toIso8601String(),
            'location' => $this->location,
            'court_name' => $this->court_name,
            'court_room' => $this->court_room,
            'judge_name' => $this->judge_name,
            'reminder_at' => $this->reminder_at?->toIso8601String(),
            'reminder_days_before' => $this->reminder_days_before,
            'metadata' => $this->metadata ?? [],
            'case' => $this->whenLoaded('legalMatter', fn () => [
                'id' => $this->legalMatter?->id,
                'title' => $this->legalMatter?->title,
                'matter_number' => $this->legalMatter?->matter_number,
            ]),
            'user' => $this->whenLoaded('user', fn () => [
                'id' => $this->user?->id,
                'name' => $this->user?->name,
            ]),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
