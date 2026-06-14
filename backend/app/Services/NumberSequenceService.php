<?php

namespace App\Services;

use App\Models\Client;
use App\Models\Invoice;
use App\Models\LegalMatter;

class NumberSequenceService
{
    public function nextCaseNumber(int $organizationId): string
    {
        return $this->nextSequence(
            LegalMatter::query()->withTrashed()->where('organization_id', $organizationId),
            'matter_number',
            'CASE-',
            4,
        );
    }

    public function nextClientNumber(int $organizationId): string
    {
        return $this->nextSequence(
            Client::query()->withTrashed()->where('organization_id', $organizationId),
            'client_number',
            'CL-',
            6,
        );
    }

    public function nextInvoiceNumber(int $organizationId): string
    {
        $monthPrefix = 'INV-'.now()->format('Ym').'-';

        return $this->nextSequence(
            Invoice::query()->withTrashed()->where('organization_id', $organizationId),
            'invoice_number',
            $monthPrefix,
            6,
        );
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<\Illuminate\Database\Eloquent\Model>  $query
     */
    protected function nextSequence($query, string $column, string $prefix, int $padLength): string
    {
        $latest = (clone $query)
            ->where($column, 'like', $prefix.'%')
            ->orderByDesc($column)
            ->value($column);

        $sequence = 1;
        if (is_string($latest) && preg_match('/(\d+)$/', $latest, $matches)) {
            $sequence = ((int) $matches[1]) + 1;
        }

        return $prefix.str_pad((string) $sequence, $padLength, '0', STR_PAD_LEFT);
    }
}
