<?php

namespace Tests\Feature;

use App\Models\Fulfillment;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\Transaction;
use App\Models\User;

class PhysicalProductFlowTest extends BaseApiTest
{
    /**
     * Test complete physical product purchase flow: register → order → payment → fulfillment → shipping
     */
    public function test_complete_physical_product_purchase_flow()
    {
        // Step 1: Create a physical product
        $product = Product::factory()->physical()->create([
            'title' => 'Wireless Headphones',
            'type' => 'physical',
            'meta' => [
                'weight' => 0.5,
                'dimensions' => '20x15x8 cm',
                'color' => 'Black',
            ],
        ]);

        $productPrice = ProductPrice::factory()->oneTime()->create([
            'product_id' => $product->id,
            'title' => 'Standard Price',
            'amount' => 99.99,
            'billing_period' => 'once',
        ]);

        // Step 2: User registration
        $userData = [
            'name' => 'Jane Smith',
            'email' => 'jane.smith@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ];

        $registrationResponse = $this->postJson('/api/register', $userData);
        $this->assertApiResponse($registrationResponse, 201);

        $user = User::where('email', $userData['email'])->first();
        $token = $registrationResponse->json('access_token');
        $headers = ['Authorization' => 'Bearer ' . $token];

        // Step 3: Browse products
        $productsResponse = $this->getJson('/api/payments/products', $headers);
        $this->assertApiResponse($productsResponse, 200);

        // Step 4: Get specific product details
        $productResponse = $this->getJson("/api/payments/products/{$product->id}", $headers);
        $this->assertApiResponse($productResponse, 200, [
            'product' => ['id', 'title', 'type', 'active_prices']
        ]);

        // Step 5: Create checkout session
        $checkoutData = [
            'product_price_id' => $productPrice->id,
            'gateway' => 'stripe',
        ];

        $checkoutResponse = $this->postJson('/api/payments/checkout', $checkoutData, $headers);
        $this->assertApiResponse($checkoutResponse, 200, [
            'success',
            'url',
        ]);

        // Verify order was created
        $this->assertDatabaseHasOrder([
            'user_id' => $user->id,
            'product_price_id' => $productPrice->id,
            'status' => 'pending',
            'total' => 99.99,
            'gateway' => 'stripe',
        ]);

        $order = Order::where('user_id', $user->id)->first();

        // Step 6: Simulate successful payment webhook
        $order->update(['status' => 'completed']);
        
        // Create transaction record
        Transaction::create([
            'user_id' => $user->id,
            'order_id' => $order->id,
            'type' => 'charge',
            'status' => 'completed',
            'gateway' => 'stripe',
            'amount' => $order->total,
            'currency' => $order->currency,
            'gateway_transaction_id' => 'pi_test_12345',
            'gateway_data' => [
                'payment_method' => 'card_visa',
                'last4' => '4242',
            ],
            'meta' => [
                'physical_product' => true,
                'shipping_required' => true,
                'processing_time' => '1-2 business days',
            ],
        ]);

        // Step 7: Process fulfillment
        $fulfillmentService = app(\App\Services\Fulfillment\FulfillmentService::class);
        $fulfillmentResult = $fulfillmentService->processPurchaseFulfillment($order);
        $this->assertTrue($fulfillmentResult);

        // Verify fulfillment was created
        $this->assertDatabaseHasFulfillment([
            'order_id' => $order->id,
            'status' => 'completed',
            'type' => 'physical',
        ]);

        $fulfillment = Fulfillment::where('order_id', $order->id)->first();
        $this->assertNotNull($fulfillment->tracking_number);
        $this->assertNotNull($fulfillment->carrier);

        // Step 8: Get user's orders
        $ordersResponse = $this->getJson('/api/payments/orders', $headers);
        $this->assertApiResponse($ordersResponse, 200, [
            'orders' => [
                'data' => [
                    '*' => ['id', 'order_number', 'status', 'total', 'product_price']
                ]
            ]
        ]);

        // Step 9: Get specific order details
        $orderDetailsResponse = $this->getJson("/api/payments/orders/{$order->id}", $headers);
        $this->assertApiResponse($orderDetailsResponse, 200, [
            'order' => [
                'id', 'order_number', 'status', 'total', 'product_price', 
                'transactions', 'fulfillments'
            ]
        ]);

        $orderData = $orderDetailsResponse->json('order');
        $this->assertEquals('completed', $orderData['status']);
        $this->assertCount(1, $orderData['transactions']);
        $this->assertCount(1, $orderData['fulfillments']);

        // Verify fulfillment details
        $fulfillmentData = $orderData['fulfillments'][0];
        $this->assertEquals('completed', $fulfillmentData['status']);
        $this->assertEquals('physical', $fulfillmentData['type']);
        $this->assertNotNull($fulfillmentData['tracking_number']);
        $this->assertNotNull($fulfillmentData['carrier']);
    }

    /**
     * Test physical product purchase with different payment gateways
     */
    public function test_physical_product_with_paypal()
    {
        $user = $this->authenticateUser();
        
        $product = Product::factory()->physical()->create();
        $productPrice = ProductPrice::factory()->oneTime()->create([
            'product_id' => $product->id,
            'amount' => 149.99,
        ]);

        $checkoutData = [
            'product_price_id' => $productPrice->id,
            'gateway' => 'paypal',
        ];

        $response = $this->postJson('/api/payments/checkout', $checkoutData);
        $this->assertApiResponse($response, 200);
        
        // Verify PayPal order created
        $this->assertDatabaseHasOrder([
            'user_id' => $user->id,
            'gateway' => 'paypal',
            'total' => 149.99,
        ]);
    }

    /**
     * Test order fulfillment tracking
     */
    public function test_order_fulfillment_tracking()
    {
        $user = $this->authenticateUser();
        
        $product = Product::factory()->physical()->create();
        $productPrice = ProductPrice::factory()->oneTime()->create(['product_id' => $product->id]);
        
        // Create completed order
        $order = Order::factory()->completed()->create([
            'user_id' => $user->id,
            'product_price_id' => $productPrice->id,
        ]);

        // Create fulfillment with tracking
        $fulfillment = Fulfillment::create([
            'order_id' => $order->id,
            'status' => 'processing',
            'type' => 'physical',
            'tracking_number' => 'TRACK123456789',
            'carrier' => 'FedEx',
            'meta' => [
                'shipping_address' => [
                    'title' => $order->billing_name,
                    'address' => $order->billing_address,
                    'city' => $order->billing_city,
                    'state' => $order->billing_state,
                    'zipcode' => $order->billing_zipcode,
                    'country' => $order->billing_country,
                ],
                'estimated_delivery' => now()->addDays(3)->toDateString(),
            ],
        ]);

        // Get order details with fulfillment info
        $response = $this->getJson("/api/payments/orders/{$order->id}");
        $this->assertApiResponse($response, 200);
        
        $orderData = $response->json('order');
        $fulfillmentData = $orderData['fulfillments'][0];
        
        $this->assertEquals('TRACK123456789', $fulfillmentData['tracking_number']);
        $this->assertEquals('FedEx', $fulfillmentData['carrier']);
        $this->assertEquals('processing', $fulfillmentData['status']);
    }

    /**
     * Test bulk order scenarios
     */
    public function test_multiple_item_order()
    {
        $user = $this->authenticateUser();
        
        $product = Product::factory()->physical()->create();
        $productPrice = ProductPrice::factory()->oneTime()->create([
            'product_id' => $product->id,
            'amount' => 25.99,
        ]);

        // Simulate ordering multiple quantities (this would require frontend implementation)
        $order = Order::factory()->completed()->create([
            'user_id' => $user->id,
            'product_price_id' => $productPrice->id,
            'total' => 25.99 * 3, // 3 items
            'customer_note' => 'Quantity: 3',
        ]);

        // Process fulfillment
        $fulfillmentService = app(\App\Services\Fulfillment\FulfillmentService::class);
        $result = $fulfillmentService->processPurchaseFulfillment($order);
        $this->assertTrue($result);

        // Verify fulfillment handles bulk order
        $fulfillment = Fulfillment::where('order_id', $order->id)->first();
        $this->assertEquals('completed', $fulfillment->status);
        $this->assertNotNull($fulfillment->tracking_number);
    }

    /**
     * Test shipping address validation
     */
    public function test_shipping_address_requirements()
    {
        $user = $this->authenticateUser();
        
        $product = Product::factory()->physical()->create();
        $productPrice = ProductPrice::factory()->oneTime()->create(['product_id' => $product->id]);
        
        // Create order with incomplete shipping info
        $order = Order::factory()->create([
            'user_id' => $user->id,
            'product_price_id' => $productPrice->id,
            'billing_address' => null, // Missing address
            'billing_city' => null,
            'billing_state' => null,
        ]);

        // Get order details - should still work but highlight missing info
        $response = $this->getJson("/api/payments/orders/{$order->id}");
        $this->assertApiResponse($response, 200);
        
        $orderData = $response->json('order');
        $this->assertNull($orderData['billing_address']);
    }

    /**
     * Test failed payment scenarios
     */
    public function test_failed_payment_handling()
    {
        $user = $this->authenticateUser();
        
        $product = Product::factory()->physical()->create();
        $productPrice = ProductPrice::factory()->oneTime()->create(['product_id' => $product->id]);

        // Create order
        $checkoutResponse = $this->postJson('/api/payments/checkout', [
            'product_price_id' => $productPrice->id,
            'gateway' => 'stripe',
        ]);
        
        $order = Order::where('user_id', $user->id)->first();
        
        // Simulate payment failure
        $order->update(['status' => 'failed']);
        
        Transaction::create([
            'user_id' => $user->id,
            'order_id' => $order->id,
            'type' => 'charge',
            'status' => 'failed',
            'gateway' => 'stripe',
            'amount' => $order->total,
            'currency' => $order->currency,
            'gateway_transaction_id' => 'pi_failed_12345',
            'gateway_data' => [
                'failure_reason' => 'insufficient_funds',
            ],
            'meta' => [
                'failure_type' => 'payment_declined',
                'retry_possible' => true,
                'failure_count' => 1,
            ],
        ]);

        // Verify no fulfillment is created for failed payments
        $fulfillmentCount = Fulfillment::where('order_id', $order->id)->count();
        $this->assertEquals(0, $fulfillmentCount);

        // Get order details
        $response = $this->getJson("/api/payments/orders/{$order->id}");
        $orderData = $response->json('order');
        
        $this->assertEquals('failed', $orderData['status']);
        $this->assertEquals('failed', $orderData['transactions'][0]['status']);
        $this->assertEmpty($orderData['fulfillments']);
    }
}