<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\SignatureRequest */
class SignatureRequestResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'organization_id' => $this->organization_id,
            'document_id' => $this->document_id,
            'legal_matter_id' => $this->legal_matter_id,
            'client_id' => $this->client_id,
            'status' => $this->status,
            'fields' => $this->fields ?? [],
            'message' => $this->message,
            'signed_at' => $this->signed_at?->toIso8601String(),
            'signer_ip' => $this->when(
                $request->user()?->client_id === null,
                $this->signer_ip,
            ),
            'audit' => $this->when(
                $request->user()?->client_id === null || $this->status === 'signed',
                $this->audit,
            ),
            'signed_document_id' => $this->signed_document_id,
            'document' => $this->whenLoaded('document', fn () => $this->document ? [
                'id' => $this->document->id,
                'name' => $this->document->name,
                'content_html' => $this->document->content_html,
                'version' => $this->document->version,
                'category' => $this->document->category,
            ] : null),
            'legal_matter' => $this->whenLoaded('legalMatter', fn () => $this->legalMatter ? [
                'id' => $this->legalMatter->id,
                'title' => $this->legalMatter->title,
                'matter_number' => $this->legalMatter->matter_number,
            ] : null),
            'client' => $this->whenLoaded('client', fn () => $this->client ? [
                'id' => $this->client->id,
                'name' => $this->client->name,
                'email' => $this->client->email,
            ] : null),
            'sender' => $this->whenLoaded('sender', fn () => $this->sender ? [
                'id' => $this->sender->id,
                'name' => $this->sender->name,
            ] : null),
            'signed_document' => $this->whenLoaded('signedDocument', fn () => $this->signedDocument ? [
                'id' => $this->signedDocument->id,
                'name' => $this->signedDocument->name,
                'original_filename' => $this->signedDocument->original_filename,
            ] : null),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
