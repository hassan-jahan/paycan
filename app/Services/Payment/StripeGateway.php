<?php

namespace App\Services\Payment;

use App\Models\Order;
use App\Models\Subscription;
use App\Models\User;
use App\Notifications\PaymentSuccessNotification;
use Illuminate\Support\Facades\Log;
use Stripe\StripeClient;

class StripeGateway extends AbstractPaymentGateway
{
    protected $stripe;

    protected $gateway = 'stripe';

    public function __construct()
    {
        $secretKey = config('services.stripe.secret');

        // Only initialize if credentials are properly configured
        if ($secretKey &&
            ! str_starts_with($secretKey, 'sk_test_your_key') &&
            ! str_starts_with($secretKey, 'sk_live_your_key') &&
            ! str_starts_with($secretKey, 'your_stripe_')) {

            $this->stripe = new StripeClient($secretKey);
        } else {
            // Stripe credentials not configured - will return errors for Stripe operations
            $this->stripe = null;
        }
    }

    public function createCheckoutSession(array $data)
    {
        if (! $this->stripe) {
            return [
                'success' => false,
                'message' => 'Stripe credentials not configured',
            ];
        }

        try {
            $lineItems = [];
            foreach ($data['items'] as $item) {
                // Get price ID from gateway_data JSON
                $priceId = $item['price']->gateway_data['stripe']['price_id'] ?? null;

                // If price ID doesn't exist or is invalid (fake demo ID), create it dynamically
                if (! $priceId || $this->isInvalidPriceId($priceId)) {
                    Log::info("Creating Stripe price for ProductPrice: {$item['price']->title}");
                    $priceId = $this->createStripePrice($item['price']);

                    if (! $priceId) {
                        throw new \Exception("Failed to create Stripe price for '{$item['price']->title}'. Please contact support.");
                    }
                }

                $lineItems[] = [
                    'price' => $priceId,
                    'quantity' => $item['quantity'] ?? 1,
                ];
            }

            // Get or create customer for the user
            $customerId = $this->getOrCreateCustomer($data['user']);

            // Determine if this is a subscription based on the first item's billing period
            $isSubscription = false;
            if (! empty($data['items'])) {
                $firstItem = $data['items'][0];
                $isSubscription = ($firstItem['price']->billing_period !== 'once');
            }

            $sessionData = [
                'payment_method_types' => ['card'],
                'line_items' => $lineItems,
                'mode' => $isSubscription ? 'subscription' : 'payment',
                'success_url' => $data['success_url'],
                'cancel_url' => $data['cancel_url'],
                'customer' => $customerId,
                'metadata' => [
                    'user_id' => $data['user']->id,
                    'order_id' => $data['order']->id,
                ],
            ];

            // For subscriptions, add allow_promotion_codes if available
            if ($isSubscription) {
                $sessionData['allow_promotion_codes'] = true;
                $sessionData['subscription_data'] = [
                    'metadata' => [
                        'user_id' => $data['user']->id,
                        'order_id' => $data['order']->id,
                    ],
                ];
            }

            $session = $this->stripe->checkout->sessions->create($sessionData);

            return [
                'success' => true,
                'id' => $session->id,
                'url' => $session->url,
            ];
        } catch (\Stripe\Exception\InvalidRequestException $e) {
            Log::error('Stripe API error: '.$e->getMessage());

            // Check for specific error types and provide user-friendly messages
            if (str_contains($e->getMessage(), 'No such price')) {
                return [
                    'success' => false,
                    'message' => 'The selected product pricing is not properly configured. Please try a different payment method or contact support.',
                ];
            } elseif (str_contains($e->getMessage(), 'Invalid email')) {
                return [
                    'success' => false,
                    'message' => 'A valid email address is required to process your payment.',
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Payment processing error: '.$e->getMessage(),
                ];
            }
        } catch (\Exception $e) {
            Log::error('Stripe checkout session error: '.$e->getMessage());

            return [
                'success' => false,
                'message' => 'An unexpected error occurred while setting up your payment. Please try again.',
            ];
        }
    }

