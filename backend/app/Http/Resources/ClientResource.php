<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Client */
class ClientResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'client_number' => $this->client_number,
            'type' => $this->type,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'company_name' => $this->company_name,
            'address' => $this->address,
            'status' => $this->status,
            'notes' => $this->notes,
            'legal_matters_count' => $this->whenCounted('legalMatters'),
            'open_legal_matters_count' => $this->whenCounted('open_legal_matters_count'),
            'invoices_count' => $this->whenCounted('invoices'),
            'contacts_count' => $this->whenCounted('contacts'),
            'communication_logs_count' => $this->whenCounted('communicationLogs'),
            'legal_matters' => $this->whenLoaded('legalMatters', fn () => $this->legalMatters->map(fn ($m) => [
                'id' => $m->id,
                'title' => $m->title,
                'status' => $m->status,
                'matter_number' => $m->matter_number,
                'matter_stage' => $m->matter_stage,
                'created_at' => $m->created_at?->toIso8601String(),
            ])),
            'portal' => $this->when(
                $this->relationLoaded('portalUser'),
                fn () => [
                    'has_account' => $this->portalUser !== null,
                    'login_email' => $this->portalUser?->email,
                    'is_active' => $this->portalUser?->is_active,
                ],
            ),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
