<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceLineItem extends Model
{
    public const TYPES = ['time_entry', 'expense', 'service', 'adjustment'];

    protected $fillable = [
        'invoice_id',
        'organization_id',
        'time_entry_id',
        'case_expense_id',
        'service_item_id',
        'line_type',
        'description',
        'quantity',
        'unit_price',
        'amount',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2',
            'unit_price' => 'decimal:2',
            'amount' => 'decimal:2',
            'sort_order' => 'integer',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function timeEntry(): BelongsTo
    {
        return $this->belongsTo(TimeEntry::class);
    }

    public function caseExpense(): BelongsTo
    {
        return $this->belongsTo(CaseExpense::class);
    }

    public function serviceItem(): BelongsTo
    {
        return $this->belongsTo(ServiceItem::class);
    }
}
