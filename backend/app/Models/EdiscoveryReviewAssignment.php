<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EdiscoveryReviewAssignment extends Model
{
    /** @var list<string> */
    public const STATUSES = [
        'assigned',
        'in_progress',
        'completed',
        'skipped',
    ];

    protected $fillable = [
        'organization_id',
        'ediscovery_document_id',
        'reviewer_id',
        'review_status',
        'notes',
        'assigned_by',
        'assigned_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'assigned_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(EdiscoveryDocument::class, 'ediscovery_document_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }

    public function assigner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }
}
