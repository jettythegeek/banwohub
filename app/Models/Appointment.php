<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Appointment extends Model
{
    use SoftDeletes;

    public const CONSULTATION_TYPES = LawyerAvailabilitySlot::CONSULTATION_TYPES;

    public const STATUSES = ['pending', 'confirmed', 'cancelled', 'completed'];

    public const PAYMENT_STATUSES = ['none', 'pending', 'paid'];

    protected $fillable = [
        'organization_id',
        'calendar_event_id',
        'client_id',
        'user_id',
        'legal_matter_id',
        'booked_by_user_id',
        'consultation_type',
        'status',
        'starts_at',
        'ends_at',
        'location',
        'online_meeting',
        'fee',
        'payment_status',
        'notes',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'online_meeting' => 'boolean',
            'fee' => 'decimal:2',
            'metadata' => 'array',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function calendarEvent(): BelongsTo
    {
        return $this->belongsTo(CalendarEvent::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function lawyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function legalMatter(): BelongsTo
    {
        return $this->belongsTo(LegalMatter::class);
    }

    public function bookedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'booked_by_user_id');
    }

    public function isPaidType(): bool
    {
        return in_array($this->consultation_type, ['paid_consultation'], true)
            && $this->fee !== null
            && (float) $this->fee > 0;
    }
}
