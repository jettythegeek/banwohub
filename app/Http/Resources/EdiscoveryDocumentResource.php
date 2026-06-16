<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\EdiscoveryDocument */
class EdiscoveryDocumentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'legal_matter_id' => $this->legal_matter_id,
            'ediscovery_collection_id' => $this->ediscovery_collection_id,
            'title' => $this->title,
            'notes' => $this->notes,
            'document_date' => $this->document_date?->toDateString(),
            'sender' => $this->sender,
            'recipient' => $this->recipient,
            'file_type' => $this->file_type,
            'privilege' => $this->privilege,
            'relevance' => $this->relevance,
            'custom_tags' => $this->custom_tags ?? [],
            'review_status' => $this->review_status,
            'content_preview' => $this->content_preview,
            'original_filename' => $this->original_filename,
            'mime_type' => $this->mime_type,
            'size' => $this->size,
            'has_file' => ! empty($this->path),
            'uploaded_by' => $this->uploaded_by,
            'legal_matter' => $this->whenLoaded('legalMatter', fn () => [
                'id' => $this->legalMatter?->id,
                'title' => $this->legalMatter?->title,
                'matter_number' => $this->legalMatter?->matter_number,
            ]),
            'collection' => $this->whenLoaded('collection', fn () => [
                'id' => $this->collection?->id,
                'name' => $this->collection?->name,
            ]),
            'uploader' => $this->whenLoaded('uploader', fn () => [
                'id' => $this->uploader?->id,
                'name' => $this->uploader?->name,
            ]),
            'review_assignments' => EdiscoveryReviewAssignmentResource::collection($this->whenLoaded('reviewAssignments')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
