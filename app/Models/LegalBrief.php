<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class LegalBrief extends Model
{
    use LogsActivity;

    /** @var list<string> */
    public const STATUSES = ['draft', 'review', 'final'];

    /** @var list<string> */
    public const AUTHORITY_TYPES = ['case', 'statute', 'regulation', 'other'];

    /** @var list<string> */
    public const BRIEF_TYPES = [
        'motion_to_dismiss',
        'summary_judgment',
        'motion_to_reopen',
        'motion_to_compel',
        'continuance',
        'appellate_brief',
        'trial_brief',
        'memorandum_of_law',
        'opposition_brief',
        'reply_brief',
        'admin_agency_brief',
        'immigration_court_brief',
        'federal_court_brief',
        'state_court_brief',
    ];

    /** @var list<string> */
    public const COURT_TYPES = ['federal', 'state', 'immigration', 'admin', 'appellate'];

    /** @var list<string> */
    public const CITATION_STYLES = ['bluebook', 'alwd', 'state_specific'];

    protected $fillable = [
        'organization_id',
        'legal_matter_id',
        'title',
        'brief_type',
        'jurisdiction',
        'court_type',
        'cause_of_action',
        'case_facts',
        'statutes',
        'desired_outcome',
        'citation_style',
        'content_html',
        'status',
        'last_ai_governance_log_id',
        'created_by',
        'updated_by',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['title', 'status'])
            ->logOnlyDirty()
            ->useLogName('brief');
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function legalMatter(): BelongsTo
    {
        return $this->belongsTo(LegalMatter::class);
    }

    public function citations(): HasMany
    {
        return $this->hasMany(BriefCitation::class)->orderBy('sort_order');
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
            'review' => in_array($status, ['final', 'draft'], true),
            'final' => false,
            default => false,
        };
    }
}
