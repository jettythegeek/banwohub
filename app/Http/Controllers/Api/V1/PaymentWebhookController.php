<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\PayPalCheckoutService;
use App\Services\StripeCheckoutService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentWebhookController extends Controller
{
    public function stripe(Request $request, StripeCheckoutService $stripe): JsonResponse
    {
        $payload = $request->getContent();
        $signature = $request->header('Stripe-Signature');

        if (! $stripe->verifyWebhookSignature($payload, $signature)) {
            return response()->json(['message' => 'Invalid signature.'], 400);
        }

        $event = json_decode($payload, true);
        if (! is_array($event)) {
            return response()->json(['message' => 'Invalid payload.'], 400);
        }

        $handled = $stripe->handleWebhookEvent($event);

        return response()->json(['received' => true, 'handled' => $handled]);
    }

    public function paypal(Request $request, PayPalCheckoutService $paypal): JsonResponse
    {
        $payload = $request->all();
        if ($payload === []) {
            return response()->json(['message' => 'Invalid payload.'], 400);
        }

        try {
            $handled = $paypal->handleWebhookEvent($payload);
        } catch (\Throwable) {
            return response()->json(['received' => true, 'handled' => false]);
        }

        return response()->json(['received' => true, 'handled' => $handled]);
    }
}
