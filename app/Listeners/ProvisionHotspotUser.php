<?php

namespace App\Listeners;

use App\Events\WifiPaymentSuccess;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use RouterOS\Config;
use RouterOS\Client as RouterClient;

class ProvisionHotspotUser implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     *
     * @var array
     */
    public $backoff = [10, 30, 60];

    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     *
     * @param  \App\Events\WifiPaymentSuccess  $event
     * @return void
     */
    public function handle(WifiPaymentSuccess $event)
    {
        $session = $event->transaction;

        try {
            $config = (new Config())
                ->set('host', env('MIKROTIK_HOST'))
                ->set('user', env('MIKROTIK_USER'))
                ->set('pass', env('MIKROTIK_PASS'))
                ->set('port', 8728);
            // Resolve from the Laravel container so we can mock it in tests
            $routerClient = app(RouterClient::class, ['config' => $config]);

            // Create a hotspot user on MikroTik with an uptime limit
            $duration = $session->duration_minutes . 'm';

            $routerClient->query([
                '/ip/hotspot/user/add',
                '=name=' . $session->mac_address,
                '=password=' . $session->mac_address,
                '=mac-address=' . $session->mac_address,
                '=limit-uptime=' . $duration,
                '=comment=Selcom Txn ' . $session->transaction_id
            ])->read();

            Log::info("Provisioned user on Mikrotik for Txn: {$session->transaction_id}, MAC: {$session->mac_address}");

        } catch (\Exception $e) {
            Log::error("Failed to provision user on Mikrotik: " . $e->getMessage());
            
            // Re-throw to trigger the queue retry
            throw $e;
        }
    }

    /**
     * Handle a job failure.
     *
     * @param  \App\Events\WifiPaymentSuccess  $event
     * @param  \Throwable  $exception
     * @return void
     */
    public function failed(WifiPaymentSuccess $event, \Throwable $exception)
    {
        Log::critical("CRITICAL: Failed to provision user on Mikrotik after all retries. Txn: {$event->transaction->transaction_id}", [
            'exception' => $exception->getMessage()
        ]);
        
        // You could dispatch a Slack alert or email here
    }
}
