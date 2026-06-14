<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Message */
class MessageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'message_thread_id' => $this->message_thread_id,
            'body' => $this->body,
            'read_at' => $this->read_at?->toIso8601String(),
            'attachments' => $this->attachments ?? [],
            'sender' => $this->whenLoaded('sender', fn () => $this->sender ? [
                'id' => $this->sender->id,
                'name' => $this->sender->name,
                'is_client' => $this->sender->client_id !== null,
            ] : null),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
