<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EdiscoveryTag extends Model
{
    /** @var list<string> */
    public const CATEGORIES = [
        'privilege',
        'relevance',
        'custom',
    ];

    protected $fillable = [
        'organization_id',
        'legal_matter_id',
        'name',
        'color',
        'category',
        'created_by',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function legalMatter(): BelongsTo
    {
        return $this->belongsTo(LegalMatter::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
