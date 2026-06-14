<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Payment;
use App\Support\Currency;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class PayPalCheckoutService
{
    public function __construct(
        private readonly PaymentGatewayConfig $gatewayConfig,
        private readonly InvoicePaymentService $paymentService,
    ) {}

    /**
     * @return array{approval_url: string, order_id: string, payment_id: int}
     */
    public function createCheckoutOrder(Invoice $invoice, string $returnUrl, string $cancelUrl): array
    {
        if (! $this->gatewayConfig->paypalEnabled()) {
            throw new RuntimeException('PayPal is not configured.');
        }

        $amount = (float) $invoice->balance_due;
        if ($amount <= 0) {
            throw new RuntimeException('This invoice has no balance due.');
        }

        $currency = strtoupper($invoice->currency ?: Currency::code());
        $token = $this->accessToken();

        $response = Http::withToken($token)
            ->acceptJson()
            ->post($this->apiBase().'/v2/checkout/orders', [
                'intent' => 'CAPTURE',
                'purchase_units' => [
                    [
                        'reference_id' => (string) $invoice->id,
                        'description' => 'Invoice '.$invoice->invoice_number,
                        'custom_id' => (string) $invoice->id,
                        'amount' => [
                            'currency_code' => $currency,
                            'value' => number_format($amount, 2, '.', ''),
                        ],
                    ],
                ],
                'application_context' => [
                    'brand_name' => config('app.name'),
                    'landing_page' => 'NO_PREFERENCE',
                    'user_action' => 'PAY_NOW',
                    'return_url' => $returnUrl,
                    'cancel_url' => $cancelUrl,
                ],
            ]);

        if (! $response->successful()) {
            throw new RuntimeException(
                'PayPal checkout failed: '.($response->json('message') ?? $response->body())
            );
        }

        $orderId = (string) $response->json('id');
        $approvalUrl = collect($response->json('links', []))
            ->firstWhere('rel', 'approve')['href'] ?? null;

        if (! $orderId || ! $approvalUrl) {
            throw new RuntimeException('PayPal checkout response was incomplete.');
        }

        $payment = $this->paymentService->createPendingCheckoutPayment(
            invoice: $invoice,
            gateway: 'paypal',
            externalId: $orderId,
            amount: $amount,
            metadata: [
                'invoice_number' => $invoice->invoice_number,
                'currency' => $currency,
            ],
        );

        return [
            'approval_url' => $approvalUrl,
            'order_id' => $orderId,
            'payment_id' => $payment->id,
        ];
    }

    public function captureOrder(string $orderId): bool
    {
        if (! $this->gatewayConfig->paypalEnabled()) {
            return false;
        }

        $payment = Payment::query()
            ->where('gateway', 'paypal')
            ->where('external_id', $orderId)
            ->first();

        if ($payment?->isCompleted()) {
            return true;
        }

        $token = $this->accessToken();
        $response = Http::withToken($token)
            ->acceptJson()
            ->post($this->apiBase()."/v2/checkout/orders/{$orderId}/capture");

        if (! $response->successful()) {
            return false;
        }

        $status = $response->json('status');
        if ($status !== 'COMPLETED') {
            return false;
        }

        if (! $payment) {
            $invoiceId = (int) ($response->json('purchase_units.0.payments.captures.0.custom_id')
                ?? $response->json('purchase_units.0.reference_id')
                ?? 0);
            $invoice = Invoice::query()->find($invoiceId);
            if (! $invoice) {
                return false;
            }

            $captureAmount = (float) ($response->json('purchase_units.0.payments.captures.0.amount.value') ?? $invoice->balance_due);

            $this->paymentService->applyPayment(
                invoice: $invoice,
                amount: $captureAmount,
                gateway: 'paypal',
                externalId: $orderId,
                metadata: ['paypal_order' => $orderId],
                paymentMethod: 'paypal',
            );

            return true;
        }

        $this->paymentService->completePendingPayment($payment, [
            'paypal_order' => $orderId,
            'capture_id' => $response->json('purchase_units.0.payments.captures.0.id'),
        ]);

        return true;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function handleWebhookEvent(array $payload): bool
    {
        if (! $this->gatewayConfig->paypalEnabled()) {
            return false;
        }

        $eventType = $payload['event_type'] ?? null;
        if (! in_array($eventType, ['CHECKOUT.ORDER.APPROVED', 'PAYMENT.CAPTURE.COMPLETED'], true)) {
            return false;
        }

        $resource = $payload['resource'] ?? [];
        $orderId = $resource['id'] ?? ($resource['supplementary_data']['related_ids']['order_id'] ?? null);

        if (! $orderId || $orderId === 'TEST') {
            return false;
        }

        if ($eventType === 'CHECKOUT.ORDER.APPROVED') {
            return $this->captureOrder($orderId);
        }

        $payment = Payment::query()
            ->where('gateway', 'paypal')
            ->where('external_id', $orderId)
            ->first();

        if ($payment?->isCompleted()) {
            return true;
        }

        return $this->captureOrder($orderId);
    }

    private function accessToken(): string
    {
        return Cache::remember('paypal_access_token', 3000, function () {
            $response = Http::asForm()
                ->withBasicAuth(
                    config('services.paypal.client_id'),
                    config('services.paypal.client_secret'),
                )
                ->post($this->apiBase().'/v1/oauth2/token', [
                    'grant_type' => 'client_credentials',
                ]);

            if (! $response->successful()) {
                throw new RuntimeException('PayPal authentication failed.');
            }

            return (string) $response->json('access_token');
        });
    }

    private function apiBase(): string
    {
        return config('services.paypal.mode') === 'live'
            ? 'https://api-m.paypal.com'
            : 'https://api-m.sandbox.paypal.com';
    }
}
