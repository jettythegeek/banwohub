<?php

namespace App\Services;

use App\Models\LegalMatter;
use App\Models\TrustLedgerEntry;

class TrustLedgerService
{
    public function balanceForMatter(int $legalMatterId): float
    {
        return round(
            TrustLedgerEntry::query()
                ->where('legal_matter_id', $legalMatterId)
                ->get(['entry_type', 'amount'])
                ->reduce(fn (float $carry, TrustLedgerEntry $entry): float => $carry + $this->signedAmount($entry), 0.0),
            2,
        );
    }

    public function syncMatterBalance(LegalMatter $legalMatter): void
    {
        $balance = $this->balanceForMatter($legalMatter->id);

        $legalMatter->update([
            'trust_balance' => $balance,
        ]);
    }

    public function signedAmount(TrustLedgerEntry $entry): float
    {
        $amount = (float) $entry->amount;

        return match ($entry->entry_type) {
            'deposit' => $amount,
            'disbursement' => -$amount,
            'adjustment' => $amount,
            default => 0.0,
        };
    }
}
