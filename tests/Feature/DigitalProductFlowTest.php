<?php

namespace Tests\Feature;

use App\Models\Fulfillment;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\Transaction;
use App\Models\User;

class DigitalProductFlowTest extends BaseApiTest
{
    /**
     * Test complete digital product purchase flow: register → order → payment → instant fulfillment
     */
    public function test_complete_digital_product_purchase_flow()
    {
        // Step 1: Create a digital product
        $product = Product::factory()->digital()->create([
            'title' => 'Premium Software License',
            'type' => 'digital',
            'meta' => [
                'version' => '2.1.0',
                'platforms' => ['Windows', 'macOS', 'Linux'],
                'license_type' => 'single_user',
                'download_size' => '250MB',
            ],
        ]);

        $productPrice = ProductPrice::factory()->oneTime()->create([
            'product_id' => $product->id,
            'title' => 'Standard License',
            'amount' => 199.99,
            'billing_period' => 'once',
        ]);

        // Step 2: User registration
        $userData = [
            'name' => 'Tech Enthusiast',
            'email' => 'tech@example.com',
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
            'gateway_transaction_id' => 'pi_digital_12345',
            'meta' => [
                'digital_product' => true,
                'instant_delivery' => true,
                'license_type' => 'single_user',
            ],
        ]);

        // Step 5: Process digital fulfillment (instant)
        $fulfillmentService = app(\App\Services\Fulfillment\FulfillmentService::class);
        $fulfillmentResult = $fulfillmentService->processPurchaseFulfillment($order);
        $this->assertTrue($fulfillmentResult);

        // Verify digital fulfillment was created with license key and download link
        $fulfillment = Fulfillment::where('order_id', $order->id)->first();
        $this->assertNotNull($fulfillment);
        $this->assertEquals('completed', $fulfillment->status);
        $this->assertEquals('digital', $fulfillment->type);
        $this->assertNotNull($fulfillment->fulfilled_at);

        $meta = $fulfillment->meta;
        $this->assertArrayHasKey('license_key', $meta);
        $this->assertArrayHasKey('download_link', $meta);
        $this->assertArrayHasKey('expires_at', $meta);

        // Step 6: Get order details with fulfillment info
        $orderDetailsResponse = $this->getJson("/api/payments/orders/{$order->id}", $headers);
        $this->assertApiResponse($orderDetailsResponse, 200, [
            'order' => [
                'id', 'order_number', 'status', 'total', 'product_price', 
                'transactions', 'fulfillments'
            ]
        ]);

        $orderData = $orderDetailsResponse->json('order');
        $fulfillmentData = $orderData['fulfillments'][0];
        
        $this->assertEquals('completed', $fulfillmentData['status']);
        $this->assertEquals('digital', $fulfillmentData['type']);
        $this->assertNotNull($fulfillmentData['fulfilled_at']);
        
