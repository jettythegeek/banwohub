<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\MessageThread */
class MessageThreadResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $user = $request->user();

        return [
            'id' => $this->id,
            'subject' => $this->subject,
            'client_id' => $this->client_id,
            'legal_matter_id' => $this->legal_matter_id,
            'last_message_at' => $this->last_message_at?->toIso8601String(),
            'unread_count' => $user ? $this->unreadCountFor($user) : 0,
            'client' => $this->whenLoaded('client', fn () => $this->client ? [
                'id' => $this->client->id,
                'name' => $this->client->name,
                'email' => $this->client->email,
            ] : null),
            'case' => $this->whenLoaded('legalMatter', fn () => $this->legalMatter ? [
                'id' => $this->legalMatter->id,
                'title' => $this->legalMatter->title,
                'matter_number' => $this->legalMatter->matter_number,
            ] : null),
            'creator' => $this->whenLoaded('creator', fn () => $this->creator ? [
                'id' => $this->creator->id,
                'name' => $this->creator->name,
            ] : null),
            'latest_message' => $this->whenLoaded('latestMessage', function () {
                return $this->latestMessage
                    ? (new MessageResource($this->latestMessage->loadMissing('sender')))->resolve()
                    : null;
            }),
            'messages' => MessageResource::collection($this->whenLoaded('messages')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
