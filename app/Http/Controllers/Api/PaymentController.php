<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\Subscription;
use App\Services\Payment\PaymentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class PaymentController extends Controller
{
    protected $paymentService;

    public function __construct(PaymentService $paymentService)
    {
        $this->paymentService = $paymentService;
    }

    public function getProducts()
    {
        $products = Product::active()->with('activePrices')->get();

        return response()->json(['products' => $products]);
    }

    public function getProduct(Product $product)
    {
        $product->load('activePrices');

        return response()->json(['product' => $product]);
    }

    public function createCheckoutSession(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'product_price_id' => 'required|exists:product_prices,id',
            'gateway' => 'required|in:stripe,paypal',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            $productPrice = ProductPrice::active()->findOrFail($request->product_price_id);

            $result = $this->paymentService->createCheckoutSession(
                auth()->user(),
                $productPrice,
                ['gateway' => $request->gateway]
            );

            if (! $result['success']) {
                $errorMessage = $result['message'] ?? $result['error'] ?? 'Payment processing failed';
                Log::error('Payment gateway error for user '.auth()->id().': '.$errorMessage);

                return response()->json([
                    'error' => $errorMessage,
                    'message' => $errorMessage,
                ], 400);
            }

            return response()->json($result);
        } catch (\Exception $e) {
            Log::error('Checkout session creation failed: '.$e->getMessage());

            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function createSubscription(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'product_price_id' => 'required|exists:product_prices,id',
            'gateway' => 'required|in:stripe,paypal',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            $productPrice = ProductPrice::active()->findOrFail($request->product_price_id);

            // Ensure it's a subscription product price
            if ($productPrice->billing_period === 'once') {
                return response()->json(['error' => 'This product is not a subscription'], 422);
            }

            $options = [
                'gateway' => $request->gateway,
                'success_url' => $request->get('success_url', url('/payment/success/test')),
                'cancel_url' => $request->get('cancel_url', url('/payment/cancel/test')),
            ];

            $result = $this->paymentService->createSubscription(
                auth()->user(),
                $productPrice,
                $options
            );

            return response()->json($result);
        } catch (\Exception $e) {
            Log::error('Subscription creation failed: '.$e->getMessage());

            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function getOrders()
    {
        $orders = auth()->user()->orders()
            ->with(['productPrice.product', 'transactions', 'fulfillments'])
            ->latest()
            ->paginate(15);

        return response()->json(['orders' => $orders]);
    }

    public function getOrder(Order $order)
    {
        if ($order->user_id !== auth()->id()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $order->load(['productPrice.product', 'transactions', 'fulfillments']);

        return response()->json(['order' => $order]);
    }

    public function getSubscriptions()
    {
        $subscriptions = auth()->user()->subscriptions()
            ->with(['productPrice.product', 'order'])
            ->latest()
            ->paginate(15);

        return response()->json(['subscriptions' => $subscriptions]);
    }

    public function getSubscription(Subscription $subscription)
    {
        if ($subscription->user_id !== auth()->id()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $subscription->load(['productPrice.product', 'order', 'transactions']);

        return response()->json(['subscription' => $subscription]);
    }

    public function cancelSubscription($subscriptionId)
    {
        $subscription = Subscription::find($subscriptionId);

        if (! $subscription) {
            return response()->json(['error' => 'Subscription not found'], 404);
        }

        if ($subscription->user_id !== auth()->id()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        try {
            $result = $this->paymentService->cancelSubscription($subscription);

            return response()->json($result);
        } catch (\Exception $e) {
            Log::error('Subscription cancellation failed: '.$e->getMessage());

            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function resumeSubscription(Subscription $subscription)
    {
        if ($subscription->user_id !== auth()->id()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        try {
            $result = $this->paymentService->resumeSubscription($subscription);

            return response()->json($result);
        } catch (\Exception $e) {
            Log::error('Subscription resumption failed: '.$e->getMessage());

            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function changeSubscriptionPlan(Request $request, Subscription $subscription)
    {
        if ($subscription->user_id !== auth()->id()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $validator = Validator::make($request->all(), [
            'new_product_price_id' => 'required|exists:product_prices,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            $newProductPrice = ProductPrice::active()->findOrFail($request->new_product_price_id);

            if ($newProductPrice->billing_period === 'once') {
                return response()->json(['error' => 'Target product is not a subscription'], 422);
            }

            $result = $this->paymentService->changeSubscriptionPlan($subscription, $newProductPrice);

            return response()->json($result);
        } catch (\Exception $e) {
            Log::error('Subscription plan change failed: '.$e->getMessage());

            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function createOrder(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'product_price_id' => 'required|exists:product_prices,id',
            'gateway' => 'required|in:stripe,paypal',
            'quantity' => 'sometimes|integer|min:1|max:10',
            'success_url' => 'required|url',
            'cancel_url' => 'required|url',
            'customer_note' => 'sometimes|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            $productPrice = ProductPrice::active()->findOrFail($request->product_price_id);

            $options = [
                'gateway' => $request->gateway,
                'success_url' => $request->success_url,
                'cancel_url' => $request->cancel_url,
                'quantity' => $request->get('quantity', 1),
                'customer_note' => $request->get('customer_note'),
            ];

            if ($productPrice->billing_period === 'once') {
                $result = $this->paymentService->createCheckoutSession(
                    auth()->user(),
                    $productPrice,
                    $options
                );
            } else {
                $result = $this->paymentService->createSubscription(
                    auth()->user(),
                    $productPrice,
                    $options
                );
            }

            if (! $result['success']) {
                $errorMessage = $result['message'] ?? $result['error'] ?? 'Payment processing failed';
                Log::error('Payment gateway error for user '.auth()->id().': '.$errorMessage);

                return response()->json([
                    'error' => $errorMessage,
                    'message' => $errorMessage,
                ], 400);
            }

            return response()->json($result);
        } catch (\Exception $e) {
            Log::error('Order creation failed: '.$e->getMessage(), [
                'user_id' => auth()->id(),
                'product_price_id' => $request->product_price_id,
                'gateway' => $request->gateway,
                'trace' => $e->getTraceAsString(),
            ]);

            // Provide user-friendly error messages
            $userMessage = $e->getMessage();
            if (str_contains($e->getMessage(), 'already has an active subscription')) {
                $userMessage = 'You already have an active subscription for this product. Please cancel your existing subscription first or contact support.';
            } elseif (str_contains($e->getMessage(), 'not a subscription')) {
                $userMessage = 'This product is not available as a subscription. Please try purchasing it as a one-time payment.';
            } elseif (str_contains($e->getMessage(), 'email is required')) {
                $userMessage = 'A valid email address is required to process your payment.';
            }

            return response()->json([
                'error' => $userMessage,
                'message' => $userMessage,
            ], 500);
        }
    }

    public function handleStripeWebhook(Request $request)
    {
        Log::critical('=== API PaymentController webhook handler called ===');

        $payload = $request->getContent();
        $sig_header = $request->header('Stripe-Signature');

        Log::info('Stripe webhook received', [
            'payload_length' => strlen($payload),
            'has_signature' => ! empty($sig_header),
            'webhook_secret_configured' => ! empty(config('services.stripe.webhook_secret')),
            'server_time' => now()->toISOString(),
            'app_env' => config('app.env'),
        ]);

        try {
            // Temporarily disable signature verification for testing
            if (config('app.env') === 'local') {
                Log::info('Skipping webhook signature verification in local environment');
                $event = json_decode($payload, true);
            } else {
                // Increase tolerance to 600 seconds (10 minutes) for development
                $event = \Stripe\Webhook::constructEvent(
                    $payload, $sig_header, config('services.stripe.webhook_secret'), 600
                );
            }

            Log::info('Stripe webhook event', [
                'type' => $event['type'] ?? 'unknown',
                'id' => $event['id'] ?? 'unknown',
            ]);

            // Use the StripeGateway to handle webhooks
            $stripeGateway = app(\App\Services\Payment\StripeGateway::class);
            $result = $stripeGateway->handleWebhook((array) $event);

            if (! $result['success']) {
                throw new \Exception($result['error'] ?? 'Webhook handling failed');
            }

            Log::info('Stripe webhook processed successfully');

            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            Log::error('Stripe webhook error: '.$e->getMessage(), [
                'payload_snippet' => substr($payload, 0, 200),
                'signature_present' => ! empty($sig_header),
            ]);

            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * Get available plans for subscription change
     */
    public function getAvailablePlans(Subscription $subscription)
    {
        if ($subscription->user_id !== auth()->id()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        try {
            // Get all active product prices excluding the current subscription's plan
            $availablePlans = ProductPrice::active()
                ->where('id', '!=', $subscription->product_price_id)
                ->with('product')
                ->get();

            return response()->json([
                'success' => true,
                'available_plans' => $availablePlans,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to get available plans: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'error' => 'Failed to load available plans',
            ], 500);
        }
    }

    public function handlePayPalWebhook(Request $request)
    {
        $payload = $request->all();

        try {
            $event_type = $payload['event_type'] ?? '';

            switch ($event_type) {
                case 'PAYMENT.CAPTURE.COMPLETED':
                    $this->paymentService->handlePayPalPaymentSuccess($payload);
                    break;
                case 'PAYMENT.CAPTURE.DENIED':
                    $this->paymentService->handlePayPalPaymentFailed($payload);
                    break;
                case 'BILLING.SUBSCRIPTION.CREATED':
                    $this->paymentService->handlePayPalSubscriptionCreated($payload);
                    break;
                case 'BILLING.SUBSCRIPTION.CANCELLED':
                    $this->paymentService->handlePayPalSubscriptionCancelled($payload);
                    break;
                    // Handle other event types
            }

            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            Log::error('PayPal webhook error: '.$e->getMessage());

            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * Create customer portal session for payment method management
     */
    public function createCustomerPortalSession(Request $request)
    {
        $user = auth()->user();

        $validator = Validator::make($request->all(), [
            'gateway' => 'required|in:stripe,paypal',
            'return_url' => 'sometimes|url',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            $gateway = $request->input('gateway', 'stripe');
            $returnUrl = $request->input('return_url', url('/payment/subscriptions'));

            $result = $this->paymentService->createCustomerPortalSession($user, $gateway, $returnUrl);

            if ($result['success']) {
                return response()->json([
                    'success' => true,
                    'url' => $result['url'],
                    'gateway' => $gateway,
                ]);
            } else {
                return response()->json(['error' => $result['error']], 400);
            }
        } catch (\Exception $e) {
            Log::error('Customer portal session creation failed: '.$e->getMessage());

            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Get customer portal session for a specific subscription
     */
    public function getSubscriptionCustomerPortal(Request $request, Subscription $subscription)
    {
        if ($subscription->user_id !== auth()->id()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $validator = Validator::make($request->all(), [
            'return_url' => 'sometimes|url',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            $returnUrl = $request->input('return_url', url('/payment/subscriptions'));

            $result = $this->paymentService->createCustomerPortalSession(
                auth()->user(),
                $subscription->gateway,
                $returnUrl
            );

            if ($result['success']) {
                return response()->json([
                    'success' => true,
                    'url' => $result['url'],
                    'gateway' => $subscription->gateway,
                ]);
            } else {
                return response()->json(['error' => $result['error']], 400);
            }
        } catch (\Exception $e) {
            Log::error('Subscription customer portal creation failed: '.$e->getMessage());

            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
