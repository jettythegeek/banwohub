<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\ResearchProject */
class ResearchProjectResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'legal_matter_id' => $this->legal_matter_id,
            'name' => $this->name,
            'description' => $this->description,
            'case_theory' => $this->case_theory,
            'jurisdiction' => $this->jurisdiction,
            'practice_area' => $this->practice_area,
            'legal_matter' => $this->whenLoaded('legalMatter', fn () => [
                'id' => $this->legalMatter?->id,
                'title' => $this->legalMatter?->title,
                'matter_number' => $this->legalMatter?->matter_number,
            ]),
            'chat_messages_count' => $this->whenCounted('chatMessages'),
            'creator' => $this->whenLoaded('creator', fn () => [
                'id' => $this->creator?->id,
                'name' => $this->creator?->name,
            ]),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
