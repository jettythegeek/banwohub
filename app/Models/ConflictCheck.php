<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ConflictCheck extends Model
{
    use SoftDeletes;

    public const STATUSES = [
        'not_started',
        'in_review',
        'potential_conflict_found',
        'cleared',
        'rejected',
    ];

    protected $fillable = [
        'organization_id',
        'legal_matter_id',
        'intake_submission_id',
        'requested_by',
        'reviewer_id',
        'status',
        'search_terms',
        'matches',
        'report',
        'decision',
        'notes',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'search_terms' => 'array',
            'matches' => 'array',
            'report' => 'array',
            'reviewed_at' => 'datetime',
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

    public function intakeSubmission(): BelongsTo
    {
        return $this->belongsTo(IntakeSubmission::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }
}
