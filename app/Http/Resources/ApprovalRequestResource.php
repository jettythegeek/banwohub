<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\ApprovalRequest */
class ApprovalRequestResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'organization_id' => $this->organization_id,
            'subject_type' => $this->subject_type,
            'subject_id' => $this->subject_id,
            'status' => $this->status,
            'requires_approval' => $this->requires_approval,
            'notes' => $this->notes,
            'comments' => $this->comments ?? [],
            'submitted_at' => $this->submitted_at?->toIso8601String(),
            'reviewed_at' => $this->reviewed_at?->toIso8601String(),
            'submitter' => $this->whenLoaded('submitter', fn () => $this->submitter ? [
                'id' => $this->submitter->id,
                'name' => $this->submitter->name,
            ] : null),
            'reviewer' => $this->whenLoaded('reviewer', fn () => $this->reviewer ? [
                'id' => $this->reviewer->id,
                'name' => $this->reviewer->name,
            ] : null),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
