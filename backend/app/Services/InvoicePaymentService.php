<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Payment;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class InvoicePaymentService
{
    public function applyPayment(
        Invoice $invoice,
        float $amount,
        string $gateway,
        ?string $externalId = null,
        array $metadata = [],
        ?string $paymentMethod = null,
        ?string $notes = null,
        ?Carbon $paidAt = null,
        string $paymentStatus = 'completed',
    ): Payment {
        if ($externalId) {
            $existing = Payment::query()
                ->where('gateway', $gateway)
                ->where('external_id', $externalId)
                ->first();

            if ($existing?->isCompleted()) {
                return $existing;
            }
        }

        return DB::transaction(function () use (
            $invoice,
            $amount,
            $gateway,
            $externalId,
            $metadata,
            $paymentMethod,
            $notes,
            $paidAt,
            $paymentStatus,
        ) {
            $payment = null;

            if ($externalId) {
                $payment = Payment::query()
                    ->where('gateway', $gateway)
                    ->where('external_id', $externalId)
                    ->lockForUpdate()
                    ->first();

                if ($payment?->isCompleted()) {
                    return $payment;
                }
            }

            if ($payment) {
                $payment->update([
                    'invoice_id' => $invoice->id,
                    'amount' => round($amount, 2),
                    'status' => $paymentStatus,
                    'metadata' => array_merge($payment->metadata ?? [], $metadata),
                ]);
            } else {
                $payment = Payment::query()->create([
                    'invoice_id' => $invoice->id,
                    'gateway' => $gateway,
                    'external_id' => $externalId,
                    'amount' => round($amount, 2),
                    'status' => $paymentStatus,
                    'metadata' => $metadata ?: null,
                ]);
            }

            if ($paymentStatus === 'completed') {
                $this->updateInvoiceBalances(
                    $invoice->fresh(),
                    round($amount, 2),
                    $paymentMethod ?? $gateway,
                    $notes,
                    $paidAt,
                );
            }

            return $payment->fresh();
        });
    }

    public function createPendingCheckoutPayment(
        Invoice $invoice,
        string $gateway,
        string $externalId,
        float $amount,
        array $metadata = [],
    ): Payment {
        return Payment::query()->updateOrCreate(
            [
                'gateway' => $gateway,
                'external_id' => $externalId,
            ],
            [
                'invoice_id' => $invoice->id,
                'amount' => round($amount, 2),
                'status' => 'pending',
                'metadata' => $metadata ?: null,
            ],
        );
    }

    public function completePendingPayment(Payment $payment, array $metadata = []): Payment
    {
        if ($payment->isCompleted()) {
            return $payment;
        }

        return $this->applyPayment(
            invoice: $payment->invoice()->lockForUpdate()->firstOrFail(),
            amount: (float) $payment->amount,
            gateway: $payment->gateway,
            externalId: $payment->external_id,
            metadata: array_merge($payment->metadata ?? [], $metadata),
            paymentMethod: $payment->gateway,
            paymentStatus: 'completed',
        );
    }

    private function updateInvoiceBalances(
        Invoice $invoice,
        float $amount,
        string $paymentMethod,
        ?string $notes,
        ?Carbon $paidAt,
    ): void {
        $newPaid = round(((float) $invoice->amount_paid) + $amount, 2);
        $balance = max(0, round(((float) $invoice->total_amount) - $newPaid, 2));

        $status = 'partial';
        $resolvedPaidAt = $invoice->paid_at;

        if ($balance <= 0) {
            $status = 'paid';
            $balance = 0;
            $newPaid = (float) $invoice->total_amount;
            $resolvedPaidAt = $paidAt ?? now();
        } elseif ($invoice->status === 'draft') {
            $status = 'sent';
        }

        $invoice->update([
            'amount_paid' => $newPaid,
            'balance_due' => $balance,
            'status' => $status,
            'payment_notes' => $notes ?? $invoice->payment_notes,
            'last_payment_method' => $paymentMethod,
            'paid_at' => $resolvedPaidAt,
            'sent_at' => $invoice->sent_at ?? now(),
        ]);
    }
}
