<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Laravel\Sanctum\Sanctum;

abstract class BaseApiTest extends BaseTestCase
{
    use RefreshDatabase;

    protected function refreshDatabase()
    {
        // Explicitly run all migrations
        $this->artisan('migrate:fresh', ['--force' => true]);
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Configure SQLite for testing (prevent VACUUM issues)
        if (config('database.default') === 'sqlite') {
            \DB::unprepared('PRAGMA auto_vacuum = NONE');
            \DB::unprepared('PRAGMA journal_mode = MEMORY');
        }

        // Mock payment gateways to avoid real API calls
        $this->mockPaymentGateways();
    }

    protected function authenticateUser($user = null): User
    {
        $user = $user ?: User::factory()->create();
        Sanctum::actingAs($user);

        return $user;
    }

    protected function createGuestUser(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->safeEmail(),
            'phone' => fake()->phoneNumber(),
        ];
    }

    protected function mockPaymentGateways(): void
    {
        // Mock Stripe
        $this->mock(\App\Services\Payment\StripeGateway::class, function ($mock) {
            $mock->shouldReceive('createCheckoutSession')
                ->andReturn([
                    'success' => true,
                    'id' => 'cs_test_'.fake()->regexify('[A-Za-z0-9]{50}'),
                    'url' => 'https://checkout.stripe.com/pay/test_session',
                ]);

            $mock->shouldReceive('createSubscription')
                ->andReturn([
                    'success' => true,
                    'id' => 'sub_'.fake()->regexify('[A-Za-z0-9]{24}'),
                    'status' => 'active',
                    'client_secret' => 'pi_'.fake()->regexify('[A-Za-z0-9]{24}').'_secret_test',
                ]);

            $mock->shouldReceive('cancelSubscription')
                ->andReturn(['success' => true]);

            $mock->shouldReceive('resumeSubscription')
                ->andReturn(['success' => true]);

            $mock->shouldReceive('changeSubscriptionPlan')
                ->andReturn([
                    'success' => true,
                    'subscription' => (object) ['id' => 'sub_test_123'],
                ]);

            $mock->shouldReceive('createCustomerPortalSession')
                ->andReturn([
                    'success' => true,
                    'url' => 'https://billing.stripe.com/session/test_session',
                    'session_id' => 'bps_test_session_123',
                ]);
        });

        // Mock PayPal
        $this->mock(\App\Services\Payment\PayPalGateway::class, function ($mock) {
            $mock->shouldReceive('createCheckoutSession')
                ->andReturn([
                    'success' => true,
                    'id' => fake()->regexify('[A-Z0-9]{17}'),
                    'url' => 'https://www.paypal.com/checkoutnow?token=test_token',
                ]);

            $mock->shouldReceive('createSubscription')
                ->andReturn([
                    'success' => true,
                    'id' => 'I-'.fake()->regexify('[A-Z0-9]{13}'),
                    'status' => 'ACTIVE',
                ]);

            $mock->shouldReceive('cancelSubscription')
                ->andReturn(['success' => true]);

            $mock->shouldReceive('resumeSubscription')
                ->andReturn(['success' => true]);

            $mock->shouldReceive('changeSubscriptionPlan')
                ->andReturn(['success' => true]);

            $mock->shouldReceive('createCustomerPortalSession')
                ->andReturn([
                    'success' => true,
                    'url' => 'https://www.sandbox.paypal.com/myaccount/autopay/',
                    'message' => 'PayPal payments are managed through your PayPal account',
                ]);
        });

        // Don't mock the PaymentService - let it run but provide test-friendly gateway responses
        // The gateways above are mocked to avoid external API calls
    }

    protected function assertApiResponse($response, int $expectedStatus = 200, array $expectedStructure = []): void
    {
        $response->assertStatus($expectedStatus);

        if (! empty($expectedStructure)) {
            $response->assertJsonStructure($expectedStructure);
        }

        if ($expectedStatus >= 400) {
            // Check for either 'error' (single error) or 'errors' (validation errors) format
            // 401 responses may not have JSON body, so only check for JSON errors if response has content
            $json = $response->json();
            if ($json && $expectedStatus !== 401) {
                $this->assertTrue(
                    isset($json['error']) || isset($json['errors']),
                    'Error response should contain either "error" or "errors" key'
                );
            }
        }
    }

    protected function assertDatabaseHasOrder(array $attributes): void
    {
        $this->assertDatabaseHas('orders', $attributes);
    }

    protected function assertDatabaseHasSubscription(array $attributes): void
    {
        $this->assertDatabaseHas('subscriptions', $attributes);
    }

    protected function assertDatabaseHasTransaction(array $attributes): void
    {
        $this->assertDatabaseHas('transactions', $attributes);
    }

    protected function assertDatabaseHasFulfillment(array $attributes): void
    {
        $this->assertDatabaseHas('fulfillments', $attributes);
    }
}
