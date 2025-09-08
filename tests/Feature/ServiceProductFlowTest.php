<?php

namespace Tests\Feature;

use App\Models\Fulfillment;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\Transaction;
use App\Models\User;

class ServiceProductFlowTest extends BaseApiTest
{
    /**
     * Test complete service product purchase flow: register → order → payment → service activation
     */
    public function test_complete_service_product_purchase_flow()
    {
        // Step 1: Create a service product
        $product = Product::factory()->service()->create([
            'title' => 'Website Design Consultation',
            'type' => 'service',
            'meta' => [
                'duration' => '2 hours',
                'delivery_method' => 'video_call',
                'expertise' => ['web_design', 'UX/UI', 'branding'],
                'availability' => 'weekdays_9_to_5',
            ],
        ]);

        $productPrice = ProductPrice::factory()->oneTime()->create([
            'product_id' => $product->id,
            'title' => 'Standard Consultation',
            'amount' => 299.99,
            'billing_period' => 'once',
        ]);

        // Step 2: User registration
        $userData = [
            'name' => 'Business Owner',
            'email' => 'business@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ];

        $registrationResponse = $this->postJson('/api/register', $userData);
        $this->assertApiResponse($registrationResponse, 201);

        $user = User::where('email', $userData['email'])->first();
        $token = $registrationResponse->json('access_token');
        $headers = ['Authorization' => 'Bearer ' . $token];

        // Step 3: Create checkout session
        $checkoutData = [
            'product_price_id' => $productPrice->id,
            'gateway' => 'stripe',
        ];

        $checkoutResponse = $this->postJson('/api/payments/checkout', $checkoutData, $headers);
        $this->assertApiResponse($checkoutResponse, 200, [
            'success',
            'url',
        ]);

        $order = Order::where('user_id', $user->id)->first();
        $this->assertNotNull($order);

        // Step 4: Simulate successful payment
        $order->update(['status' => 'completed']);
        
        Transaction::create([
            'user_id' => $user->id,
            'order_id' => $order->id,
            'type' => 'charge',
            'status' => 'completed',
            'gateway' => 'stripe',
            'amount' => $order->total,
            'currency' => $order->currency,
            'gateway_transaction_id' => 'pi_service_12345',
            'meta' => [
                'service_product' => true,
                'consultation_type' => 'design',
                'duration_hours' => 2,
                'scheduling_required' => true,
            ],
        ]);

        // Step 5: Process service fulfillment
        $fulfillmentService = app(\App\Services\Fulfillment\FulfillmentService::class);
        $fulfillmentResult = $fulfillmentService->processPurchaseFulfillment($order);
        $this->assertTrue($fulfillmentResult);

        // Verify service fulfillment was created
        $fulfillment = Fulfillment::where('order_id', $order->id)->first();
        $this->assertNotNull($fulfillment);
        $this->assertEquals('completed', $fulfillment->status);
        $this->assertEquals('service', $fulfillment->type);
        $this->assertNotNull($fulfillment->fulfilled_at);

        $meta = $fulfillment->meta;
        $this->assertArrayHasKey('service_code', $meta);
        $this->assertArrayHasKey('instructions', $meta);
        $this->assertArrayHasKey('valid_until', $meta);

        // Step 6: Get order details with fulfillment info
        $orderDetailsResponse = $this->getJson("/api/payments/orders/{$order->id}", $headers);
        $this->assertApiResponse($orderDetailsResponse, 200);

        $orderData = $orderDetailsResponse->json('order');
        $fulfillmentData = $orderData['fulfillments'][0];
        
        $this->assertEquals('completed', $fulfillmentData['status']);
        $this->assertEquals('service', $fulfillmentData['type']);
        $this->assertNotNull($fulfillmentData['fulfilled_at']);
        
        // Verify service activation data
        $this->assertArrayHasKey('service_code', $fulfillmentData['meta']);
        $this->assertArrayHasKey('instructions', $fulfillmentData['meta']);
        $this->assertStringContainsString('support@example.com', $fulfillmentData['meta']['instructions']);
    }

    /**
     * Test recurring service (consulting retainer)
     */
    public function test_recurring_service_consultation()
    {
        $user = $this->authenticateUser();
        
        $product = Product::factory()->service()->create([
            'title' => 'Monthly Business Consulting',
            'meta' => [
                'type' => 'recurring_consultation',
                'hours_per_month' => 10,
                'meeting_frequency' => 'weekly',
            ],
        ]);
        
        $productPrice = ProductPrice::factory()->monthly()->create([
            'product_id' => $product->id,
            'amount' => 1500.00,
            'billing_period' => 'monthly',
        ]);

        // Create subscription for recurring service
        $subscriptionResponse = $this->postJson('/api/payments/subscribe', [
            'product_price_id' => $productPrice->id,
            'gateway' => 'stripe',
        ]);
        
        $this->assertApiResponse($subscriptionResponse, 200);
        
        // Verify subscription created for service
        $this->assertDatabaseHasSubscription([
            'user_id' => $user->id,
            'product_price_id' => $productPrice->id,
        ]);
    }

    /**
     * Test appointment-based service
     */
    public function test_appointment_based_service()
    {
        $user = $this->authenticateUser();
        
        $product = Product::factory()->service()->create([
            'title' => 'Legal Consultation Session',
            'meta' => [
                'type' => 'appointment',
                'duration_minutes' => 60,
                'location' => 'office_or_video',
                'preparation_required' => true,
            ],
        ]);
        
        $productPrice = ProductPrice::factory()->oneTime()->create([
            'product_id' => $product->id,
            'amount' => 450.00,
        ]);

        $order = Order::factory()->completed()->create([
            'user_id' => $user->id,
            'product_price_id' => $productPrice->id,
        ]);

        // Process appointment service fulfillment
        $fulfillmentService = app(\App\Services\Fulfillment\FulfillmentService::class);
        $result = $fulfillmentService->processPurchaseFulfillment($order);
        $this->assertTrue($result);

        $fulfillment = Fulfillment::where('order_id', $order->id)->first();
        $meta = $fulfillment->meta;
        
        $this->assertEquals('service', $fulfillment->type);
        $this->assertArrayHasKey('service_code', $meta);
        $this->assertArrayHasKey('valid_until', $meta);
        
        // Should include scheduling instructions
        $this->assertStringContainsString('schedule', strtolower($meta['instructions']));
    }

    /**
     * Test training/educational service
     */
    public function test_training_service_fulfillment()
    {
        $user = $this->authenticateUser();
        
        $product = Product::factory()->service()->create([
            'title' => 'Advanced Laravel Workshop',
            'meta' => [
                'type' => 'training',
                'format' => 'workshop',
                'duration_days' => 3,
                'max_participants' => 20,
                'materials_included' => true,
            ],
        ]);
        
        $productPrice = ProductPrice::factory()->oneTime()->create([
            'product_id' => $product->id,
            'amount' => 899.99,
        ]);

        $order = Order::factory()->completed()->create([
            'user_id' => $user->id,
            'product_price_id' => $productPrice->id,
        ]);

        $fulfillmentService = app(\App\Services\Fulfillment\FulfillmentService::class);
        $fulfillmentService->processPurchaseFulfillment($order);

        $fulfillment = Fulfillment::where('order_id', $order->id)->first();
        $this->assertEquals('service', $fulfillment->type);
        $this->assertEquals('completed', $fulfillment->status);

        // Training services might have additional meta
        $meta = $fulfillment->meta;
        $this->assertArrayHasKey('service_code', $meta);
        $this->assertArrayHasKey('instructions', $meta);
    }

    /**
     * Test service with prerequisites/requirements
     */
    public function test_service_with_prerequisites()
    {
        $user = $this->authenticateUser();
        
        $product = Product::factory()->service()->create([
            'title' => 'Advanced Technical Audit',
            'meta' => [
                'prerequisites' => [
                    'Basic system access',
                    'Documentation ready',
                    'Stakeholder availability'
                ],
                'preparation_time' => '3-5 business days',
                'deliverables' => ['Audit report', 'Action plan', 'Follow-up call'],
            ],
        ]);
        
        $productPrice = ProductPrice::factory()->oneTime()->create([
            'product_id' => $product->id,
            'amount' => 2500.00,
        ]);

        $order = Order::factory()->completed()->create([
            'user_id' => $user->id,
            'product_price_id' => $productPrice->id,
        ]);

        $fulfillmentService = app(\App\Services\Fulfillment\FulfillmentService::class);
        $fulfillmentService->processPurchaseFulfillment($order);

        $fulfillment = Fulfillment::where('order_id', $order->id)->first();
        
        // High-value services should be fulfilled but might require manual steps
        $this->assertEquals('completed', $fulfillment->status);
        $this->assertArrayHasKey('service_code', $fulfillment->meta);
    }

    /**
     * Test service expiration and validity
     */
    public function test_service_expiration_tracking()
    {
        $user = $this->authenticateUser();
        
        $product = Product::factory()->service()->create([
            'title' => 'Marketing Strategy Session',
            'meta' => [
                'validity_period' => '90 days',
                'rescheduling_allowed' => true,
                'refund_policy' => '48_hours_notice',
            ],
        ]);
        
        $productPrice = ProductPrice::factory()->oneTime()->create([
            'product_id' => $product->id,
            'amount' => 750.00,
        ]);

        // Create fulfillment with specific validity
        $order = Order::factory()->completed()->create([
            'user_id' => $user->id,
            'product_price_id' => $productPrice->id,
        ]);

        $fulfillment = Fulfillment::create([
            'order_id' => $order->id,
            'status' => 'completed',
            'type' => 'service',
            'meta' => [
                'service_code' => 'MARKETING-' . strtoupper(substr(md5(uniqid()), 0, 8)),
                'instructions' => 'Contact our team to schedule your session.',
                'valid_until' => now()->addDays(90)->toDateTimeString(),
                'rescheduling_allowed' => true,
            ],
            'fulfilled_at' => now(),
        ]);

        // Get order details
        $response = $this->getJson("/api/payments/orders/{$order->id}");
        $fulfillmentData = $response->json('order.fulfillments.0');
        
        $this->assertNotNull($fulfillmentData['meta']['valid_until']);
        $this->assertTrue($fulfillmentData['meta']['rescheduling_allowed']);
    }

    /**
     * Test service team assignment
     */
    public function test_service_team_assignment()
    {
        $user = $this->authenticateUser();
        
        $product = Product::factory()->service()->create([
            'title' => 'Dedicated Development Team',
            'meta' => [
                'team_size' => 5,
                'roles' => ['Project Manager', 'Frontend Dev', 'Backend Dev', 'Designer', 'QA'],
                'commitment_type' => 'dedicated',
            ],
        ]);
        
        $productPrice = ProductPrice::factory()->monthly()->create([
            'product_id' => $product->id,
            'amount' => 15000.00,
            'billing_period' => 'monthly',
        ]);

        // This would typically be a subscription service
        $subscriptionResponse = $this->postJson('/api/payments/subscribe', [
            'product_price_id' => $productPrice->id,
            'gateway' => 'stripe',
        ]);
        
        $this->assertApiResponse($subscriptionResponse, 200);
        
        // For team services, fulfillment might happen after subscription confirmation
        $this->assertDatabaseHasSubscription([
            'user_id' => $user->id,
            'product_price_id' => $productPrice->id,
        ]);
    }

    /**
     * Test service cancellation and refund policies
     */
    public function test_service_cancellation_handling()
    {
        $user = $this->authenticateUser();
        
        $product = Product::factory()->service()->create([
            'title' => 'Design Workshop',
            'meta' => [
                'cancellation_policy' => '24_hours_notice',
                'refund_eligible' => true,
            ],
        ]);
        
        $productPrice = ProductPrice::factory()->oneTime()->create([
            'product_id' => $product->id,
            'amount' => 399.99,
        ]);

        $order = Order::factory()->completed()->create([
            'user_id' => $user->id,
            'product_price_id' => $productPrice->id,
            'created_at' => now()->subHours(2), // Purchased 2 hours ago
        ]);

        $fulfillmentService = app(\App\Services\Fulfillment\FulfillmentService::class);
        $fulfillmentService->processPurchaseFulfillment($order);

        // Service should be fulfilled but with clear cancellation terms
        $fulfillment = Fulfillment::where('order_id', $order->id)->first();
        $this->assertEquals('completed', $fulfillment->status);
        
        $response = $this->getJson("/api/payments/orders/{$order->id}");
        $orderData = $response->json('order');
        
        // Order should be accessible and show service details
        $this->assertEquals('completed', $orderData['status']);
        $this->assertNotEmpty($orderData['fulfillments']);
    }

    /**
     * Test multi-session service package
     */
    public function test_multi_session_service_package()
    {
        $user = $this->authenticateUser();
        
        $product = Product::factory()->service()->create([
            'title' => 'Coaching Package (5 Sessions)',
            'meta' => [
                'session_count' => 5,
                'session_duration' => 60,
                'package_validity' => '6 months',
                'booking_flexibility' => 'reschedule_allowed',
            ],
        ]);
        
        $productPrice = ProductPrice::factory()->oneTime()->create([
            'product_id' => $product->id,
            'amount' => 1250.00, // $250 per session in package
        ]);

        $order = Order::factory()->completed()->create([
            'user_id' => $user->id,
            'product_price_id' => $productPrice->id,
        ]);

        $fulfillmentService = app(\App\Services\Fulfillment\FulfillmentService::class);
        $fulfillmentService->processPurchaseFulfillment($order);

        $fulfillment = Fulfillment::where('order_id', $order->id)->first();
        $meta = $fulfillment->meta;
        
        $this->assertEquals('service', $fulfillment->type);
        $this->assertArrayHasKey('service_code', $meta);
        
        // Package services might have extended validity
        $this->assertArrayHasKey('valid_until', $meta);
    }
}