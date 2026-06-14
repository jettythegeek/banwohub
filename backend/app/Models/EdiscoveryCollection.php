<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EdiscoveryCollection extends Model
{
    /** @var list<string> */
    public const STATUSES = [
        'open',
        'in_review',
        'complete',
    ];

    protected $fillable = [
        'organization_id',
        'legal_matter_id',
        'name',
        'description',
        'status',
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

    public function documents(): HasMany
    {
        return $this->hasMany(EdiscoveryDocument::class);
    }
}
