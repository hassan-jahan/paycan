<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Auth\SocialiteController;
use App\Http\Controllers\PaymentController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome');
})->name('home');

Route::get('dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::get('products', [PaymentController::class, 'products'])->name('products');

// API Token generation - bypass CSRF for stateless API
Route::post('api/auth/token', [AuthController::class, 'createToken'])
    ->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class);

// Socialite Routes
Route::get('auth/{provider}', [SocialiteController::class, 'redirect'])
    ->name('socialite.redirect');

Route::get('auth/{provider}/callback', [SocialiteController::class, 'callback'])
        ->name('socialite.callback');

// Connect social account (requires authentication)
Route::get('auth/{provider}/connect', [SocialiteController::class, 'redirect'])
    ->middleware('auth')
    ->defaults('action', 'connect')
    ->name('socialite.connect');

// Payment Routes
Route::prefix('payment')->middleware(['auth'])->group(function () {
    Route::get('checkout/{productPrice}', [PaymentController::class, 'checkout'])->name('payment.checkout');
    Route::post('checkout/{productPrice}', [PaymentController::class, 'processCheckout'])->name('payment.process');
    Route::get('success/{order}', [PaymentController::class, 'success'])->name('payment.success');
    Route::get('cancel/{order}', [PaymentController::class, 'cancel'])->name('payment.cancel');

    Route::get('subscription/{productPrice}', [PaymentController::class, 'subscriptionCheckout'])->name('subscription.checkout');
    Route::post('subscription/{productPrice}', [PaymentController::class, 'processSubscriptionCheckout'])->name('subscription.process');

    Route::get('orders', [PaymentController::class, 'orders'])->name('account.orders');
    Route::get('orders/{order}', [PaymentController::class, 'viewOrder'])->name('account.orders.view');

    Route::get('subscriptions', [PaymentController::class, 'subscriptions'])->name('account.subscriptions');
    Route::get('subscriptions/{subscription}', [PaymentController::class, 'viewSubscription'])->name('account.subscriptions.view');
    Route::post('subscriptions/{subscription}/cancel', [PaymentController::class, 'cancelSubscription'])->name('account.subscriptions.cancel');
    Route::post('subscriptions/{subscription}/resume', [PaymentController::class, 'resumeSubscription'])->name('account.subscriptions.resume');
    Route::put('subscriptions/{subscription}/change-plan', [PaymentController::class, 'changeSubscriptionPlan'])->name('account.subscriptions.change-plan');
});

// Payment Webhooks (no auth)
Route::post('webhooks/stripe', [PaymentController::class, 'handleStripeWebhook'])->name('webhooks.stripe');
Route::post('webhooks/paypal', [PaymentController::class, 'handlePayPalWebhook'])->name('webhooks.paypal');

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
