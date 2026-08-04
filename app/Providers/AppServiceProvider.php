<?php

namespace App\Providers;

use App\Events\WifiPaymentSuccess;
use App\Listeners\ProvisionHotspotUser;
use App\Services\PaymentGatewayManager;
use App\Services\Payments\AzamPayGateway;
use App\Services\Payments\SelcomGateway;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(PaymentGatewayManager::class, function ($app) {
            $manager = new PaymentGatewayManager;
            $manager->register('selcom', $app->make(SelcomGateway::class));
            $manager->register('azampay', $app->make(AzamPayGateway::class));
            return $manager;
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Keep this clean and empty.
        // Our native webhook code lives directly in the HotspotController now!
        Event::listen(WifiPaymentSuccess::class, ProvisionHotspotUser::class);
    }
}
