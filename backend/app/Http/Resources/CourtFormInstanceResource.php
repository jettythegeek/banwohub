<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\CourtFormInstance */
class CourtFormInstanceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'legal_matter_id' => $this->legal_matter_id,
            'court_form_template_id' => $this->court_form_template_id,
            'court_filing_id' => $this->court_filing_id,
            'title' => $this->title,
            'field_values' => $this->field_values ?? [],
            'status' => $this->status,
            'template' => $this->whenLoaded('template', fn () => new CourtFormTemplateResource($this->template)),
            'legal_matter' => $this->whenLoaded('legalMatter', fn () => [
                'id' => $this->legalMatter?->id,
                'title' => $this->legalMatter?->title,
                'matter_number' => $this->legalMatter?->matter_number,
            ]),
            'court_filing' => $this->whenLoaded('courtFiling', fn () => new CourtFilingResource($this->courtFiling)),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
