<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Organization */
class OrganizationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'legal_name' => $this->legal_name,
            'email' => $this->email,
            'phone' => $this->phone,
            'address' => $this->address,
            'logo_path' => $this->logo_path,
            'practice_areas' => $this->practice_areas ?? [],
            'case_types' => $this->case_types ?? [],
            'jurisdictions' => $this->jurisdictions ?? [],
            'settings' => $this->settings ?? [],
        ];
    }
}
