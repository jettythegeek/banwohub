<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\ResearchChatMessage */
class ResearchChatMessageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'research_project_id' => $this->research_project_id,
            'role' => $this->role,
            'content' => $this->content,
            'ai_governance_log_id' => $this->ai_governance_log_id,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
