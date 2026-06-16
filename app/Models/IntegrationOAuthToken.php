<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IntegrationOAuthToken extends Model
{
    protected $table = 'integration_oauth_tokens';

    public const PROVIDER_GOOGLE_CALENDAR = 'google_calendar';

    protected $fillable = [
        'organization_id',
        'user_id',
        'provider',
        'access_token',
        'refresh_token',
        'expires_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
