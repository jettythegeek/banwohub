<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class LegalDocument extends Model
{
    use SoftDeletes;

    /** Firm-wide reusable templates (not case-scoped). */
    public const ORGANIZATION_TYPES = ['organization_template'];

    /** PRD case document types stored in document_type. */
    public const CASE_DOCUMENT_TYPES = [
        'engagement_letter',
        'pleading',
        'contract',
        'evidence',
        'correspondence',
        'court_filing',
        'discovery',
        'memo',
        'template',
        'case_note',
    ];

    /** @deprecated Legacy uploads — treat as pleading in UI. */
    public const LEGACY_CASE_DOCUMENT = 'case_document';

    public const TYPES = [
        ...self::ORGANIZATION_TYPES,
        ...self::CASE_DOCUMENT_TYPES,
        self::LEGACY_CASE_DOCUMENT,
    ];

    public static function isCaseScopedType(string $type): bool
    {
        return $type === self::LEGACY_CASE_DOCUMENT || in_array($type, self::CASE_DOCUMENT_TYPES, true);
    }

    /** @var list<string> */
    public const AI_REVIEW_STATUSES = [
        'generated',
        'under_review',
        'edited',
        'approved',
        'rejected',
        'finalized',
    ];

    protected $fillable = [
        'organization_id',
        'legal_matter_id',
        'document_folder_id',
        'uploaded_by',
        'checked_out_by',
        'checked_out_at',
        'document_type',
        'name',
        'category',
        'description',
        'content_html',
        'parent_template_id',
        'original_filename',
        'mime_type',
        'size',
        'disk',
        'path',
        'version',
        'client_visible',
        'uploaded_by_client',
        'portal_reviewed_at',
        'ai_generated',
        'ai_review_status',
        'ai_governance_log_id',
        'ai_approved_by',
        'ai_approved_at',
        'requires_approval',
    ];

    protected function casts(): array
    {
        return [
            'client_visible' => 'boolean',
            'uploaded_by_client' => 'boolean',
            'portal_reviewed_at' => 'datetime',
            'ai_generated' => 'boolean',
            'ai_approved_at' => 'datetime',
            'requires_approval' => 'boolean',
            'checked_out_at' => 'datetime',
        ];
    }

    public function isCheckedOut(): bool
    {
        return $this->checked_out_by !== null && $this->checked_out_at !== null;
    }

    public function isCheckedOutBy(User $user): bool
    {
        return $this->isCheckedOut() && (int) $this->checked_out_by === (int) $user->id;
    }

    public function documentFolder(): BelongsTo
    {
        return $this->belongsTo(DocumentFolder::class, 'document_folder_id');
    }

    public function checkedOutBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'checked_out_by');
    }

    public function isAiFinalizable(): bool
    {
        return $this->ai_generated && $this->ai_review_status === 'approved';
    }

    public function canTransitionAiReviewTo(string $status): bool
    {
        if (! $this->ai_generated) {
            return false;
        }

        $current = $this->ai_review_status ?? 'generated';

        return match ($current) {
            'generated', 'edited' => in_array($status, ['under_review'], true),
            'under_review' => in_array($status, ['approved', 'rejected'], true),
            'approved' => $status === 'finalized',
            'rejected' => in_array($status, ['under_review'], true),
            'finalized' => false,
            default => in_array($status, ['under_review'], true),
        };
    }

    public function aiApprover(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ai_approved_by');
    }

    public function aiGovernanceLog(): BelongsTo
    {
        return $this->belongsTo(AiGovernanceLog::class, 'ai_governance_log_id');
    }

    public function isPortalPendingReview(): bool
    {
        return $this->uploaded_by_client && $this->portal_reviewed_at === null;
    }

    public function portalStatus(): string
    {
        if (! $this->uploaded_by_client) {
            return 'staff';
        }

        if ($this->isPortalPendingReview()) {
            return 'pending';
        }

        return $this->client_visible ? 'shared' : 'internal';
    }

    public function parentTemplate(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_template_id');
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function legalMatter(): BelongsTo
    {
        return $this->belongsTo(LegalMatter::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(DocumentVersion::class, 'document_id')->orderByDesc('version_number');
    }

    public function isWordDocument(): bool
    {
        if (! is_string($this->mime_type) || $this->mime_type === '') {
            return false;
        }

        return in_array($this->mime_type, [
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/msword',
        ], true);
    }
}
