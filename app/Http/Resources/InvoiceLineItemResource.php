<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\InvoiceLineItem */
class InvoiceLineItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'time_entry_id' => $this->time_entry_id,
            'service_item_id' => $this->service_item_id,
            'line_type' => $this->line_type,
            'description' => $this->description,
            'quantity' => (float) $this->quantity,
            'unit_price' => (float) $this->unit_price,
            'amount' => (float) $this->amount,
            'sort_order' => $this->sort_order,
            'time_entry' => $this->whenLoaded('timeEntry', fn () => $this->timeEntry ? [
                'id' => $this->timeEntry->id,
                'description' => $this->timeEntry->description,
                'duration_minutes' => $this->timeEntry->duration_minutes,
                'rate' => $this->timeEntry->rate !== null ? (float) $this->timeEntry->rate : null,
            ] : null),
            'service_item' => $this->whenLoaded('serviceItem', fn () => $this->serviceItem ? [
                'id' => $this->serviceItem->id,
                'name' => $this->serviceItem->name,
            ] : null),
        ];
    }
}
