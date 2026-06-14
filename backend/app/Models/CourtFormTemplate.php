<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CourtFormTemplate extends Model
{
    protected $fillable = [
        'organization_id',
        'name',
        'jurisdiction',
        'court',
        'case_type',
        'filing_type',
        'fields',
        'guidance',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'fields' => 'array',
            'guidance' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function instances(): HasMany
    {
        return $this->hasMany(CourtFormInstance::class);
    }

    public function scopeAvailableToOrganization($query, int $organizationId)
    {
        return $query->where(function ($q) use ($organizationId): void {
            $q->whereNull('organization_id')
                ->orWhere('organization_id', $organizationId);
        })->where('is_active', true);
    }
}
