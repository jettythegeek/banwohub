<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LawyerAvailabilitySlot extends Model
{
    public const CONSULTATION_TYPES = [
        'free_consultation',
        'paid_consultation',
        'case_review',
        'client_meeting',
        'court_preparation',
        'internal_meeting',
    ];

    protected $fillable = [
        'organization_id',
        'user_id',
        'day_of_week',
        'start_time',
        'end_time',
        'slot_duration_minutes',
        'consultation_types',
        'consultation_fee',
        'location',
        'online_meeting',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'consultation_types' => 'array',
            'consultation_fee' => 'decimal:2',
            'online_meeting' => 'boolean',
            'is_active' => 'boolean',
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
