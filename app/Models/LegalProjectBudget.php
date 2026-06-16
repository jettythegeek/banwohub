<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LegalProjectBudget extends Model
{
    /** @var list<string> */
    public const CATEGORIES = ['fees', 'expenses', 'disbursements', 'other'];

    protected $fillable = [
        'organization_id',
        'legal_matter_id',
        'category',
        'description',
        'budgeted_amount',
        'actual_amount',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'budgeted_amount' => 'decimal:2',
            'actual_amount' => 'decimal:2',
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

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
