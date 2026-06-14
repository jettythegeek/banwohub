<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class CalendarEvent extends Model
{
    use SoftDeletes;

    public const TYPES = [
        'court_hearing',
        'filing_deadline',
        'client_meeting',
        'internal_meeting',
        'appointment',
        'document_review_deadline',
        'payment_due_date',
        'limitation_deadline',
        'follow_up_reminder',
    ];

    public const HEARING_TYPES = [
        'motion',
        'trial',
        'deposition',
        'arraignment',
        'status_conference',
        'sentencing',
        'mediation',
        'other',
    ];

    public const HEARING_STATUSES = [
        'scheduled',
        'confirmed',
        'continued',
        'completed',
        'cancelled',
    ];

    public const DEADLINE_SUBTYPES = [
        'deadline',
        'court_date',
        'meeting',
        'reminder',
    ];

    public const DEADLINE_EVENT_TYPES = [
        'filing_deadline',
        'document_review_deadline',
        'payment_due_date',
        'limitation_deadline',
        'follow_up_reminder',
    ];

    protected $fillable = [
        'organization_id',
        'legal_matter_id',
        'user_id',
        'created_by',
        'event_type',
        'hearing_type',
        'hearing_status',
        'deadline_subtype',
        'title',
        'description',
        'starts_at',
        'ends_at',
        'location',
        'court_name',
        'court_room',
        'judge_name',
        'reminder_at',
        'reminder_days_before',
        'reminder_sent_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'reminder_at' => 'datetime',
            'reminder_sent_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function legalMatter(): BelongsTo
    {
        return $this->belongsTo(LegalMatter::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public static function categoryForType(string $eventType): string
    {
        if ($eventType === 'court_hearing') {
            return 'hearing';
        }

        if ($eventType === 'appointment') {
            return 'appointment';
        }

        if (in_array($eventType, self::DEADLINE_EVENT_TYPES, true)) {
            return 'deadline';
        }

        return 'meeting';
    }

    public function applyReminderDaysBefore(): void
    {
        if ($this->reminder_days_before === null || $this->reminder_at !== null || ! $this->starts_at) {
            return;
        }

        $this->reminder_at = $this->starts_at->copy()->subDays($this->reminder_days_before);
    }
}
