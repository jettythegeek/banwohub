<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommunicationLog extends Model
{
    public const CHANNELS = ['in_app', 'email', 'phone', 'meeting', 'note'];

    protected $fillable = [
        'organization_id',
        'client_id',
        'legal_matter_id',
        'message_thread_id',
        'channel',
        'subject',
        'body',
        'logged_by_user_id',
        'occurred_at',
        'client_feedback',
        'satisfaction_score',
    ];

    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
            'satisfaction_score' => 'integer',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function legalMatter(): BelongsTo
    {
        return $this->belongsTo(LegalMatter::class);
    }

    public function messageThread(): BelongsTo
    {
        return $this->belongsTo(MessageThread::class);
    }

    public function loggedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'logged_by_user_id');
    }
}
