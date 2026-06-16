<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\LegalDocument */
class LegalDocumentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'legal_matter_id' => $this->legal_matter_id,
            'document_folder_id' => $this->document_folder_id,
            'document_folder' => $this->whenLoaded('documentFolder', fn () => [
                'id' => $this->documentFolder?->id,
                'name' => $this->documentFolder?->name,
            ]),
            'checked_out_at' => $this->checked_out_at?->toIso8601String(),
            'is_checked_out' => $this->isCheckedOut(),
            'checked_out_by' => $this->whenLoaded('checkedOutBy', fn () => $this->checkedOutBy ? [
                'id' => $this->checkedOutBy->id,
                'name' => $this->checkedOutBy->name,
            ] : null),
            'document_type' => $this->document_type,
            'name' => $this->name,
            'category' => $this->category,
            'description' => $this->description,
            'content_html' => $this->content_html,
            'parent_template_id' => $this->parent_template_id,
            'parent_template' => $this->whenLoaded('parentTemplate', fn () => [
                'id' => $this->parentTemplate?->id,
                'name' => $this->parentTemplate?->name,
            ]),
            'original_filename' => $this->original_filename,
            'mime_type' => $this->mime_type,
            'size' => $this->size,
            'version' => $this->version,
            'client_visible' => $this->client_visible,
            'uploaded_by_client' => $this->uploaded_by_client,
            'portal_reviewed_at' => $this->portal_reviewed_at?->toIso8601String(),
            'portal_pending_review' => $this->isPortalPendingReview(),
            'ai_generated' => $this->ai_generated,
            'ai_review_status' => $this->ai_review_status,
            'ai_governance_log_id' => $this->ai_governance_log_id,
            'ai_approved_at' => $this->ai_approved_at?->toIso8601String(),
            'ai_approved_by' => $this->whenLoaded('aiApprover', fn () => [
                'id' => $this->aiApprover?->id,
                'name' => $this->aiApprover?->name,
            ]),
            'requires_approval' => (bool) $this->requires_approval,
            'download_url' => url("/api/v1/documents/{$this->id}/download"),
            'case' => $this->whenLoaded('legalMatter', fn () => [
                'id' => $this->legalMatter?->id,
                'title' => $this->legalMatter?->title,
                'matter_number' => $this->legalMatter?->matter_number,
            ]),
            'uploaded_by' => $this->whenLoaded('uploader', fn () => [
                'id' => $this->uploader?->id,
                'name' => $this->uploader?->name,
            ]),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
