<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentVersion extends Model
{
    public const SOURCES = ['human', 'ai', 'system'];

    protected $fillable = [
        'document_id',
        'content_html',
        'version_number',
        'created_by',
        'change_summary',
        'source',
    ];

    public function document(): BelongsTo
    {
        return $this->belongsTo(LegalDocument::class, 'document_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
