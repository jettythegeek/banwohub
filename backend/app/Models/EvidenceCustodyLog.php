<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EvidenceCustodyLog extends Model
{
    /** @var list<string> */
    public const ACTIONS = [
        'received',
        'transferred',
        'reviewed',
        'copied',
        'exported',
        'checked_out',
        'checked_in',
    ];

    protected $fillable = [
        'organization_id',
        'evidence_item_id',
        'action',
        'notes',
        'location',
        'from_user_id',
        'to_user_id',
        'logged_by',
        'logged_at',
    ];

    protected function casts(): array
    {
        return [
            'logged_at' => 'datetime',
        ];
    }

    public function evidenceItem(): BelongsTo
    {
        return $this->belongsTo(EvidenceItem::class);
    }

    public function fromUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'from_user_id');
    }

    public function toUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'to_user_id');
    }

    public function logger(): BelongsTo
    {
        return $this->belongsTo(User::class, 'logged_by');
    }
}
