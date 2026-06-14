<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\AiGovernanceLog */
class AiGovernanceLogResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'action_type' => $this->action_type,
            'bot_context' => $this->bot_context,
            'legal_matter_id' => $this->legal_matter_id,
            'legal_document_id' => $this->legal_document_id,
            'output_id' => $this->output_id,
            'model' => $this->model,
            'status' => $this->status,
            'output_preview' => $this->output_preview,
            'prompt_context' => $this->prompt_context,
            'metadata' => $this->metadata,
            'user' => $this->whenLoaded('user', fn () => [
                'id' => $this->user?->id,
                'name' => $this->user?->name,
            ]),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
