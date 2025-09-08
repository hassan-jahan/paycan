<?php

namespace App\Services\Payment;

use App\Models\Order;
use App\Models\Transaction;
use App\Notifications\PaymentSuccessNotification;
use App\Notifications\PaymentFailedNotification;
use Illuminate\Support\Facades\Log;

abstract class AbstractPaymentGateway implements PaymentGatewayInterface
{
    // Common methods shared by all gateways
    protected function createTransaction(array $data)
    {
        try {
            $transaction = Transaction::create([
                'user_id' => $data['user_id'],
                'order_id' => $data['order_id'] ?? null,
                'subscription_id' => $data['subscription_id'] ?? null,
                'type' => $data['type'],
                'status' => $data['status'],
                'gateway' => $data['gateway'],
                'amount' => $data['amount'],
                'currency' => $data['currency'],
                'gateway_transaction_id' => $data['gateway_transaction_id'],
                'gateway_data' => $data['gateway_data'] ?? null,
            ]);

            return $transaction;
        } catch (\Exception $e) {
            Log::error('Error creating transaction: ' . $e->getMessage());
            throw $e;
        }
    }

    // Other shared helper methods
    protected function updateOrderStatus(Order $order, string $status, ?string $gatewayOrderId = null)
    {
        $order->status = $status;
        if ($gatewayOrderId) {
            $order->gateway_order_id = $gatewayOrderId;
        }
        $order->save();

        return $order;
    }
}