<?php

namespace App\Services\Payment;

class PaymentGatewayFactory
{
    /**
     * Create a new payment gateway instance
     */
    public static function create(string $gateway): PaymentGatewayInterface
    {
        return match ($gateway) {
            'stripe' => app(StripeGateway::class),
            'paypal' => app(PayPalGateway::class),
            default => throw new \InvalidArgumentException("Unsupported payment gateway: {$gateway}")
        };
    }
}