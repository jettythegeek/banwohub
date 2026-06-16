<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\BriefCitation */
class BriefCitationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'legal_brief_id' => $this->legal_brief_id,
            'authority' => $this->authority,
            'citation_text' => $this->citation_text,
            'sort_order' => $this->sort_order,
            'source_note' => $this->source_note,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
