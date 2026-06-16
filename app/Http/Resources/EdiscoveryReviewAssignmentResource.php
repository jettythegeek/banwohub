<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\EdiscoveryReviewAssignment */
class EdiscoveryReviewAssignmentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'ediscovery_document_id' => $this->ediscovery_document_id,
            'reviewer_id' => $this->reviewer_id,
            'review_status' => $this->review_status,
            'notes' => $this->notes,
            'assigned_at' => $this->assigned_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'reviewer' => $this->whenLoaded('reviewer', fn () => [
                'id' => $this->reviewer?->id,
                'name' => $this->reviewer?->name,
            ]),
            'assigner' => $this->whenLoaded('assigner', fn () => [
                'id' => $this->assigner?->id,
                'name' => $this->assigner?->name,
            ]),
            'document' => $this->whenLoaded('document', fn () => [
                'id' => $this->document?->id,
                'title' => $this->document?->title,
                'review_status' => $this->document?->review_status,
            ]),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
