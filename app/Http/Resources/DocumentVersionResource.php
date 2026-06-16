<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\DocumentVersion */
class DocumentVersionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'document_id' => $this->document_id,
            'version_number' => $this->version_number,
            'change_summary' => $this->change_summary,
            'source' => $this->source ?? 'human',
            'content_html' => $this->content_html,
            'created_by' => $this->whenLoaded('author', fn () => [
                'id' => $this->author?->id,
                'name' => $this->author?->name,
            ]),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
