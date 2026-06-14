<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\IntakeSubmission */
class IntakeSubmissionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'intake_form_id' => $this->intake_form_id,
            'submitter_name' => $this->submitter_name,
            'submitter_email' => $this->submitter_email,
            'submitter_phone' => $this->submitter_phone,
            'status' => $this->status,
            'data' => $this->data ?? [],
            'review_notes' => $this->review_notes,
            'submitted_at' => $this->submitted_at?->toIso8601String(),
            'reviewed_at' => $this->reviewed_at?->toIso8601String(),
            'form' => new IntakeFormResource($this->whenLoaded('intakeForm')),
            'reviewer' => $this->whenLoaded('reviewer', fn () => [
                'id' => $this->reviewer?->id,
                'name' => $this->reviewer?->name,
            ]),
            'converted_client' => $this->whenLoaded('convertedClient', fn () => [
                'id' => $this->convertedClient?->id,
                'name' => $this->convertedClient?->name,
            ]),
            'converted_case' => $this->whenLoaded('convertedLegalMatter', fn () => [
                'id' => $this->convertedLegalMatter?->id,
                'title' => $this->convertedLegalMatter?->title,
                'matter_number' => $this->convertedLegalMatter?->matter_number,
            ]),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
