<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\EvidenceCustodyLog */
class EvidenceCustodyLogResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'evidence_item_id' => $this->evidence_item_id,
            'action' => $this->action,
            'notes' => $this->notes,
            'location' => $this->location,
            'from_user_id' => $this->from_user_id,
            'to_user_id' => $this->to_user_id,
            'logged_by' => $this->logged_by,
            'logged_at' => $this->logged_at?->toIso8601String(),
            'from_user' => $this->whenLoaded('fromUser', fn () => [
                'id' => $this->fromUser?->id,
                'name' => $this->fromUser?->name,
            ]),
            'to_user' => $this->whenLoaded('toUser', fn () => [
                'id' => $this->toUser?->id,
                'name' => $this->toUser?->name,
            ]),
            'logger' => $this->whenLoaded('logger', fn () => [
                'id' => $this->logger?->id,
                'name' => $this->logger?->name,
            ]),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
