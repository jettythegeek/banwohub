<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    public const GATEWAYS = [
        'stripe',
        'paypal',
        'cash',
        'card',
        'upi',
        'bank_transfer',
        'cheque',
    ];

    /** @var list<string> Staff-recorded payment methods (stored as gateway). */
    public const MANUAL_GATEWAYS = [
        'cash',
        'card',
        'upi',
        'bank_transfer',
        'cheque',
    ];

    public const STATUSES = ['pending', 'completed', 'failed', 'refunded'];

    protected $fillable = [
        'invoice_id',
        'gateway',
        'external_id',
        'amount',
        'status',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'metadata' => 'array',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }
}