    public function handlePaymentSuccess(array $payload)
    {
        try {
            $eventType = $payload['type'] ?? '';

            if ($eventType === 'checkout.session.completed') {
                $session = $payload['data']['object'] ?? [];
                $sessionId = $session['id'] ?? '';
                $mode = $session['mode'] ?? '';

                if ($sessionId) {
                    // Find the order by gateway_order_id (checkout session ID)
                    $order = Order::where('gateway_order_id', $sessionId)->first();

                    if ($order && $order->status === 'pending') {
                        $order->update([
                            'status' => 'paid',
                            'meta' => array_merge($order->meta ?? [], [
                                'payment_completed_at' => now(),
                                'stripe_payment_intent' => $session['payment_intent'] ?? null,
                                'stripe_subscription' => $session['subscription'] ?? null,
                            ]),
                        ]);

                        Log::info("Order {$order->order_number} marked as paid via Stripe webhook");

                        // If this was a subscription checkout, update the subscription status too
                        if ($mode === 'subscription') {
                            $subscription = Subscription::where('gateway_subscription_id', $sessionId)
                                ->orWhere('order_id', $order->id)
                                ->first();

                            if ($subscription && $subscription->status === 'incomplete') {
                                $stripeSubscriptionId = $session['subscription'] ?? null;

                                $subscription->update([
                                    'status' => 'active',
                                    'gateway_subscription_id' => $stripeSubscriptionId,
                                    'gateway_status' => 'active',
                                    'gateway_data' => array_merge($subscription->gateway_data ?? [], [
                                        'stripe_checkout_session' => $sessionId,
                                        'stripe_subscription_id' => $stripeSubscriptionId,
                                        'activated_at' => now(),
                                    ]),
                                ]);

                                Log::info("Subscription {$subscription->id} marked as active via Stripe webhook");
                            }
                        }

                        // TODO: Send payment success notification to user
                        // $order->user->notify(new PaymentSuccessNotification($order));
                    }
                }
            }

            return true;
        } catch (\Exception $e) {
            Log::error('Stripe payment success webhook error: '.$e->getMessage());

            return false;
        }
    }

    public function handlePaymentFailure(array $payload)
    {
        try {
            $eventType = $payload['type'] ?? '';

            if ($eventType === 'payment_intent.payment_failed') {
                $paymentIntent = $payload['data']['object'] ?? [];
                $paymentIntentId = $paymentIntent['id'] ?? '';

                if ($paymentIntentId) {
                    // Try to find order by payment intent metadata
                    $orderId = $paymentIntent['metadata']['order_id'] ?? null;

                    if ($orderId) {
                        $order = Order::find($orderId);

                        if ($order && $order->status === 'pending') {
                            $order->update([
                                'status' => 'failed',
                                'meta' => array_merge($order->meta ?? [], [
                                    'payment_failed_at' => now(),
                                    'failure_reason' => $paymentIntent['last_payment_error']['message'] ?? 'Payment failed',
                                ]),
                            ]);

                            Log::info("Order {$order->order_number} marked as failed via Stripe webhook");
                        }
                    }
                }
            }

            return true;
        } catch (\Exception $e) {
            Log::error('Stripe payment failure webhook error: '.$e->getMessage());

            return false;
        }
    }

