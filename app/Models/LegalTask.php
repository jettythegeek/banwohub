<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class LegalTask extends Model
{
    use SoftDeletes;

    public const STATUSES = [
        'not_started',
        'in_progress',
        'awaiting_review',
        'completed',
        'blocked',
        'overdue',
    ];

    public const PRIORITIES = ['low', 'normal', 'high', 'urgent'];

    protected $fillable = [
        'organization_id',
        'legal_matter_id',
        'assignee_id',
        'created_by',
        'title',
        'description',
        'status',
        'priority',
        'due_at',
        'checklist',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'due_at' => 'datetime',
            'completed_at' => 'datetime',
            'checklist' => 'array',
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
        return $this->belongsTo(User::class, 'assignee_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(TaskAttachment::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(TaskComment::class);
    }
}
