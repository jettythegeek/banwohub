<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaskAttachment extends Model
{
    protected $fillable = [
        'legal_task_id',
        'uploaded_by',
        'name',
        'path',
        'disk',
        'mime_type',
        'size',
    ];

    public function legalTask(): BelongsTo
    {
        return $this->belongsTo(LegalTask::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
