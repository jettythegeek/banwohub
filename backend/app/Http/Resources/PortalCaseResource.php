<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\LegalMatter */
class PortalCaseResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'matter_number' => $this->matter_number,
            'practice_area' => $this->practice_area,
            'case_type' => $this->case_type,
            'court_jurisdiction' => $this->court_jurisdiction,
            'status' => $this->status,
            'priority' => $this->priority,
            'opened_at' => $this->opened_at?->toDateString(),
            'expected_close_at' => $this->expected_close_at?->toDateString(),
            'description' => $this->description,
            'lead_lawyer' => $this->whenLoaded('leadLawyer', fn () => $this->leadLawyer ? [
                'id' => $this->leadLawyer->id,
                'name' => $this->leadLawyer->name,
            ] : null),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
