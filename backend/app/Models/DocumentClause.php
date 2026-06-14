<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentClause extends Model
{
    public const CATEGORIES = [
        'general',
        'confidentiality',
        'indemnity',
        'termination',
        'governing_law',
        'dispute_resolution',
        'payment',
        'engagement',
        'correspondence',
    ];

    protected $fillable = [
        'organization_id',
        'title',
        'category',
        'body_html',
        'tags',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'tags' => 'array',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
