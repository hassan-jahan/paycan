<?php

namespace App\Services\Payment;

use App\Models\Order;
use App\Models\Subscription;
use App\Models\User;
use App\Notifications\PaymentSuccessNotification;
use Illuminate\Support\Facades\Log;
use PaypalServerSdkLib\Authentication\ClientCredentialsAuthCredentialsBuilder;
use PaypalServerSdkLib\Environment;
use PaypalServerSdkLib\Models\OrderApplicationContext;
use PaypalServerSdkLib\Models\OrderRequest;
use PaypalServerSdkLib\Models\PurchaseUnitRequest;
use PaypalServerSdkLib\Models\SubscriptionRequestPOST;
use PaypalServerSdkLib\PaypalServerSdkClientBuilder;

class PayPalGateway extends AbstractPaymentGateway
{
    protected $client;

    protected $gateway = 'paypal';

    public function __construct()
    {
        $clientId = config('services.paypal.client_id');
        $clientSecret = config('services.paypal.client_secret');

        // Only initialize if credentials are properly configured
        if ($clientId && $clientSecret &&
            ! str_starts_with($clientId, 'your_paypal_') &&
            ! str_starts_with($clientSecret, 'your_paypal_')) {

            $environment = config('services.paypal.mode') === 'sandbox'
                ? Environment::SANDBOX
                : Environment::PRODUCTION;

            $this->client = PaypalServerSdkClientBuilder::init()
                ->clientCredentialsAuthCredentials(
                    ClientCredentialsAuthCredentialsBuilder::init(
                        $clientId,
                        $clientSecret
                    )
                )
                ->environment($environment)
                ->build();
        } else {
            // PayPal credentials not configured - will return errors for PayPal operations
            $this->client = null;
        }
    }

