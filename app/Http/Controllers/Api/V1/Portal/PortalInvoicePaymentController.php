<?php

namespace App\Http\Controllers\Api\V1\Portal;

use App\Http\Controllers\Controller;
use App\Http\Concerns\ResolvesPortalClient;
use App\Models\Invoice;
use App\Services\PaymentGatewayConfig;
use App\Services\PayPalCheckoutService;
use App\Services\StripeCheckoutService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class PortalInvoicePaymentController extends Controller
{
    use ResolvesPortalClient;

    public function gateways(PaymentGatewayConfig $gatewayConfig): JsonResponse
    {
        return response()->json([
            'gateways' => $gatewayConfig->portalGateways(),
        ]);
    }

    public function stripeCheckout(
        Request $request,
        Invoice $invoice,
        StripeCheckoutService $stripe,
        PaymentGatewayConfig $gatewayConfig,
    ): JsonResponse {
        $this->authorizePayableInvoice($request, $invoice);

        if (! $gatewayConfig->stripeEnabled()) {
            return response()->json([
                'message' => 'Stripe is not configured. Online card payments are unavailable.',
                'gateways' => $gatewayConfig->portalGateways(),
            ], 503);
        }

        $frontend = rtrim(config('app.frontend_url', config('app.url')), '/');

        try {
            $session = $stripe->createCheckoutSession(
                invoice: $invoice,
                successUrl: $frontend.'/portal/invoices/'.$invoice->id.'/payment/success?gateway=stripe',
                cancelUrl: $frontend.'/portal/invoices/'.$invoice->id.'/payment/cancel?gateway=stripe',
            );
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json([
            'gateway' => 'stripe',
            'checkout_url' => $session['checkout_url'],
            'session_id' => $session['session_id'],
            'payment_id' => $session['payment_id'],
        ]);
    }

    public function paypalCheckout(
        Request $request,
        Invoice $invoice,
        PayPalCheckoutService $paypal,
        PaymentGatewayConfig $gatewayConfig,
    ): JsonResponse {
        $this->authorizePayableInvoice($request, $invoice);

        if (! $gatewayConfig->paypalEnabled()) {
            return response()->json([
                'message' => 'PayPal is not configured. PayPal payments are unavailable.',
                'gateways' => $gatewayConfig->portalGateways(),
            ], 503);
        }

        $frontend = rtrim(config('app.frontend_url', config('app.url')), '/');

        try {
            $order = $paypal->createCheckoutOrder(
                invoice: $invoice,
                returnUrl: $frontend.'/portal/invoices/'.$invoice->id.'/payment/success?gateway=paypal',
                cancelUrl: $frontend.'/portal/invoices/'.$invoice->id.'/payment/cancel?gateway=paypal',
            );
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json([
            'gateway' => 'paypal',
            'approval_url' => $order['approval_url'],
            'order_id' => $order['order_id'],
            'payment_id' => $order['payment_id'],
        ]);
    }

    public function paypalCapture(
        Request $request,
        PayPalCheckoutService $paypal,
    ): JsonResponse {
        $data = $request->validate([
            'order_id' => ['required', 'string'],
        ]);

        $client = $this->portalClientFor($request->user());
        $payment = \App\Models\Payment::query()
            ->where('gateway', 'paypal')
            ->where('external_id', $data['order_id'])
            ->whereHas('invoice', fn ($q) => $q->where('client_id', $client->id))
            ->first();

        abort_unless($payment, 404);

        $captured = $paypal->captureOrder($data['order_id']);

        return response()->json([
            'captured' => $captured,
            'invoice_id' => $payment->invoice_id,
        ], $captured ? 200 : 422);
    }

    private function authorizePayableInvoice(Request $request, Invoice $invoice): void
    {
        $client = $this->portalClientFor($request->user());

        abort_unless(
            $invoice->client_id === $client->id
            && in_array($invoice->status, ['sent', 'partial', 'overdue'], true)
            && (float) $invoice->balance_due > 0,
            404,
        );
    }
}
