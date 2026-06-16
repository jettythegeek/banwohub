<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\LegalTask */
class LegalTaskResource extends JsonResource
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
            'status' => $this->status,
            'priority' => $this->priority,
            'due_at' => $this->due_at?->toIso8601String(),
            'checklist' => $this->checklist ?? [],
            'completed_at' => $this->completed_at?->toIso8601String(),
            'case' => $this->whenLoaded('legalMatter', fn () => [
                'id' => $this->legalMatter?->id,
                'title' => $this->legalMatter?->title,
                'matter_number' => $this->legalMatter?->matter_number,
            ]),
            'assignee' => $this->whenLoaded('assignee', fn () => [
                'id' => $this->assignee?->id,
                'name' => $this->assignee?->name,
                'email' => $this->assignee?->email,
            ]),
            'attachments' => TaskAttachmentResource::collection($this->whenLoaded('attachments')),
            'comments' => TaskCommentResource::collection($this->whenLoaded('comments')),
            'attachments_count' => $this->whenCounted('attachments'),
            'comments_count' => $this->whenCounted('comments'),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
