<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\LegalProjectMilestone */
class LegalProjectMilestoneResource extends JsonResource
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
            'description' => $this->description,
            'milestone_type' => $this->milestone_type,
            'status' => $this->status,
            'due_at' => $this->due_at?->toDateString(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'assigned_to' => $this->assigned_to,
            'sort_order' => $this->sort_order,
            'assignee' => $this->whenLoaded('assignee', fn () => $this->assignee ? [
                'id' => $this->assignee->id,
                'name' => $this->assignee->name,
            ] : null),
            'legal_matter' => $this->whenLoaded('legalMatter', fn () => $this->legalMatter ? [
                'id' => $this->legalMatter->id,
                'title' => $this->legalMatter->title,
                'matter_number' => $this->legalMatter->matter_number,
            ] : null),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
