<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApprovalRequest extends Model
{
    public const SUBJECT_TYPES = ['legal_document', 'invoice'];

    /** @var list<string> */
    public const STATUSES = [
        'draft',
        'submitted',
        'changes_requested',
        'approved',
        'rejected',
        'finalized',
    ];

    protected $fillable = [
        'organization_id',
        'subject_type',
        'subject_id',
        'status',
        'requires_approval',
        'submitted_by',
        'reviewer_id',
        'comments',
        'notes',
        'submitted_at',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'requires_approval' => 'boolean',
            'comments' => 'array',
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }

    public function resolveSubject(): ?Model
    {
        return match ($this->subject_type) {
            'legal_document' => LegalDocument::query()->find($this->subject_id),
            'invoice' => Invoice::query()->find($this->subject_id),
            default => null,
        };
    }

    public static function latestForSubject(string $subjectType, int $subjectId): ?self
    {
        return self::query()
            ->where('subject_type', $subjectType)
            ->where('subject_id', $subjectId)
            ->latest()
            ->first();
    }

    public function isApproved(): bool
    {
        return in_array($this->status, ['approved', 'finalized'], true);
    }

    public function canTransitionTo(string $status): bool
    {
        return match ($this->status) {
            'draft' => $status === 'submitted',
            'submitted' => in_array($status, ['approved', 'rejected', 'changes_requested'], true),
            'changes_requested' => $status === 'submitted',
            'rejected' => $status === 'submitted',
            'approved' => $status === 'finalized',
            default => false,
        };
    }
}