    public function getPaymentDetails(string $paymentId)
    {
        if (! $this->stripe) {
            return [
                'success' => false,
                'error' => 'Stripe credentials not configured',
            ];
        }

        try {
            $paymentIntent = $this->stripe->paymentIntents->retrieve($paymentId);

            return [
                'success' => true,
                'data' => $paymentIntent,
            ];
        } catch (\Exception $e) {
            Log::error('Stripe get payment details error: '.$e->getMessage());

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    public function refundPayment(string $paymentId, ?float $amount = null)
    {
        if (! $this->stripe) {
            return [
                'success' => false,
                'error' => 'Stripe credentials not configured',
            ];
        }

        try {
            $refundData = ['payment_intent' => $paymentId];
            if ($amount) {
                $refundData['amount'] = (int) ($amount * 100); // Convert to cents
            }

            $refund = $this->stripe->refunds->create($refundData);

            return [
                'success' => true,
                'refund_id' => $refund->id,
            ];
        } catch (\Exception $e) {
            Log::error('Stripe refund error: '.$e->getMessage());

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    public function createSubscription(array $data)
    {
        if (! $this->stripe) {
            return [
                'success' => false,
                'error' => 'Stripe credentials not configured',
            ];
        }

        try {
            // Get or create customer
            $customerId = $this->getOrCreateCustomer($data['user']);

            // Get the price ID from the product price
            $productPrice = $data['items'][0]['price'];
            $priceId = $this->getOrCreateStripePrice($productPrice);

            if (! $priceId) {
                throw new \Exception('Failed to get or create Stripe price');
            }

            $subscription = $this->stripe->subscriptions->create([
                'customer' => $customerId,
                'items' => [['price' => $priceId]],
                'metadata' => [
                    'user_id' => $data['user']->id,
                    'subscription_id' => $data['subscription']->id ?? null,
                    'order_id' => $data['order']->id ?? null,
                ],
            ]);

            return [
                'success' => true,
                'id' => $subscription->id,
                'status' => $subscription->status,
            ];
        } catch (\Exception $e) {
            Log::error('Stripe subscription creation error: '.$e->getMessage());

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    public function cancelSubscription(string $subscriptionId)
    {
        if (! $this->stripe) {
            return [
                'success' => false,
                'error' => 'Stripe credentials not configured',
            ];
        }

        try {
            $subscription = $this->stripe->subscriptions->cancel($subscriptionId);

            return [
                'success' => true,
                'status' => $subscription->status,
                'current_period_end' => $subscription->current_period_end,
                'canceled_at' => $subscription->canceled_at,
            ];
        } catch (\Exception $e) {
            Log::error('Stripe cancel subscription error: '.$e->getMessage());

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    public function resumeSubscription(string $subscriptionId)
    {
        if (! $this->stripe) {
            return [
                'success' => false,
                'error' => 'Stripe credentials not configured',
            ];
        }

        try {
            $subscription = $this->stripe->subscriptions->update($subscriptionId, [
                'cancel_at_period_end' => false,
            ]);

            return [
                'success' => true,
                'status' => $subscription->status,
            ];
        } catch (\Exception $e) {
            Log::error('Stripe resume subscription error: '.$e->getMessage());

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    public function changeSubscriptionPlan(string $subscriptionId, string $newPlanId)
    {
        if (! $this->stripe) {
            return [
                'success' => false,
                'error' => 'Stripe credentials not configured',
            ];
        }

        try {
            $subscription = $this->stripe->subscriptions->retrieve($subscriptionId);
            $updatedSubscription = $this->stripe->subscriptions->update($subscriptionId, [
                'items' => [
                    [
                        'id' => $subscription->items->data[0]->id,
                        'price' => $newPlanId,
                    ],
                ],
            ]);

            return [
                'success' => true,
                'subscription' => $updatedSubscription,
            ];
        } catch (\Exception $e) {
            Log::error('Stripe plan change error: '.$e->getMessage());

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Create a new Stripe price and update the ProductPrice model
     */
    protected function createStripePrice($productPrice)
    {
        if (! $this->stripe) {
            return null;
        }

        try {
            // First, create or get a Stripe product
            $productName = $productPrice->product->title.' - '.$productPrice->title;
            $stripeProduct = $this->createOrGetStripeProduct($productPrice->product, $productName);

            if (! $stripeProduct) {
                throw new \Exception("Failed to create Stripe product for '{$productName}'");
            }

            // Create the price in Stripe
            $priceData = [
                'unit_amount' => (int) ($productPrice->amount * 100), // Convert to cents
                'currency' => strtolower($productPrice->currency),
                'product' => $stripeProduct->id,
                'nickname' => $productPrice->title,
            ];

            // Handle recurring prices
            if ($productPrice->billing_period !== 'once') {
                $priceData['recurring'] = [
                    'interval' => $this->mapBillingPeriodToStripe($productPrice->billing_period),
                ];
            }

            $stripePrice = $this->stripe->prices->create($priceData);

            // Update the ProductPrice model with the new price ID
            $currentGatewayData = $productPrice->gateway_data ?? [];
            $currentGatewayData['stripe'] = [
                'price_id' => $stripePrice->id,
                'product_id' => $stripeProduct->id,
            ];

            $productPrice->update(['gateway_data' => $currentGatewayData]);

            Log::info("Created Stripe price: {$stripePrice->id} for ProductPrice: {$productPrice->title}");

            return $stripePrice->id;
        } catch (\Exception $e) {
            Log::error("Failed to create Stripe price for ProductPrice ID {$productPrice->id}: ".$e->getMessage());

            return null;
        }
    }

    /**
     * Create or get existing Stripe product
     */
    protected function createOrGetStripeProduct($product, $productName)
    {
        try {
            // Try to get existing product ID from gateway_data
            $stripeProductId = $product->gateway_data['stripe']['product_id'] ?? null;

            if ($stripeProductId) {
                try {
                    // Verify the product still exists in Stripe
                    return $this->stripe->products->retrieve($stripeProductId);
                } catch (\Stripe\Exception\InvalidRequestException $e) {
                    // Product doesn't exist, create a new one
                    Log::warning("Stripe product {$stripeProductId} not found, creating new one");
                }
            }

            // Create new Stripe product
            $stripeProduct = $this->stripe->products->create([
                'name' => $productName,
                'description' => $product->description,
                'metadata' => [
                    'product_id' => $product->id,
                    'product_slug' => $product->slug,
                ],
            ]);

            // Update the Product model with Stripe product ID
            $currentGatewayData = $product->gateway_data ?? [];
            $currentGatewayData['stripe'] = [
                'product_id' => $stripeProduct->id,
            ];

            $product->update(['gateway_data' => $currentGatewayData]);

            return $stripeProduct;
        } catch (\Exception $e) {
            Log::error("Failed to create Stripe product for Product ID {$product->id}: ".$e->getMessage());

            return null;
        }
    }

    /**
     * Map billing period to Stripe recurring interval
     */
    protected function mapBillingPeriodToStripe($billingPeriod)
    {
        $mapping = [
            'daily' => 'day',
            'weekly' => 'week',
            'monthly' => 'month',
            'yearly' => 'year',
        ];

        return $mapping[$billingPeriod] ?? 'month';
    }

    /**
     * Check if a price ID is invalid/fake/demo
     */
    protected function isInvalidPriceId($priceId)
    {
        // Real Stripe price IDs start with 'price_' and are at least 28 characters
        // Demo/fake IDs are usually shorter or don't follow Stripe's format
        if (strlen($priceId) < 28) {
            return true;
        }

        // Common patterns for fake/demo price IDs
        $fakePatterns = [
            'price_digital_',
            'price_physical_',
            'price_service_',
            'price_subscription_',
            'price_course_',
            'price_headphones',
            'price_fitness_',
        ];

        foreach ($fakePatterns as $pattern) {
            if (str_starts_with($priceId, $pattern)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get or create Stripe customer for user
     */
    public function getOrCreateCustomer($user)
    {
        // Check if user already has a Stripe customer ID
        $gatewayData = $user->gateway_data ?? [];
        $customerId = $gatewayData['stripe']['customer_id'] ?? null;

        if ($customerId) {
            try {
                // Verify the customer still exists in Stripe
                $customer = $this->stripe->customers->retrieve($customerId);

                return $customer->id;
            } catch (\Stripe\Exception\InvalidRequestException $e) {
                // Customer doesn't exist, create a new one
                Log::warning("Stripe customer {$customerId} not found for user {$user->id}, creating new one");
            }
        }

        // Create new Stripe customer
        $customer = $this->stripe->customers->create([
            'email' => $user->email,
            'name' => $user->name,
            'metadata' => [
                'user_id' => $user->id,
            ],
        ]);

        // Update the user's gateway_data with the new customer ID
        $gatewayData['stripe'] = array_merge($gatewayData['stripe'] ?? [], [
            'customer_id' => $customer->id,
        ]);

        $user->update(['gateway_data' => $gatewayData]);

        Log::info("Created Stripe customer: {$customer->id} for user: {$user->id}");

        return $customer->id;
    }

    /**
     * Get or create Stripe price for product price
     */
    public function getOrCreateStripePrice($productPrice)
    {
        // Get price ID from gateway_data JSON
        $priceId = $productPrice->gateway_data['stripe']['price_id'] ?? null;

        // If price ID doesn't exist or is invalid (fake demo ID), create it dynamically
        if (! $priceId || $this->isInvalidPriceId($priceId)) {
            Log::info("Creating Stripe price for ProductPrice: {$productPrice->title}");
            $priceId = $this->createStripePrice($productPrice);

            if (! $priceId) {
                throw new \Exception("Failed to create Stripe price for '{$productPrice->title}'. Please contact support.");
            }
        }

        return $priceId;
    }

    public function handleWebhook(array $payload)
    {
        try {
            $event = $payload['type'] ?? null;

            switch ($event) {
                case 'checkout.session.completed':
                    return $this->handlePaymentSuccess($payload);
                case 'payment_intent.payment_failed':
                    return $this->handlePaymentFailure($payload);
                default:
                    return ['success' => true, 'message' => 'Unhandled event type'];
            }
        } catch (\Exception $e) {
            Log::error('Stripe webhook error: '.$e->getMessage());

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Create Stripe Customer Portal session for payment method management
     */
    public function createCustomerPortalSession(User $user, string $returnUrl)
    {
        try {
            // Get or create Stripe customer
            $stripeCustomerId = $this->getOrCreateStripeCustomer($user);

            if (! $stripeCustomerId) {
                throw new \Exception('Failed to create or retrieve Stripe customer');
            }

            // Create billing portal session
            $session = \Stripe\BillingPortal\Session::create([
                'customer' => $stripeCustomerId,
                'return_url' => $returnUrl,
            ]);

            return [
                'success' => true,
                'url' => $session->url,
                'session_id' => $session->id,
            ];
        } catch (\Exception $e) {
            Log::error('Stripe customer portal session creation error: '.$e->getMessage(), [
                'user_id' => $user->id,
                'return_url' => $returnUrl,
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get or create Stripe customer for a user
     */
    protected function getOrCreateStripeCustomer(User $user)
    {
        try {
            // Check if user already has a Stripe customer ID stored
            $existingCustomerId = $user->gateway_data['stripe']['customer_id'] ?? null;

            if ($existingCustomerId) {
                // Verify the customer exists in Stripe
                try {
                    $customer = \Stripe\Customer::retrieve($existingCustomerId);
                    if ($customer && ! $customer->deleted) {
                        return $existingCustomerId;
                    }
                } catch (\Exception $e) {
                    Log::warning('Stored Stripe customer ID is invalid, creating new one', [
                        'user_id' => $user->id,
                        'stored_customer_id' => $existingCustomerId,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            // Create new Stripe customer
            $customer = \Stripe\Customer::create([
                'email' => $user->email,
                'name' => $user->name,
                'metadata' => [
                    'user_id' => $user->id,
                ],
            ]);

            // Store customer ID in user's gateway data
            $gatewayData = $user->gateway_data ?? [];
            $gatewayData['stripe'] = array_merge($gatewayData['stripe'] ?? [], [
                'customer_id' => $customer->id,
            ]);

            $user->update(['gateway_data' => $gatewayData]);

            return $customer->id;
        } catch (\Exception $e) {
            Log::error('Failed to get or create Stripe customer: '.$e->getMessage(), [
                'user_id' => $user->id,
            ]);

            return null;
        }
    }
}
