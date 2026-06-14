<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\EvidenceItem */
class EvidenceItemResource extends JsonResource
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
            'evidence_type' => $this->evidence_type,
            'source' => $this->source,
            'date_obtained' => $this->date_obtained?->toDateString(),
            'relevance' => $this->relevance,
            'exhibit_number' => $this->exhibit_number,
            'tags' => $this->tags ?? [],
            'status' => $this->status,
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
            'uploader' => $this->whenLoaded('uploader', fn () => [
                'id' => $this->uploader?->id,
                'name' => $this->uploader?->name,
            ]),
            'custody_logs' => EvidenceCustodyLogResource::collection($this->whenLoaded('custodyLogs')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
