<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\LegalMotion */
class LegalMotionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'legal_matter_id' => $this->legal_matter_id,
            'motion_template_id' => $this->motion_template_id,
            'title' => $this->title,
            'motion_type' => $this->motion_type,
            'content_html' => $this->content_html,
            'status' => $this->status,
            'court_filing_id' => $this->court_filing_id,
            'last_ai_governance_log_id' => $this->last_ai_governance_log_id,
            'legal_matter' => $this->whenLoaded('legalMatter', fn () => [
                'id' => $this->legalMatter?->id,
                'title' => $this->legalMatter?->title,
                'matter_number' => $this->legalMatter?->matter_number,
            ]),
            'template' => $this->whenLoaded('template', fn () => new MotionTemplateResource($this->template)),
            'court_filing' => $this->whenLoaded('courtFiling', fn () => new CourtFilingResource($this->courtFiling)),
            'creator' => $this->whenLoaded('creator', fn () => [
                'id' => $this->creator?->id,
                'name' => $this->creator?->name,
            ]),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
