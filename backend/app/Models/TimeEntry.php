<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class TimeEntry extends Model
{
    use SoftDeletes;

    public const STATUSES = ['draft', 'submitted', 'approved'];

    protected $fillable = [
        'organization_id',
        'user_id',
        'legal_matter_id',
        'legal_task_id',
        'invoice_id',
        'created_by',
        'approved_by',
        'description',
        'started_at',
        'ended_at',
        'duration_minutes',
        'billable',
        'rate',
        'status',
        'is_running',
        'approved_at',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'approved_at' => 'datetime',
            'duration_minutes' => 'integer',
            'billable' => 'boolean',
            'is_running' => 'boolean',
            'rate' => 'decimal:2',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function legalMatter(): BelongsTo
    {
        return $this->belongsTo(LegalMatter::class);
    }

    public function legalTask(): BelongsTo
    {
        return $this->belongsTo(LegalTask::class, 'legal_task_id');
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Billable amount for this entry, or null when no rate is set.
     */
    public function amount(): ?float
    {
        if ($this->rate === null) {
            return null;
        }

        return round(((float) $this->rate) * ($this->duration_minutes / 60), 2);
    }
}
