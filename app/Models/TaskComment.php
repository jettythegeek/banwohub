<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaskComment extends Model
{
    protected $fillable = [
        'legal_task_id',
        'user_id',
        'body',
    ];

    public function legalTask(): BelongsTo
    {
        return $this->belongsTo(LegalTask::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
