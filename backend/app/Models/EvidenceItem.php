<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class EvidenceItem extends Model
{
    use LogsActivity;

    /** @var list<string> */
    public const STATUSES = [
        'uploaded',
        'under_review',
        'approved',
        'rejected',
        'marked_as_exhibit',
        'filed',
        'archived',
    ];

    /** @var list<string> */
    public const EVIDENCE_TYPES = [
        'pdf',
        'image',
        'video',
        'audio',
        'email',
        'scanned_document',
        'statement',
        'contract',
        'receipt',
        'screenshot',
        'other',
    ];

    protected $fillable = [
        'organization_id',
        'legal_matter_id',
        'title',
        'description',
        'evidence_type',
        'source',
        'date_obtained',
        'relevance',
        'exhibit_number',
        'tags',
        'status',
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
            'date_obtained' => 'date',
            'tags' => 'array',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['title', 'status', 'exhibit_number', 'evidence_type'])
            ->logOnlyDirty()
            ->useLogName('evidence');
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

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function custodyLogs(): HasMany
    {
        return $this->hasMany(EvidenceCustodyLog::class)->orderByDesc('logged_at');
    }

    public function canTransitionTo(string $status): bool
    {
        return match ($this->status) {
            'uploaded' => in_array($status, ['under_review', 'archived'], true),
            'under_review' => in_array($status, ['approved', 'rejected', 'uploaded'], true),
            'approved' => in_array($status, ['marked_as_exhibit', 'rejected', 'under_review'], true),
            'rejected' => in_array($status, ['uploaded', 'archived'], true),
            'marked_as_exhibit' => in_array($status, ['filed', 'approved', 'archived'], true),
            'filed' => $status === 'archived',
            'archived' => false,
            default => false,
        };
    }
}
