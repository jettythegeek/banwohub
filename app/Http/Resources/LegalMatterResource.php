<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Spatie\Activitylog\Models\Activity;

/** @mixin \App\Models\LegalMatter */
class LegalMatterResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'matter_number' => $this->matter_number,
            'practice_area' => $this->practice_area,
            'case_type' => $this->case_type,
            'court_jurisdiction' => $this->court_jurisdiction,
            'status' => $this->status,
            'stage' => $this->stage ?? 'lead',
            'matter_stage' => $this->matter_stage ?? 'intake',
            'priority' => $this->priority,
            'opened_at' => $this->opened_at?->toDateString(),
            'expected_close_at' => $this->expected_close_at?->toDateString(),
            'description' => $this->description,
            'billing_type' => $this->billing_type ?? 'hourly',
            'billing_rate' => $this->billing_rate !== null ? (float) $this->billing_rate : null,
            'fixed_fee_amount' => $this->fixed_fee_amount !== null ? (float) $this->fixed_fee_amount : null,
            'retainer_minimum_amount' => $this->retainer_minimum_amount !== null ? (float) $this->retainer_minimum_amount : null,
            'trust_balance' => $this->trust_balance !== null ? (float) $this->trust_balance : null,
            'tags' => $this->tags ?? [],
            'client' => new ClientResource($this->whenLoaded('client')),
            'lead_lawyer' => $this->whenLoaded('leadLawyer', fn () => [
                'id' => $this->leadLawyer?->id,
                'name' => $this->leadLawyer?->name,
            ]),
            'parties' => $this->whenLoaded('parties', fn () => $this->parties->map(fn ($p) => [
                'id' => $p->id,
                'name' => $p->name,
                'party_type' => $p->party_type,
            ])),
            'assigned_staff' => $this->whenLoaded('assignedStaff', fn () => $this->assignedStaff->map(fn ($u) => [
                'id' => $u->id,
                'name' => $u->name,
                'role' => $u->pivot->role,
            ])),
            'timeline' => Activity::query()
                ->where('subject_type', $this->resource::class)
                ->where('subject_id', $this->id)
                ->latest()
                ->limit(20)
                ->get()
                ->map(fn (Activity $a) => [
                    'id' => $a->id,
                    'description' => $a->description,
                    'event' => $a->event,
                    'properties' => $a->properties,
                    'created_at' => $a->created_at?->toIso8601String(),
                ]),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
