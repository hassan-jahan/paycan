<?php

namespace App\Services\Payment;

interface PaymentGatewayInterface
{
    // One-time payment methods
    public function createCheckoutSession(array $data);
    public function handlePaymentSuccess(array $payload);
    public function handlePaymentFailure(array $payload);
    public function getPaymentDetails(string $paymentId);
    public function refundPayment(string $paymentId, ?float $amount = null);

    // Subscription methods
    public function createSubscription(array $data);
    public function cancelSubscription(string $subscriptionId);
    public function resumeSubscription(string $subscriptionId);
    public function changeSubscriptionPlan(string $subscriptionId, string $newPlanId);
    
    // Webhook handler
    public function handleWebhook(array $payload);
}