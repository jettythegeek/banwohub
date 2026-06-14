<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\LegalDocument */
class PortalDocumentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'legal_matter_id' => $this->legal_matter_id,
            'name' => $this->name,
            'category' => $this->category,
            'description' => $this->description,
            'original_filename' => $this->original_filename,
            'mime_type' => $this->mime_type,
            'size' => $this->size,
            'client_visible' => $this->client_visible,
            'uploaded_by_client' => $this->uploaded_by_client,
            'portal_reviewed_at' => $this->portal_reviewed_at?->toIso8601String(),
            'portal_pending_review' => $this->isPortalPendingReview(),
            'portal_status' => $this->portalStatus(),
            'download_url' => url("/api/v1/portal/documents/{$this->id}/download"),
            'case' => $this->whenLoaded('legalMatter', fn () => $this->legalMatter ? [
                'id' => $this->legalMatter->id,
                'title' => $this->legalMatter->title,
                'matter_number' => $this->legalMatter->matter_number,
            ] : null),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
