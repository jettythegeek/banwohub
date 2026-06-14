<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\CourtFiling */
class CourtFilingResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'legal_matter_id' => $this->legal_matter_id,
            'court_form_instance_id' => $this->court_form_instance_id,
            'legal_motion_id' => $this->legal_motion_id,
            'title' => $this->title,
            'court' => $this->court,
            'filing_date' => $this->filing_date?->toDateString(),
            'filed_by' => $this->filed_by,
            'filing_method' => $this->filing_method,
            'court_reference_number' => $this->court_reference_number,
            'document_ids' => $this->document_ids ?? [],
            'status' => $this->status,
            'court_response' => $this->court_response,
            'notes' => $this->notes,
            'correction_deadline' => $this->correction_deadline?->toDateString(),
            'legal_matter' => $this->whenLoaded('legalMatter', fn () => [
                'id' => $this->legalMatter?->id,
                'title' => $this->legalMatter?->title,
                'matter_number' => $this->legalMatter?->matter_number,
            ]),
            'filed_by_user' => $this->whenLoaded('filedByUser', fn () => [
                'id' => $this->filedByUser?->id,
                'name' => $this->filedByUser?->name,
            ]),
            'court_form_instance' => $this->whenLoaded('courtFormInstance', fn () => new CourtFormInstanceResource($this->courtFormInstance)),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
