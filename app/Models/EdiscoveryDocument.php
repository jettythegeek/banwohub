<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class EdiscoveryDocument extends Model
{
    use LogsActivity;

    /** @var list<string> */
    public const PRIVILEGES = [
        'none',
        'attorney_client',
        'work_product',
        'privileged',
    ];

    /** @var list<string> */
    public const RELEVANCES = [
        'responsive',
        'non_responsive',
        'hot',
        'needs_review',
    ];

    /** @var list<string> */
    public const REVIEW_STATUSES = [
        'pending',
        'in_progress',
        'reviewed',
        'flagged',
    ];

    /** @var list<string> */
    public const FILE_TYPES = [
        'pdf',
        'email',
        'image',
        'spreadsheet',
        'document',
        'presentation',
        'archive',
        'other',
    ];

    protected $fillable = [
        'organization_id',
        'legal_matter_id',
        'ediscovery_collection_id',
        'title',
        'notes',
        'document_date',
        'sender',
        'recipient',
        'file_type',
        'privilege',
        'relevance',
        'custom_tags',
        'review_status',
        'content_preview',
        'original_filename',
        'mime_type',
        'size',
        'disk',
        'path',
        'uploaded_by',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'document_date' => 'date',
            'custom_tags' => 'array',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['title', 'privilege', 'relevance', 'review_status'])
            ->logOnlyDirty()
            ->useLogName('ediscovery');
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function legalMatter(): BelongsTo
    {
        return $this->belongsTo(LegalMatter::class);
    }

    public function collection(): BelongsTo
    {
        return $this->belongsTo(EdiscoveryCollection::class, 'ediscovery_collection_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function reviewAssignments(): HasMany
    {
        return $this->hasMany(EdiscoveryReviewAssignment::class);
    }

    public function canTransitionReviewStatusTo(string $status): bool
    {
        return match ($this->review_status) {
            'pending' => in_array($status, ['in_progress', 'reviewed', 'flagged'], true),
            'in_progress' => in_array($status, ['reviewed', 'flagged', 'pending'], true),
            'reviewed' => in_array($status, ['flagged', 'in_progress'], true),
            'flagged' => in_array($status, ['in_progress', 'reviewed', 'pending'], true),
            default => false,
        };
    }
}
