<?php

namespace App\Providers;

use App\Services\Payment\PaymentGatewayInterface;
use App\Services\Payment\PaymentService;
use App\Services\Payment\StripeGateway;
use App\Services\Payment\PayPalGateway;
use Illuminate\Support\ServiceProvider;

class PaymentServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->singleton(StripeGateway::class, function ($app) {
            return new StripeGateway();
        });

        $this->app->singleton(PayPalGateway::class, function ($app) {
            return new PayPalGateway();
        });

        $this->app->singleton(PaymentService::class, function ($app) {
            return new PaymentService();
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