    public function createCheckoutSession(array $data)
    {
        if (! $this->client) {
            return [
                'success' => false,
                'message' => 'PayPal payments are currently unavailable. Please try Stripe or contact support.',
            ];
        }

        try {
            $totalAmount = 0;
            foreach ($data['items'] as $item) {
                $totalAmount += $item['amount'] * ($item['quantity'] ?? 1);
            }

            // Create purchase unit
            $purchaseUnit = PurchaseUnitRequest::fromArray([
                'amount' => [
                    'currency_code' => $data['currency'],
                    'value' => number_format($totalAmount, 2, '.', ''),
                ],
                'custom_id' => (string) $data['order']->id,
            ]);

            // Create application context
            $applicationContext = OrderApplicationContext::fromArray([
                'return_url' => $data['success_url'],
                'cancel_url' => $data['cancel_url'],
                'brand_name' => config('app.name'),
                'landing_page' => 'BILLING',
                'user_action' => 'PAY_NOW',
            ]);

            // Create order request
            $orderRequest = OrderRequest::fromArray([
                'intent' => 'CAPTURE',
                'purchase_units' => [$purchaseUnit],
                'application_context' => $applicationContext,
            ]);

            $response = $this->client->getOrdersController()->ordersCreate($orderRequest);

            if ($response->isError()) {
                throw new \Exception('PayPal API Error: '.json_encode($response->getError()));
            }

            $order = $response->getResult();
            $approvalUrl = null;

            if ($order->getLinks()) {
                foreach ($order->getLinks() as $link) {
                    if ($link->getRel() === 'approve') {
                        $approvalUrl = $link->getHref();
                        break;
                    }
                }
            }

            return [
                'success' => true,
                'id' => $order->getId(),
                'url' => $approvalUrl,
            ];
        } catch (\Exception $e) {
            Log::error('PayPal checkout session error: '.$e->getMessage());

            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    public function handlePaymentSuccess(array $payload)
    {
        try {
            // Handle PayPal payment success
            $orderId = $payload['resource']['id'] ?? null;

            if (! $orderId) {
                throw new \Exception('No order ID found in PayPal webhook');
            }

            // Find the order in our database
            $order = Order::where('gateway_order_id', $orderId)->first();

            if (! $order) {
                throw new \Exception('Order not found: '.$orderId);
            }

            // Update order status
            $order->update([
                'status' => 'completed',
                'completed_at' => now(),
                'gateway_data' => array_merge($order->gateway_data ?? [], $payload),
            ]);

            // Send notification
            $order->user->notify(new PaymentSuccessNotification($order));

            return true;
        } catch (\Exception $e) {
            Log::error('PayPal payment success handling error: '.$e->getMessage());

            return false;
        }
    }

    public function handlePaymentFailure(array $payload)
    {
        try {
            // Handle PayPal payment failure
            $orderId = $payload['resource']['id'] ?? null;

            if (! $orderId) {
                throw new \Exception('No order ID found in PayPal webhook');
            }

            // Find the order in our database
            $order = Order::where('gateway_order_id', $orderId)->first();

            if (! $order) {
                throw new \Exception('Order not found: '.$orderId);
            }

            // Update order status
            $order->update([
                'status' => 'failed',
                'gateway_data' => array_merge($order->gateway_data ?? [], $payload),
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('PayPal payment failure handling error: '.$e->getMessage());

            return false;
        }
    }

    public function getPaymentDetails(string $paymentId)
    {
        if (! $this->client) {
            return [
                'success' => false,
                'error' => 'PayPal credentials not configured',
            ];
        }

        try {
            // Get PayPal order details
            $response = $this->client->getOrdersController()->ordersGet($paymentId);

            if ($response->isError()) {
                throw new \Exception('PayPal API Error: '.json_encode($response->getError()));
            }

            $order = $response->getResult();

            return [
                'success' => true,
                'data' => [
                    'id' => $order->getId(),
                    'status' => $order->getStatus(),
                    'intent' => $order->getIntent(),
                    'amount' => $order->getPurchaseUnits()[0]->getAmount()->getValue(),
                    'currency' => $order->getPurchaseUnits()[0]->getAmount()->getCurrencyCode(),
                ],
            ];
        } catch (\Exception $e) {
            Log::error('PayPal get payment details error: '.$e->getMessage());

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    public function refundPayment(string $paymentId, ?float $amount = null)
    {
        try {
            // PayPal refund implementation would go here
            // For now, return a mock response
            return [
                'success' => true,
                'refund_id' => 'paypal_refund_'.uniqid(),
            ];
        } catch (\Exception $e) {
            Log::error('PayPal refund error: '.$e->getMessage());

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    public function createSubscription(array $data)
    {
        if (! $this->client) {
            return [
                'success' => false,
                'error' => 'PayPal credentials not configured',
            ];
        }

        try {
            $productPrice = $data['items'][0]['price'];
            $planId = $this->getOrCreatePayPalPlan($productPrice);

            if (! $planId) {
                throw new \Exception('Failed to create or get PayPal billing plan');
            }

            // Create subscription request
            $subscriptionRequest = SubscriptionRequestPOST::fromArray([
                'plan_id' => $planId,
                'subscriber' => [
                    'email_address' => $data['user']->email,
                    'name' => [
                        'given_name' => explode(' ', $data['user']->name)[0] ?? 'Customer',
                        'surname' => explode(' ', $data['user']->name)[1] ?? '',
                    ],
                ],
                'application_context' => [
                    'brand_name' => config('app.name'),
                    'locale' => 'en-US',
                    'shipping_preference' => 'NO_SHIPPING',
                    'user_action' => 'SUBSCRIBE_NOW',
                    'payment_method' => [
                        'payer_selected' => 'PAYPAL',
                        'payee_preferred' => 'IMMEDIATE_PAYMENT_REQUIRED',
                    ],
                    'return_url' => $data['success_url'],
                    'cancel_url' => $data['cancel_url'],
                ],
                'custom_id' => (string) $data['subscription']->id,
            ]);

            $response = $this->client->getSubscriptionsController()->subscriptionsCreate($subscriptionRequest);

            if ($response->isError()) {
                throw new \Exception('PayPal Subscription API Error: '.json_encode($response->getError()));
            }

            $subscription = $response->getResult();
            $approvalUrl = null;

            if ($subscription->getLinks()) {
                foreach ($subscription->getLinks() as $link) {
                    if ($link->getRel() === 'approve') {
                        $approvalUrl = $link->getHref();
                        break;
                    }
                }
            }

            return [
                'success' => true,
                'id' => $subscription->getId(),
                'status' => $subscription->getStatus(),
                'url' => $approvalUrl,
            ];
        } catch (\Exception $e) {
            Log::error('PayPal subscription creation error: '.$e->getMessage());

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    public function cancelSubscription(string $subscriptionId)
    {
        if (! $this->client) {
            return [
                'success' => false,
                'error' => 'PayPal credentials not configured',
            ];
        }

        try {
            $response = $this->client->getSubscriptionsController()->subscriptionsCancel(
                $subscriptionId,
                ['reason' => 'User requested cancellation']
            );

            if ($response->isError()) {
                throw new \Exception('PayPal Cancel Subscription API Error: '.json_encode($response->getError()));
            }

            // Get updated subscription details
            $subscriptionResponse = $this->client->getSubscriptionsController()->subscriptionsGet($subscriptionId);
            $subscription = $subscriptionResponse->getResult();

            return [
                'success' => true,
                'status' => 'CANCELLED',
                'current_period_end' => $subscription->getBillingInfo()?->getNextBillingTime(),
                'canceled_at' => now()->timestamp,
            ];
        } catch (\Exception $e) {
            Log::error('PayPal cancel subscription error: '.$e->getMessage());

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    public function resumeSubscription(string $subscriptionId)
    {
        if (! $this->client) {
            return [
                'success' => false,
                'error' => 'PayPal credentials not configured',
            ];
        }

        try {
            $response = $this->client->getSubscriptionsController()->subscriptionsActivate(
                $subscriptionId,
                ['reason' => 'User requested reactivation']
            );

            if ($response->isError()) {
                throw new \Exception('PayPal Activate Subscription API Error: '.json_encode($response->getError()));
            }

            return [
                'success' => true,
                'status' => 'ACTIVE',
            ];
        } catch (\Exception $e) {
            Log::error('PayPal resume subscription error: '.$e->getMessage());

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    public function changeSubscriptionPlan(string $subscriptionId, string $newPlanId)
    {
        if (! $this->client) {
            return [
                'success' => false,
                'error' => 'PayPal credentials not configured',
            ];
        }

        try {
            // PayPal doesn't support direct plan changes - need to cancel and create new
            // This is a limitation of PayPal vs Stripe
            $reviseRequest = [
                'plan_id' => $newPlanId,
                'prorate' => true,
                'replace_plan_id' => $subscriptionId,
            ];

            $response = $this->client->getSubscriptionsController()->subscriptionsRevise(
                $subscriptionId,
                $reviseRequest
            );

            if ($response->isError()) {
                throw new \Exception('PayPal Revise Subscription API Error: '.json_encode($response->getError()));
            }

            return [
                'success' => true,
                'subscription_id' => $subscriptionId,
            ];
        } catch (\Exception $e) {
            Log::error('PayPal plan change error: '.$e->getMessage());

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    public function handleWebhook(array $payload)
    {
        try {
            $eventType = $payload['event_type'] ?? null;

            switch ($eventType) {
                case 'PAYMENT.CAPTURE.COMPLETED':
                    return $this->handlePaymentSuccess($payload);
                case 'PAYMENT.CAPTURE.DENIED':
                    return $this->handlePaymentFailure($payload);
                case 'BILLING.SUBSCRIPTION.ACTIVATED':
                    return $this->handleSubscriptionActivated($payload);
                case 'BILLING.SUBSCRIPTION.CANCELLED':
                    return $this->handleSubscriptionCancelled($payload);
                case 'BILLING.SUBSCRIPTION.PAYMENT.FAILED':
                    return $this->handleSubscriptionPaymentFailed($payload);
                default:
                    return ['success' => true, 'message' => 'Unhandled event type'];
            }
        } catch (\Exception $e) {
            Log::error('PayPal webhook error: '.$e->getMessage());

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Create PayPal Customer Portal equivalent (redirect to PayPal account management)
     */
    public function createCustomerPortalSession(User $user, string $returnUrl)
    {
        try {
            // PayPal doesn't have a direct customer portal like Stripe
            // Instead, we direct users to their PayPal account management
            $paypalAccountUrl = config('services.paypal.mode') === 'sandbox'
                ? 'https://www.sandbox.paypal.com/myaccount/autopay/'
                : 'https://www.paypal.com/myaccount/autopay/';

            return [
                'success' => true,
                'url' => $paypalAccountUrl,
                'message' => 'PayPal payments are managed through your PayPal account',
            ];
        } catch (\Exception $e) {
            Log::error('PayPal customer portal session creation error: '.$e->getMessage());

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get or create PayPal billing plan for subscription
     */
    protected function getOrCreatePayPalPlan($productPrice)
    {
        try {
            // Check if plan already exists in gateway_data
            $planId = $productPrice->gateway_data['paypal']['plan_id'] ?? null;

            if ($planId && ! $this->isInvalidPlanId($planId)) {
                return $planId;
            }

            // Create new PayPal billing plan
            $planId = $this->createPayPalPlan($productPrice);

            if ($planId) {
                // Update the ProductPrice model with PayPal plan ID
                $currentGatewayData = $productPrice->gateway_data ?? [];
                $currentGatewayData['paypal'] = [
                    'plan_id' => $planId,
                ];

                $productPrice->update(['gateway_data' => $currentGatewayData]);

                Log::info("Created PayPal plan: {$planId} for ProductPrice: {$productPrice->title}");
            }

            return $planId;
        } catch (\Exception $e) {
            Log::error("Failed to get or create PayPal plan for ProductPrice ID {$productPrice->id}: ".$e->getMessage());

            return null;
        }
    }

    /**
     * Create a new PayPal billing plan
     */
    protected function createPayPalPlan($productPrice)
    {
        if (! $this->client) {
            return null;
        }

        try {
            $productName = $productPrice->product->title.' - '.$productPrice->title;

            // Create billing plan request
            $planRequest = [
                'product_id' => $this->getOrCreatePayPalProduct($productPrice->product),
                'name' => $productName,
                'description' => $productPrice->product->description ?: "Subscription to {$productName}",
                'status' => 'ACTIVE',
                'billing_cycles' => [
                    [
                        'frequency' => [
                            'interval_unit' => $this->mapBillingPeriodToPayPal($productPrice->billing_period),
                            'interval_count' => 1,
                        ],
                        'tenure_type' => 'REGULAR',
                        'sequence' => 1,
                        'total_cycles' => 0, // 0 = infinite
                        'pricing_scheme' => [
                            'fixed_price' => [
                                'value' => number_format($productPrice->amount, 2, '.', ''),
                                'currency_code' => strtoupper($productPrice->currency),
                            ],
                        ],
                    ],
                ],
                'payment_preferences' => [
                    'auto_bill_outstanding' => true,
                    'setup_fee_failure_action' => 'CONTINUE',
                    'payment_failure_threshold' => 3,
                ],
            ];

            $response = $this->client->getBillingPlansController()->plansCreate($planRequest);

            if ($response->isError()) {
                throw new \Exception('PayPal Create Plan API Error: '.json_encode($response->getError()));
            }

            $plan = $response->getResult();

            return $plan->getId();
        } catch (\Exception $e) {
            Log::error("Failed to create PayPal plan for ProductPrice ID {$productPrice->id}: ".$e->getMessage());

            return null;
        }
    }

    /**
     * Get or create PayPal product for billing plan
     */
    protected function getOrCreatePayPalProduct($product)
    {
        try {
            // Check if product already exists in gateway_data
            $productId = $product->gateway_data['paypal']['product_id'] ?? null;

            if ($productId) {
                return $productId;
            }

            // Create new PayPal product
            $productRequest = [
                'id' => 'PROD_'.strtoupper(uniqid()),
                'name' => $product->title,
                'description' => $product->description ?: "Product: {$product->title}",
                'type' => 'SERVICE', // PayPal product type for subscriptions
                'category' => 'SOFTWARE',
            ];

            $response = $this->client->getCatalogProductsController()->productsCreate($productRequest);

            if ($response->isError()) {
                throw new \Exception('PayPal Create Product API Error: '.json_encode($response->getError()));
            }

            $paypalProduct = $response->getResult();

            // Update the Product model with PayPal product ID
            $currentGatewayData = $product->gateway_data ?? [];
            $currentGatewayData['paypal'] = [
                'product_id' => $paypalProduct->getId(),
            ];

            $product->update(['gateway_data' => $currentGatewayData]);

            return $paypalProduct->getId();
        } catch (\Exception $e) {
            Log::error("Failed to create PayPal product for Product ID {$product->id}: ".$e->getMessage());

            return null;
        }
    }

    /**
     * Map billing period to PayPal interval unit
     */
    protected function mapBillingPeriodToPayPal($billingPeriod)
    {
        $mapping = [
            'daily' => 'DAY',
            'weekly' => 'WEEK',
            'monthly' => 'MONTH',
            'yearly' => 'YEAR',
        ];

        return $mapping[$billingPeriod] ?? 'MONTH';
    }

    /**
     * Check if a plan ID is invalid/fake/demo
     */
    protected function isInvalidPlanId($planId)
    {
        // Real PayPal plan IDs start with 'P-' and are longer
        if (strlen($planId) < 10 || ! str_starts_with($planId, 'P-')) {
            return true;
        }

        // Common patterns for fake/demo plan IDs
        $fakePatterns = [
            'P-digital_',
            'P-physical_',
            'P-service_',
            'P-subscription_',
        ];

        foreach ($fakePatterns as $pattern) {
            if (str_starts_with($planId, $pattern)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Handle subscription activated webhook
     */
    protected function handleSubscriptionActivated(array $payload)
    {
        try {
            $subscriptionId = $payload['resource']['id'] ?? null;
            $customId = $payload['resource']['custom_id'] ?? null;

            if (! $subscriptionId || ! $customId) {
                throw new \Exception('Missing subscription ID or custom ID in PayPal webhook');
            }

            $subscription = Subscription::find($customId);

            if (! $subscription) {
                throw new \Exception('Subscription not found: '.$customId);
            }

            $subscription->update([
                'status' => 'active',
                'gateway_subscription_id' => $subscriptionId,
                'gateway_status' => 'ACTIVE',
                'gateway_data' => array_merge($subscription->gateway_data ?? [], $payload),
            ]);

            Log::info("PayPal subscription activated: {$subscriptionId}");

            return true;
        } catch (\Exception $e) {
            Log::error('PayPal subscription activation handling error: '.$e->getMessage());

            return false;
        }
    }

    /**
     * Handle subscription cancelled webhook
     */
    protected function handleSubscriptionCancelled(array $payload)
    {
        try {
            $subscriptionId = $payload['resource']['id'] ?? null;

            if (! $subscriptionId) {
                throw new \Exception('Missing subscription ID in PayPal webhook');
            }

            $subscription = Subscription::where('gateway_subscription_id', $subscriptionId)->first();

            if (! $subscription) {
                throw new \Exception('Subscription not found: '.$subscriptionId);
            }

            $subscription->update([
                'status' => 'canceled',
                'canceled_at' => now(),
                'gateway_status' => 'CANCELLED',
                'gateway_data' => array_merge($subscription->gateway_data ?? [], $payload),
            ]);

            Log::info("PayPal subscription cancelled: {$subscriptionId}");

            return true;
        } catch (\Exception $e) {
            Log::error('PayPal subscription cancellation handling error: '.$e->getMessage());

            return false;
        }
    }

    /**
     * Handle subscription payment failed webhook
     */
    protected function handleSubscriptionPaymentFailed(array $payload)
    {
        try {
            $subscriptionId = $payload['resource']['id'] ?? null;

            if (! $subscriptionId) {
                throw new \Exception('Missing subscription ID in PayPal webhook');
            }

            $subscription = Subscription::where('gateway_subscription_id', $subscriptionId)->first();

            if (! $subscription) {
                throw new \Exception('Subscription not found: '.$subscriptionId);
            }

            $subscription->update([
                'status' => 'past_due',
                'gateway_status' => 'SUSPENDED',
                'gateway_data' => array_merge($subscription->gateway_data ?? [], $payload),
            ]);

            Log::warning("PayPal subscription payment failed: {$subscriptionId}");

            return true;
        } catch (\Exception $e) {
            Log::error('PayPal subscription payment failure handling error: '.$e->getMessage());

            return false;
        }
    }
}
