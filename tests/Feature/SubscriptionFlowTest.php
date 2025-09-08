<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\Subscription;
use App\Models\User;

class SubscriptionFlowTest extends BaseApiTest
{
    /**
     * Test complete subscription flow: register → subscribe → cancel → resume
     */
    public function test_complete_subscription_lifecycle()
    {
        // Step 1: Create a subscription product
        $product = Product::factory()->subscription()->create([
            'title' => 'Premium Monthly Plan',
            'type' => 'subscription',
        ]);

        $productPrice = ProductPrice::factory()->monthly()->create([
            'product_id' => $product->id,
            'title' => 'Monthly',
            'amount' => 29.99,
            'billing_period' => 'monthly',
            'trial_days' => 7,
        ]);

        // Step 2: User registration
        $userData = [
            'name' => 'John Doe',
            'email' => 'john.doe@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ];

        $registrationResponse = $this->postJson('/api/register', $userData);

        $this->assertApiResponse($registrationResponse, 201, [
            'user' => ['id', 'name', 'email'],
            'access_token',
        ]);

        $user = User::where('email', $userData['email'])->first();
        $this->assertNotNull($user);

        // Step 3: Authenticate user
        $token = $registrationResponse->json('access_token');
        $headers = ['Authorization' => 'Bearer '.$token];

        // Step 4: Get available products
        $productsResponse = $this->getJson('/api/payments/products', $headers);

        $this->assertApiResponse($productsResponse, 200, [
            'products' => [
                '*' => ['id', 'title', 'type', 'active_prices'],
            ],
        ]);

        // Step 5: Create subscription
        $subscriptionData = [
            'product_price_id' => $productPrice->id,
            'gateway' => 'stripe',
        ];

        $subscriptionResponse = $this->postJson('/api/payments/subscribe', $subscriptionData, $headers);

        $this->assertApiResponse($subscriptionResponse, 200, [
            'success',
            'id',
        ]);

        // Verify subscription was created in database
        $this->assertDatabaseHasSubscription([
            'user_id' => $user->id,
            'product_price_id' => $productPrice->id,
            'status' => 'incomplete', // Initial status before payment confirmation
        ]);

        // Step 6: Simulate successful payment webhook (subscription activation)
        $subscription = Subscription::where('user_id', $user->id)->first();
        $subscription->update([
            'status' => 'trialing', // Since it has trial_days
            'gateway_subscription_id' => 'sub_test_123',
        ]);

        // Step 7: Verify subscription is active
        $subscriptionsResponse = $this->getJson('/api/payments/subscriptions', $headers);

        $this->assertApiResponse($subscriptionsResponse, 200, [
            'subscriptions' => [
                'data' => [
                    '*' => [
                        'id', 'title', 'status', 'trial_ends_at', 'product_price',
                    ],
                ],
            ],
        ]);

        $subscriptionData = $subscriptionsResponse->json('subscriptions.data.0');
        $this->assertEquals('trialing', $subscriptionData['status']);

        // Step 8: Cancel subscription
        $cancelResponse = $this->putJson(
            "/api/payments/subscriptions/{$subscription->id}/cancel",
            [],
            $headers
        );

        $this->assertApiResponse($cancelResponse, 200, ['success']);

        // Verify cancellation in database
        $subscription->refresh();
        $this->assertEquals('canceled', $subscription->status);
        $this->assertNotNull($subscription->canceled_at);

        // Step 9: Resume subscription (if still within grace period)
        $resumeResponse = $this->putJson(
            "/api/payments/subscriptions/{$subscription->id}/resume",
            [],
            $headers
        );

        $this->assertApiResponse($resumeResponse, 200, ['success']);

        // Verify resumption in database
        $subscription->refresh();
        $this->assertEquals('active', $subscription->status);
        $this->assertNull($subscription->canceled_at);

        // Step 10: Get subscription details
        $detailsResponse = $this->getJson(
            "/api/payments/subscriptions/{$subscription->id}",
            $headers
        );

        $this->assertApiResponse($detailsResponse, 200, [
            'subscription' => [
                'id', 'title', 'status', 'product_price', 'order', 'transactions',
            ],
        ]);

        $finalSubscription = $detailsResponse->json('subscription');
        $this->assertEquals('active', $finalSubscription['status']);
        $this->assertEquals($productPrice->id, $finalSubscription['product_price']['id']);
    }

    /**
     * Test subscription with different gateways
     */
    public function test_subscription_with_paypal()
    {
        $user = $this->authenticateUser();

        $product = Product::factory()->subscription()->create();
        $productPrice = ProductPrice::factory()->monthly()->create([
            'product_id' => $product->id,
            'trial_days' => 0, // No trial for PayPal test
        ]);

        $subscriptionData = [
            'product_price_id' => $productPrice->id,
            'gateway' => 'paypal',
        ];

        $response = $this->postJson('/api/payments/subscribe', $subscriptionData);

        $this->assertApiResponse($response, 200);

        // Verify subscription created with PayPal gateway
        $this->assertDatabaseHasSubscription([
            'user_id' => $user->id,
            'gateway' => 'paypal',
        ]);
    }

    /**
     * Test subscription plan change - E2E flow
     */
    public function test_subscription_plan_change()
    {
        // Mock the payment gateway for both subscription creation and plan change
        $this->mock(\App\Services\Payment\StripeGateway::class, function ($mock) {
            // Mock checkout session creation (used for subscriptions)
            $mock->shouldReceive('createCheckoutSession')
                ->andReturn([
                    'success' => true,
                    'id' => 'cs_test_checkout_session_123',
                    'url' => 'https://checkout.stripe.com/pay/test',
                    'status' => 'open',
                ]);

            // Mock getOrCreateStripePrice for plan changes
            $mock->shouldReceive('getOrCreateStripePrice')
                ->andReturn('price_1XYZABCDEFGHIJKLtest456');

            // Mock plan change
            $mock->shouldReceive('changeSubscriptionPlan')
                ->andReturn([
                    'success' => true,
                    'subscription' => (object) ['id' => 'sub_test_123'],
                ]);
        });

        $user = $this->authenticateUser();

        $product = Product::factory()->subscription()->create();

        // Create two different price tiers with realistic gateway data (28+ chars for Stripe)
        $basicPrice = ProductPrice::factory()->monthly()->create([
            'product_id' => $product->id,
            'title' => 'Basic',
            'amount' => 19.99,
            'gateway_data' => [
                'stripe' => ['price_id' => 'price_1ABCDEFGHIJKLMNOPtest123'],
                'paypal' => ['price_id' => 'P-basic-monthly'],
            ],
        ]);

        $premiumPrice = ProductPrice::factory()->monthly()->create([
            'product_id' => $product->id,
            'title' => 'Premium',
            'amount' => 39.99,
            'gateway_data' => [
                'stripe' => ['price_id' => 'price_1XYZABCDEFGHIJKLtest456'],
                'paypal' => ['price_id' => 'P-premium-monthly'],
            ],
        ]);

        // Step 1: Create initial subscription order (like a real user would)
        $checkoutResponse = $this->postJson('/api/payments/subscribe', [
            'product_price_id' => $basicPrice->id,
            'gateway' => 'stripe',
        ]);
        $this->assertApiResponse($checkoutResponse, 200);

        // Step 2: Get the created order and subscription
        $order = Order::where('user_id', $user->id)->first();
        $this->assertNotNull($order);

        // Simulate payment success
        $order->update(['status' => 'completed']);

        $subscription = Subscription::where('user_id', $user->id)->first();
        $subscription->update([
            'status' => 'active',
            'gateway_subscription_id' => 'sub_stripe_test_123',
        ]);

        // Step 3: Change to premium plan (real E2E scenario)
        $changeResponse = $this->putJson(
            "/api/payments/subscriptions/{$subscription->id}/change-plan",
            ['new_product_price_id' => $premiumPrice->id]
        );

        $this->assertApiResponse($changeResponse, 200);

        // Verify plan change in database
        $subscription->refresh();
        $this->assertEquals($premiumPrice->id, $subscription->product_price_id);
    }

    /**
     * Test subscription errors and edge cases
     */
    public function test_subscription_error_cases()
    {
        $user = $this->authenticateUser();

        // Try to subscribe to one-time product
        $product = Product::factory()->physical()->create();
        $oneTimePrice = ProductPrice::factory()->oneTime()->create([
            'product_id' => $product->id,
        ]);

        $response = $this->postJson('/api/payments/subscribe', [
            'product_price_id' => $oneTimePrice->id,
            'gateway' => 'stripe',
        ]);

        $this->assertApiResponse($response, 422);
        $this->assertStringContainsString('not a subscription', $response->json('error'));

        // Try to access another user's subscription (E2E flow)
        $otherUser = User::factory()->create();

        // Create subscription for other user through proper E2E flow
        \Laravel\Sanctum\Sanctum::actingAs($otherUser);
        $subscriptionProduct = Product::factory()->subscription()->create();
        $subscriptionPrice = ProductPrice::factory()->monthly()->create([
            'product_id' => $subscriptionProduct->id,
        ]);

        $otherSubscriptionResponse = $this->postJson('/api/payments/subscribe', [
            'product_price_id' => $subscriptionPrice->id,
            'gateway' => 'stripe',
        ]);
        $this->assertApiResponse($otherSubscriptionResponse, 200);

        $otherSubscription = Subscription::where('user_id', $otherUser->id)->first();

        // Switch back to original user and try to access other user's subscription
        \Laravel\Sanctum\Sanctum::actingAs($user);
        $accessResponse = $this->getJson("/api/payments/subscriptions/{$otherSubscription->id}");
        $this->assertApiResponse($accessResponse, 403);
    }

    /**
     * Test subscription with trial period
     */
    public function test_subscription_with_trial()
    {
        $user = $this->authenticateUser();

        $product = Product::factory()->subscription()->create();
        $productPrice = ProductPrice::factory()->withTrial(14)->monthly()->create([
            'product_id' => $product->id,
        ]);

        $response = $this->postJson('/api/payments/subscribe', [
            'product_price_id' => $productPrice->id,
            'gateway' => 'stripe',
        ]);

        $this->assertApiResponse($response, 200);

        // Simulate webhook activation with trial
        $subscription = Subscription::where('user_id', $user->id)->first();
        $subscription->update([
            'status' => 'trialing',
            'trial_ends_at' => now()->addDays(14),
        ]);

        // Verify trial status
        $detailsResponse = $this->getJson("/api/payments/subscriptions/{$subscription->id}");
        $subscriptionData = $detailsResponse->json('subscription');

        $this->assertEquals('trialing', $subscriptionData['status']);
        $this->assertNotNull($subscriptionData['trial_ends_at']);
    }
}
