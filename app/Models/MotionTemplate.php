<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MotionTemplate extends Model
{
    protected $fillable = [
        'organization_id',
        'slug',
        'name',
        'description',
        'structure_html',
        'required_sections',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'required_sections' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function motions(): HasMany
    {
        return $this->hasMany(LegalMotion::class);
    }
}