        // Verify digital delivery data
        $this->assertArrayHasKey('license_key', $fulfillmentData['meta']);
        $this->assertArrayHasKey('download_link', $fulfillmentData['meta']);
        $this->assertStringContainsString('/download/', $fulfillmentData['meta']['download_link']);
    }

    /**
     * Test digital product with different file types
     */
    public function test_digital_product_software_download()
    {
        $user = $this->authenticateUser();
        
        $product = Product::factory()->digital()->create([
            'title' => 'Photo Editor Pro',
            'meta' => [
                'file_type' => 'application',
                'version' => '3.2.1',
                'requirements' => 'Windows 10, 8GB RAM',
            ],
        ]);
        
        $productPrice = ProductPrice::factory()->oneTime()->create([
            'product_id' => $product->id,
            'amount' => 79.99,
        ]);

        // Purchase and fulfill
        $order = Order::factory()->completed()->create([
            'user_id' => $user->id,
            'product_price_id' => $productPrice->id,
        ]);

        $fulfillmentService = app(\App\Services\Fulfillment\FulfillmentService::class);
        $result = $fulfillmentService->processPurchaseFulfillment($order);
        $this->assertTrue($result);

        $fulfillment = Fulfillment::where('order_id', $order->id)->first();
        $this->assertEquals('digital', $fulfillment->type);
        $this->assertNotNull($fulfillment->meta['license_key']);
    }

    /**
     * Test digital product - eBook/content delivery
     */
    public function test_digital_ebook_delivery()
    {
        $user = $this->authenticateUser();
        
        $product = Product::factory()->digital()->create([
            'title' => 'Complete Laravel Guide',
            'meta' => [
                'content_type' => 'ebook',
                'format' => ['PDF', 'EPUB', 'MOBI'],
                'pages' => 450,
                'language' => 'English',
            ],
        ]);
        
        $productPrice = ProductPrice::factory()->oneTime()->create([
            'product_id' => $product->id,
            'amount' => 49.99,
        ]);

        $order = Order::factory()->completed()->create([
            'user_id' => $user->id,
            'product_price_id' => $productPrice->id,
        ]);

        // Process fulfillment
        $fulfillmentService = app(\App\Services\Fulfillment\FulfillmentService::class);
        $fulfillmentService->processPurchaseFulfillment($order);

        $fulfillment = Fulfillment::where('order_id', $order->id)->first();
        $meta = $fulfillment->meta;
        
        // For eBooks, we might not need license keys but download links
        $this->assertArrayHasKey('download_link', $meta);
        $this->assertArrayHasKey('expires_at', $meta);
        
        // Download link should be accessible
        $this->assertStringContainsString('download', $meta['download_link']);
    }

    /**
     * Test digital product access control
     */
    public function test_digital_product_access_control()
    {
        $user1 = $this->authenticateUser();
        $user2 = User::factory()->create();
        
        $product = Product::factory()->digital()->create();
        $productPrice = ProductPrice::factory()->oneTime()->create(['product_id' => $product->id]);
        
        // User 1 purchases product
        $order = Order::factory()->completed()->create([
            'user_id' => $user1->id,
            'product_price_id' => $productPrice->id,
        ]);

        $fulfillmentService = app(\App\Services\Fulfillment\FulfillmentService::class);
        $fulfillmentService->processPurchaseFulfillment($order);

        // User 1 can access their order
        $response1 = $this->getJson("/api/payments/orders/{$order->id}");
        $this->assertApiResponse($response1, 200);

        // User 2 cannot access user 1's order
        $this->actingAs($user2);
        $response2 = $this->getJson("/api/payments/orders/{$order->id}");
        $this->assertApiResponse($response2, 403);
    }

    /**
     * Test digital product license expiration
     */
    public function test_digital_license_expiration_tracking()
    {
        $user = $this->authenticateUser();
        
        $product = Product::factory()->digital()->create([
            'title' => 'Annual Software License',
            'meta' => [
                'license_duration' => '1 year',
                'renewal_required' => true,
            ],
        ]);
        
        $productPrice = ProductPrice::factory()->oneTime()->create([
            'product_id' => $product->id,
            'amount' => 299.99,
        ]);

        $order = Order::factory()->completed()->create([
            'user_id' => $user->id,
            'product_price_id' => $productPrice->id,
        ]);

        // Create fulfillment with expiration
        $fulfillment = Fulfillment::create([
            'order_id' => $order->id,
            'status' => 'completed',
            'type' => 'digital',
            'meta' => [
                'license_key' => 'LICENSE-12345-ABCDE',
                'download_link' => url('/download/software/123/token'),
                'expires_at' => now()->addYear()->toDateTimeString(),
                'license_type' => 'annual',
            ],
            'fulfilled_at' => now(),
        ]);

        // Get order details
        $response = $this->getJson("/api/payments/orders/{$order->id}");
        $fulfillmentData = $response->json('order.fulfillments.0');
        
        $this->assertNotNull($fulfillmentData['meta']['expires_at']);
        $this->assertEquals('annual', $fulfillmentData['meta']['license_type']);
    }

    /**
     * Test bulk digital product purchase
     */
    public function test_bulk_digital_license_purchase()
    {
        $user = $this->authenticateUser();
        
        $product = Product::factory()->digital()->create([
            'title' => 'Team Software License (5 users)',
            'meta' => [
                'license_count' => 5,
                'license_type' => 'multi_user',
            ],
        ]);
        
        $productPrice = ProductPrice::factory()->oneTime()->create([
            'product_id' => $product->id,
            'amount' => 999.99,
        ]);

        $order = Order::factory()->completed()->create([
            'user_id' => $user->id,
            'product_price_id' => $productPrice->id,
            'customer_note' => 'Team license for 5 users',
        ]);

        $fulfillmentService = app(\App\Services\Fulfillment\FulfillmentService::class);
        $fulfillmentService->processPurchaseFulfillment($order);

        $fulfillment = Fulfillment::where('order_id', $order->id)->first();
        $meta = $fulfillment->meta;
        
        // Team license should have multiple keys or different structure
        $this->assertArrayHasKey('license_key', $meta);
        $this->assertArrayHasKey('download_link', $meta);
    }

    /**
     * Test instant delivery notification
     */
    public function test_digital_product_instant_notification()
    {
        $user = $this->authenticateUser();
        
        $product = Product::factory()->digital()->create();
        $productPrice = ProductPrice::factory()->oneTime()->create(['product_id' => $product->id]);

        // Create and complete order
        $order = Order::factory()->completed()->create([
            'user_id' => $user->id,
            'product_price_id' => $productPrice->id,
        ]);

        // Process fulfillment - should send notification
        $fulfillmentService = app(\App\Services\Fulfillment\FulfillmentService::class);
        $result = $fulfillmentService->processPurchaseFulfillment($order);
        $this->assertTrue($result);

        // In a real scenario, we'd test that OrderFulfilledNotification was sent
        // For now, just verify fulfillment was created with proper status
        $fulfillment = Fulfillment::where('order_id', $order->id)->first();
        $this->assertEquals('completed', $fulfillment->status);
        $this->assertNotNull($fulfillment->fulfilled_at);
        
        // Digital products should be fulfilled immediately
        $this->assertTrue($fulfillment->fulfilled_at->diffInMinutes(now()) < 1);
    }

    /**
     * Test multiple digital product formats
     */
    public function test_digital_product_multiple_formats()
    {
        $user = $this->authenticateUser();
        
        $product = Product::factory()->digital()->create([
            'title' => 'Photography Course Bundle',
            'meta' => [
                'content_types' => ['videos', 'ebooks', 'presets', 'templates'],
                'total_size' => '4.2GB',
                'formats' => ['MP4', 'PDF', 'PSD', 'INDD'],
            ],
        ]);
        
        $productPrice = ProductPrice::factory()->oneTime()->create([
            'product_id' => $product->id,
            'amount' => 149.99,
        ]);

        $order = Order::factory()->completed()->create([
            'user_id' => $user->id,
            'product_price_id' => $productPrice->id,
        ]);

        $fulfillmentService = app(\App\Services\Fulfillment\FulfillmentService::class);
        $fulfillmentService->processPurchaseFulfillment($order);

        $response = $this->getJson("/api/payments/orders/{$order->id}");
        $orderData = $response->json('order');
        
        $this->assertEquals('completed', $orderData['status']);
        $this->assertNotEmpty($orderData['fulfillments']);
        
        $fulfillmentData = $orderData['fulfillments'][0];
        $this->assertEquals('digital', $fulfillmentData['type']);
        $this->assertArrayHasKey('download_link', $fulfillmentData['meta']);
    }
}