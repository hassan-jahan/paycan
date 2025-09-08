<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\Subscription;
use App\Services\Payment\PaymentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class PaymentController extends Controller
{
    protected $paymentService;

    public function __construct(PaymentService $paymentService)
    {
        $this->paymentService = $paymentService;

        // Require authentication for all methods except webhooks and products
        $this->middleware('auth')->except(['handleStripeWebhook', 'handlePayPalWebhook', 'products']);

        // Disable CSRF for webhooks
        $this->middleware('web')->except(['handleStripeWebhook', 'handlePayPalWebhook']);
    }

    /**
     * Show products listing page
     */
    public function products()
    {
        $products = Product::with(['activePrices'])
            ->where('is_active', true)
            ->orderBy('type')
            ->orderBy('title')
            ->get()
            ->groupBy('type');

        return Inertia::render('Products/Index', [
            'productsByType' => $products,
        ]);
    }

    /**
     * Show the checkout page
     */
    public function checkout(ProductPrice $productPrice)
    {
        return Inertia::render('Checkout/Index', [
            'productPrice' => $productPrice->load('product'),
        ]);
    }

    /**
     * Process checkout and redirect to payment gateway
     */
    public function processCheckout(Request $request, ProductPrice $productPrice)
    {
        $request->validate([
            'gateway' => 'required|in:stripe,paypal',
            'quantity' => 'nullable|integer|min:1',
        ]);

        try {
            $response = $this->paymentService->createCheckoutSession(
                auth()->user(),
                $productPrice,
                [
                    'gateway' => $request->gateway,
                    'quantity' => $request->quantity ?? 1,
                ]
            );

            if (! $response['success']) {
                return redirect()->back()->withErrors(['message' => $response['message'] ?? 'Payment processing failed']);
            }

            // Redirect to payment gateway checkout page
            return redirect($response['url']);
        } catch (\Exception $e) {
            Log::error('Checkout error: '.$e->getMessage());

            return redirect()->back()->withErrors(['message' => 'An error occurred while processing your payment']);
        }
    }

    /**
     * Handle successful payment
     */
    public function success(Request $request, Order $order)
    {
        // Verify the order belongs to the authenticated user
        if ($order->user_id !== auth()->id()) {
            abort(403);
        }

        return Inertia::render('Checkout/Success', [
            'order' => $order->load(['productPrice.product']),
        ]);
    }

    /**
     * Handle cancelled payment
     */
    public function cancel(Request $request, Order $order)
    {
        // Verify the order belongs to the authenticated user
        if ($order->user_id !== auth()->id()) {
            abort(403);
        }

        return Inertia::render('Checkout/Cancelled', [
            'order' => $order->load(['productPrice.product']),
        ]);
    }

    /**
     * Show subscription checkout page
     */
    public function subscriptionCheckout(ProductPrice $productPrice)
    {
        // Verify that the product price is a subscription
        if ($productPrice->billing_period === 'once') {
            abort(404, 'This is not a subscription product');
        }

        return Inertia::render('Checkout/Subscription', [
            'productPrice' => $productPrice->load('product'),
        ]);
    }

    /**
     * Process subscription checkout
     */
    public function processSubscriptionCheckout(Request $request, ProductPrice $productPrice)
    {
        $request->validate([
            'gateway' => 'required|in:stripe,paypal',
        ]);

        // Verify that the product price is a subscription
        if ($productPrice->billing_period === 'once') {
            return redirect()->back()->withErrors(['message' => 'This is not a subscription product']);
        }

        try {
            $response = $this->paymentService->createSubscription(
                auth()->user(),
                $productPrice,
                [
                    'gateway' => $request->gateway,
                ]
            );

            if (! $response['success']) {
                return redirect()->back()->withErrors(['message' => $response['message'] ?? 'Subscription processing failed']);
            }

            // For Stripe, return client secret for payment element
            if ($request->gateway === 'stripe' && isset($response['client_secret'])) {
                return Inertia::render('Checkout/StripeSubscriptionPayment', [
                    'clientSecret' => $response['client_secret'],
                    'subscription' => $response['id'],
                ]);
            }

            // For PayPal, redirect to PayPal
            if ($request->gateway === 'paypal' && isset($response['url'])) {
                return redirect($response['url']);
            }

            // Fallback success
            return redirect()->route('subscription.success');
        } catch (\Exception $e) {
            Log::error('Subscription checkout error: '.$e->getMessage());

            return redirect()->back()->withErrors(['message' => 'An error occurred while processing your subscription']);
        }
    }

    /**
     * Handle Stripe webhook
     */
    public function handleStripeWebhook(Request $request)
    {
        Log::critical('=== WEB PaymentController webhook handler called ===');

        $payload = $request->getContent();
        $sigHeader = $request->header('Stripe-Signature');

        Log::info('Stripe webhook received', [
            'payload_length' => strlen($payload),
            'has_signature' => ! empty($sigHeader),
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
                // Verify webhook signature with increased tolerance
                $stripeSecret = config('services.stripe.webhook_secret');
                $event = \Stripe\Webhook::constructEvent($payload, $sigHeader, $stripeSecret, 600);
            }

            Log::info('Stripe webhook event', [
                'type' => $event['type'] ?? 'unknown',
                'id' => $event['id'] ?? 'unknown',
            ]);

            // Process the event
            $paymentGateway = app(\App\Services\Payment\StripeGateway::class);
            $result = $paymentGateway->handleWebhook((array) $event);

            Log::info('Stripe webhook processed successfully');

            return response()->json(['success' => $result]);
        } catch (\Exception $e) {
            Log::error('Stripe webhook error: '.$e->getMessage(), [
                'payload_snippet' => substr($payload, 0, 200),
                'signature_present' => ! empty($sigHeader),
            ]);

            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * Handle PayPal webhook
     */
    public function handlePayPalWebhook(Request $request)
    {
        try {
            // Process the event
            $paymentGateway = app(\App\Services\Payment\PayPalGateway::class);
            $result = $paymentGateway->handleWebhook($request->all());

            return response()->json(['success' => $result]);
        } catch (\Exception $e) {
            Log::error('PayPal webhook error: '.$e->getMessage());

            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * View user's orders
     */
    public function orders()
    {
        $orders = auth()->user()->orders()->with('productPrice.product')->latest()->paginate(10);

        return Inertia::render('Orders/Index', [
            'orders' => $orders,
        ]);
    }

    /**
     * View order details
     */
    public function viewOrder(Order $order)
    {
        // Verify the order belongs to the authenticated user
        if ($order->user_id !== auth()->id()) {
            abort(403);
        }

        $order->load(['productPrice.product', 'transactions', 'fulfillments']);

        return Inertia::render('Orders/Detail', [
            'order' => $order,
        ]);
    }

    /**
     * View user's subscriptions
     */
    public function subscriptions()
    {
        $subscriptions = auth()->user()->subscriptions()->with('productPrice.product')->latest()->paginate(10);

        return Inertia::render('Account/Subscriptions', [
            'subscriptions' => $subscriptions,
        ]);
    }

    /**
     * View subscription details
     */
    public function viewSubscription(Subscription $subscription)
    {
        // Verify the subscription belongs to the authenticated user
        if ($subscription->user_id !== auth()->id()) {
            abort(403);
        }

        $subscription->load(['productPrice.product', 'order', 'transactions']);

        // Get available plans for the same product for plan changing
        $availablePlans = $subscription->productPrice->product
            ->activePrices()
            ->where('billing_period', '!=', 'once') // Only subscription plans
            ->orderBy('amount')
            ->get();

        return Inertia::render('Account/SubscriptionDetail', [
            'subscription' => $subscription,
            'availablePlans' => $availablePlans,
        ]);
    }

    /**
     * Cancel a subscription
     */
    public function cancelSubscription(Request $request, Subscription $subscription)
    {
        // Verify the subscription belongs to the authenticated user
        if ($subscription->user_id !== auth()->id()) {
            abort(403);
        }

        try {
            $response = $this->paymentService->cancelSubscription($subscription);

            if (! $response['success']) {
                return redirect()->back()->withErrors(['message' => $response['message'] ?? 'Failed to cancel subscription']);
            }

            return redirect()->back()->with('success', 'Subscription has been cancelled');
        } catch (\Exception $e) {
            Log::error('Subscription cancellation error: '.$e->getMessage());

            return redirect()->back()->withErrors(['message' => 'An error occurred while cancelling your subscription']);
        }
    }

    /**
     * Resume a subscription
     */
    public function resumeSubscription(Request $request, Subscription $subscription)
    {
        // Verify the subscription belongs to the authenticated user
        if ($subscription->user_id !== auth()->id()) {
            abort(403);
        }

        // Verify the subscription can be resumed
        if ($subscription->status !== 'canceled' || ! $subscription->ends_at || $subscription->ends_at->isPast()) {
            return redirect()->back()->withErrors(['message' => 'This subscription cannot be resumed']);
        }

        try {
            $response = $this->paymentService->resumeSubscription($subscription);

            if (! $response['success']) {
                return redirect()->back()->withErrors(['message' => $response['message'] ?? 'Failed to resume subscription']);
            }

            return redirect()->back()->with('success', 'Subscription has been resumed');
        } catch (\Exception $e) {
            Log::error('Subscription resume error: '.$e->getMessage());

            return redirect()->back()->withErrors(['message' => 'An error occurred while resuming your subscription']);
        }
    }

    /**
     * Change subscription plan
     */
    public function changeSubscriptionPlan(Request $request, Subscription $subscription)
    {
        // Verify the subscription belongs to the authenticated user
        if ($subscription->user_id !== auth()->id()) {
            abort(403);
        }

        $request->validate([
            'new_product_price_id' => 'required|exists:product_prices,id',
        ]);

        try {
            $newProductPrice = ProductPrice::active()->findOrFail($request->new_product_price_id);

            // Verify the new price is a subscription and for the same product
            if ($newProductPrice->billing_period === 'once') {
                return redirect()->back()->withErrors(['message' => 'Target plan is not a subscription']);
            }

            if ($newProductPrice->product_id !== $subscription->productPrice->product_id) {
                return redirect()->back()->withErrors(['message' => 'Cannot change to a different product']);
            }

            $response = $this->paymentService->changeSubscriptionPlan($subscription, $newProductPrice);

            if (! $response['success']) {
                return redirect()->back()->withErrors(['message' => $response['message'] ?? 'Failed to change subscription plan']);
            }

            return redirect()->back()->with('success', 'Subscription plan has been changed successfully');
        } catch (\Exception $e) {
            Log::error('Subscription plan change error: '.$e->getMessage());

            return redirect()->back()->withErrors(['message' => 'An error occurred while changing your plan']);
        }
    }
}
