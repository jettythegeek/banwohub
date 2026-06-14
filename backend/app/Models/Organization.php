<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Organization extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'legal_name',
        'email',
        'phone',
        'address',
        'logo_path',
        'practice_areas',
        'case_types',
        'jurisdictions',
        'settings',
    ];

    protected function casts(): array
    {
        return [
            'practice_areas' => 'array',
            'case_types' => 'array',
            'jurisdictions' => 'array',
            'settings' => 'array',
        ];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function clients(): HasMany
    {
        return $this->hasMany(Client::class);
    }

    public function legalMatters(): HasMany
    {
        return $this->hasMany(LegalMatter::class);
    }

    public function aiProviderConfigs(): HasMany
    {
        return $this->hasMany(AiProviderConfig::class);
    }
}
