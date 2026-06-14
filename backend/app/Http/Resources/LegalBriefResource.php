<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\LegalBrief */
class LegalBriefResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'legal_matter_id' => $this->legal_matter_id,
            'title' => $this->title,
            'brief_type' => $this->brief_type,
            'jurisdiction' => $this->jurisdiction,
            'court_type' => $this->court_type,
            'cause_of_action' => $this->cause_of_action,
            'case_facts' => $this->case_facts,
            'statutes' => $this->statutes,
            'desired_outcome' => $this->desired_outcome,
            'citation_style' => $this->citation_style,
            'content_html' => $this->content_html,
            'status' => $this->status,
            'last_ai_governance_log_id' => $this->last_ai_governance_log_id,
            'legal_matter' => $this->whenLoaded('legalMatter', fn () => [
                'id' => $this->legalMatter?->id,
                'title' => $this->legalMatter?->title,
                'matter_number' => $this->legalMatter?->matter_number,
            ]),
            'citations' => BriefCitationResource::collection($this->whenLoaded('citations')),
            'creator' => $this->whenLoaded('creator', fn () => [
                'id' => $this->creator?->id,
                'name' => $this->creator?->name,
            ]),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
