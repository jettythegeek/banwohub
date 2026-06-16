<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class LegalMotion extends Model
{
    use LogsActivity;

    /** @var list<string> */
    public const STATUSES = ['draft', 'review', 'approved', 'filing_ready'];

    protected $fillable = [
        'organization_id',
        'legal_matter_id',
        'motion_template_id',
        'title',
        'motion_type',
        'content_html',
        'status',
        'court_filing_id',
        'last_ai_governance_log_id',
        'created_by',
        'updated_by',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['title', 'status', 'motion_type'])
            ->logOnlyDirty()
            ->useLogName('motion');
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function legalMatter(): BelongsTo
    {
        return $this->belongsTo(LegalMatter::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(MotionTemplate::class, 'motion_template_id');
    }

    public function courtFiling(): BelongsTo
    {
        return $this->belongsTo(CourtFiling::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function lastAiGovernanceLog(): BelongsTo
    {
        return $this->belongsTo(AiGovernanceLog::class, 'last_ai_governance_log_id');
    }

    public function canTransitionTo(string $status): bool
    {
        return match ($this->status) {
            'draft' => $status === 'review',
            'review' => in_array($status, ['approved', 'draft'], true),
            'approved' => $status === 'filing_ready',
            'filing_ready' => false,
            default => false,
        };
    }

    public function isReadOnly(): bool
    {
        return $this->status === 'filing_ready';
    }
}
