<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\OrderQueryController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\ProductQueryController;
use Illuminate\Support\Facades\Route;

// Public routes - completely stateless API
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// Token generation moved to web.php with CSRF bypass

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']);

    // Payment routes
    Route::prefix('payments')->group(function () {
        // Products and prices
        Route::get('/products', [PaymentController::class, 'getProducts']);
        Route::get('/products/{product}', [PaymentController::class, 'getProduct']);

        // Unified order creation
        Route::post('/orders', [PaymentController::class, 'createOrder']);

        // One-time payments (checkout creation is legacy - kept for reference)
        Route::post('/checkout', [PaymentController::class, 'createCheckoutSession']);
        Route::get('/orders', [PaymentController::class, 'getOrders']);
        Route::get('/orders/{order}', [PaymentController::class, 'getOrder']);

        // Subscriptions (subscription creation is legacy - kept for reference)
        Route::post('/subscribe', [PaymentController::class, 'createSubscription']);
        Route::get('/subscriptions', [PaymentController::class, 'getSubscriptions']);
        Route::get('/subscriptions/{subscription}', [PaymentController::class, 'getSubscription']);
        Route::put('/subscriptions/{subscription}/cancel', [PaymentController::class, 'cancelSubscription']);
        Route::put('/subscriptions/{subscription}/resume', [PaymentController::class, 'resumeSubscription']);
        Route::put('/subscriptions/{subscription}/change-plan', [PaymentController::class, 'changeSubscriptionPlan']);
        Route::get('/subscriptions/{subscription}/available-plans', [PaymentController::class, 'getAvailablePlans']);

        // Customer Portal for payment method management
        Route::post('/customer-portal', [PaymentController::class, 'createCustomerPortalSession']);
        Route::post('/subscriptions/{subscription}/customer-portal', [PaymentController::class, 'getSubscriptionCustomerPortal']);

        // Webhooks (no auth required)
    });

    // Query Builder API routes (Orders - Protected)
    Route::prefix('query')->group(function () {
        Route::get('/orders', [OrderQueryController::class, 'index']);
        Route::get('/orders/{order}', [OrderQueryController::class, 'show']);
    });
});

// Query Builder API routes (Products - Public with Rate Limiting)
Route::prefix('query')->middleware(['throttle:60,1'])->group(function () {
    Route::get('/products', [ProductQueryController::class, 'index']);
    Route::get('/products/{product}', [ProductQueryController::class, 'show']);
});

// Payment webhooks (no auth)
Route::post('/webhooks/stripe', [PaymentController::class, 'handleStripeWebhook']);
Route::post('/webhooks/paypal', [PaymentController::class, 'handlePayPalWebhook']);
