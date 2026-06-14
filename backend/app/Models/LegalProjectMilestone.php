<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LegalProjectMilestone extends Model
{
    /** @var list<string> */
    public const MILESTONE_TYPES = [
        'client_onboarding',
        'research_completed',
        'draft_prepared',
        'document_reviewed',
        'filing_completed',
        'hearing_attended',
        'negotiation_completed',
        'matter_closed',
        'custom',
    ];

    /** @var list<string> */
    public const STATUSES = ['pending', 'in_progress', 'completed', 'overdue'];

    protected $fillable = [
        'organization_id',
        'legal_matter_id',
        'title',
        'description',
        'milestone_type',
        'status',
        'due_at',
        'completed_at',
        'assigned_to',
        'sort_order',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'due_at' => 'date',
            'completed_at' => 'datetime',
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

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
