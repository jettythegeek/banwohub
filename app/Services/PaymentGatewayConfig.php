<?php

namespace App\Services;

class PaymentGatewayConfig
{
    public function stripeEnabled(): bool
    {
        return filled(config('services.stripe.secret'));
    }

    public function paypalEnabled(): bool
    {
        return filled(config('services.paypal.client_id'))
            && filled(config('services.paypal.client_secret'));
    }

    /**
     * @return array<string, array{enabled: bool, message: string|null}>
     */
    public function portalGateways(): array
    {
        return [
            'stripe' => [
                'enabled' => $this->stripeEnabled(),
                'message' => $this->stripeEnabled()
                    ? null
                    : 'Stripe is not configured. Ask your firm to enable online card payments.',
            ],
            'paypal' => [
                'enabled' => $this->paypalEnabled(),
                'message' => $this->paypalEnabled()
                    ? null
                    : 'PayPal is not configured. Ask your firm to enable PayPal payments.',
            ],
        ];
    }
}
