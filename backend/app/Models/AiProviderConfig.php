<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiProviderConfig extends Model
{
    protected $fillable = [
        'organization_id',
        'provider',
        'api_key',
        'is_enabled',
        'model',
        'settings',
        'last_test_success_at',
    ];

    protected function casts(): array
    {
        return [
            'api_key' => 'encrypted',
            'is_enabled' => 'boolean',
            'settings' => 'array',
            'last_test_success_at' => 'datetime',
        ];
    }

    public function hasSuccessfulTest(): bool
    {
        return $this->last_test_success_at !== null;
    }

    public function canSelectModel(): bool
    {
        return $this->api_key !== null
            && $this->api_key !== ''
            && $this->hasSuccessfulTest();
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function resolvedModel(): string
    {
        if ($this->model) {
            return $this->model;
        }

        /** @var string|null $default */
        $default = config("ai.providers.{$this->provider}.default_model");

        return $default ?? 'unknown';
    }
}
