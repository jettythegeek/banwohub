<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\CommunicationLog */
class CommunicationLogResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'client_id' => $this->client_id,
            'legal_matter_id' => $this->legal_matter_id,
            'message_thread_id' => $this->message_thread_id,
            'channel' => $this->channel,
            'subject' => $this->subject,
            'body' => $this->body,
            'occurred_at' => $this->occurred_at?->toIso8601String(),
            'client_feedback' => $this->client_feedback,
            'satisfaction_score' => $this->satisfaction_score,
            'case' => $this->whenLoaded('legalMatter', fn () => $this->legalMatter ? [
                'id' => $this->legalMatter->id,
                'title' => $this->legalMatter->title,
                'matter_number' => $this->legalMatter->matter_number,
            ] : null),
            'logged_by' => $this->whenLoaded('loggedBy', fn () => $this->loggedBy ? [
                'id' => $this->loggedBy->id,
                'name' => $this->loggedBy->name,
            ] : null),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
