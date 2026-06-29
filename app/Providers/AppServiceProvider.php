<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Event;
use App\Events\WifiPaymentSuccess;
use App\Listeners\ProvisionHotspotUser;

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
        // Keep this clean and empty. 
        // Our native webhook code lives directly in the HotspotController now!
        Event::listen(WifiPaymentSuccess::class, ProvisionHotspotUser::class);
    }
}