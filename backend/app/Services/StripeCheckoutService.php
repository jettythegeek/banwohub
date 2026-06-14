<?php

namespace App\Services;

use App\Models\Invoice;
use App\Support\Currency;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class StripeCheckoutService
{
    public function __construct(
        private readonly PaymentGatewayConfig $gatewayConfig,
        private readonly InvoicePaymentService $paymentService,
    ) {}

    /**
     * @return array{checkout_url: string, session_id: string, payment_id: int}
     */
    public function createCheckoutSession(Invoice $invoice, string $successUrl, string $cancelUrl): array
    {
        if (! $this->gatewayConfig->stripeEnabled()) {
            throw new RuntimeException('Stripe is not configured.');
        }

        $amount = (float) $invoice->balance_due;
        if ($amount <= 0) {
            throw new RuntimeException('This invoice has no balance due.');
        }

        $currency = strtolower($invoice->currency ?: Currency::code());
        $unitAmount = $this->toMinorUnits($amount, $currency);

        $response = Http::withToken(config('services.stripe.secret'))
            ->asForm()
            ->post('https://api.stripe.com/v1/checkout/sessions', [
                'mode' => 'payment',
                'success_url' => $successUrl,
                'cancel_url' => $cancelUrl,
                'client_reference_id' => (string) $invoice->id,
                'metadata' => [
                    'invoice_id' => (string) $invoice->id,
                    'organization_id' => (string) $invoice->organization_id,
                    'invoice_number' => $invoice->invoice_number,
                ],
                'line_items' => [
                    [
                        'price_data' => [
                            'currency' => $currency,
                            'unit_amount' => $unitAmount,
                            'product_data' => [
                                'name' => 'Invoice '.$invoice->invoice_number,
                                'description' => 'Payment for invoice '.$invoice->invoice_number,
                            ],
                        ],
                        'quantity' => 1,
                    ],
                ],
            ]);

        if (! $response->successful()) {
            throw new RuntimeException(
                'Stripe checkout failed: '.($response->json('error.message') ?? $response->body())
            );
        }

        $sessionId = (string) $response->json('id');
        $checkoutUrl = (string) $response->json('url');

        $payment = $this->paymentService->createPendingCheckoutPayment(
            invoice: $invoice,
            gateway: 'stripe',
            externalId: $sessionId,
            amount: $amount,
            metadata: [
                'invoice_number' => $invoice->invoice_number,
                'currency' => strtoupper($currency),
            ],
        );

        return [
            'checkout_url' => $checkoutUrl,
            'session_id' => $sessionId,
            'payment_id' => $payment->id,
        ];
    }

    public function verifyWebhookSignature(string $payload, ?string $signatureHeader): bool
    {
        $secret = config('services.stripe.webhook_secret');
        if (! filled($secret) || ! filled($signatureHeader)) {
            return false;
        }

        $parts = [];
        foreach (explode(',', $signatureHeader) as $item) {
            [$key, $value] = array_pad(explode('=', trim($item), 2), 2, null);
            if ($key && $value) {
                $parts[$key][] = $value;
            }
        }

        $timestamp = $parts['t'][0] ?? null;
        $signatures = $parts['v1'] ?? [];
        if (! $timestamp || $signatures === []) {
            return false;
        }

        $signedPayload = $timestamp.'.'.$payload;
        $expected = hash_hmac('sha256', $signedPayload, $secret);

        foreach ($signatures as $signature) {
            if (hash_equals($expected, $signature)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $event
     */
    public function handleWebhookEvent(array $event): bool
    {
        $type = $event['type'] ?? null;
        if ($type !== 'checkout.session.completed') {
            return false;
        }

        $session = $event['data']['object'] ?? [];
        $sessionId = $session['id'] ?? null;
        $paymentStatus = $session['payment_status'] ?? null;

        if (! $sessionId || $paymentStatus !== 'paid') {
            return false;
        }

        $payment = \App\Models\Payment::query()
            ->where('gateway', 'stripe')
            ->where('external_id', $sessionId)
            ->first();

        if (! $payment) {
            $invoiceId = (int) ($session['metadata']['invoice_id'] ?? $session['client_reference_id'] ?? 0);
            $invoice = Invoice::query()->find($invoiceId);
            if (! $invoice) {
                return false;
            }

            $amount = round(((float) ($session['amount_total'] ?? 0)) / 100, 2);

            $this->paymentService->applyPayment(
                invoice: $invoice,
                amount: $amount > 0 ? $amount : (float) $invoice->balance_due,
                gateway: 'stripe',
                externalId: $sessionId,
                metadata: ['stripe_session' => $sessionId],
                paymentMethod: 'stripe',
            );

            return true;
        }

        $this->paymentService->completePendingPayment($payment, [
            'stripe_session' => $sessionId,
            'payment_intent' => $session['payment_intent'] ?? null,
        ]);

        return true;
    }

    private function toMinorUnits(float $amount, string $currency): int
    {
        $zeroDecimal = ['bif', 'clp', 'djf', 'gnf', 'jpy', 'kmf', 'krw', 'mga', 'pyg', 'rwf', 'ugx', 'vnd', 'vuv', 'xaf', 'xof', 'xpf'];

        if (in_array($currency, $zeroDecimal, true)) {
            return (int) round($amount);
        }

        return (int) round($amount * 100);
    }
}
