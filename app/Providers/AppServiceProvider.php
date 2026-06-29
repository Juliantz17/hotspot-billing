<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Event;
use Bryceandy\Selcom\Events\CheckoutWebhookReceived;
use App\Listeners\ProcessSelcomPayment;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Tells Laravel to watch for Selcom payment updates and fire your MikroTik link
        Event::listen(
            CheckoutWebhookReceived::class,
            ProcessSelcomPayment::class
        );
    }
}