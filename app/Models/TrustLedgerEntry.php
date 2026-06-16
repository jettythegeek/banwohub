<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrustLedgerEntry extends Model
{
    /** @var list<string> */
    public const ENTRY_TYPES = ['deposit', 'disbursement', 'adjustment'];

    protected $fillable = [
        'organization_id',
        'legal_matter_id',
        'entry_type',
        'amount',
        'description',
        'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'occurred_at' => 'datetime',
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
}
