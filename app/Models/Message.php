<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Message extends Model
{
    protected $fillable = [
        'message_thread_id',
        'sender_user_id',
        'body',
        'read_at',
        'attachments',
    ];

    protected function casts(): array
    {
        return [
            'read_at' => 'datetime',
            'attachments' => 'array',
        ];
    }

    public function thread(): BelongsTo
    {
        return $this->belongsTo(MessageThread::class, 'message_thread_id');
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_user_id');
    }
}
