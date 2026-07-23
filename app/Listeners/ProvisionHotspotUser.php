<?php

namespace App\Listeners;

use App\Events\WifiPaymentSuccess;
use App\Services\RouterProvisioningService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

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
     * @return void
     */
    public function handle(WifiPaymentSuccess $event)
    {
        $session = $event->transaction;

        try {
            app(RouterProvisioningService::class)->provisionAccess($session, 'Selcom Txn');

            Log::info("Provisioned user on Mikrotik for Txn: {$session->transaction_id}, MAC: {$session->mac_address}");

        } catch (\Exception $e) {
            Log::error('Failed to provision user on Mikrotik: '.$e->getMessage());

            // Re-throw to trigger the queue retry
            throw $e;
        }
    }

    /**
     * Handle a job failure.
     *
     * @return void
     */
    public function failed(WifiPaymentSuccess $event, \Throwable $exception)
    {
        Log::critical("CRITICAL: Failed to provision user on Mikrotik after all retries. Txn: {$event->transaction->transaction_id}", [
            'exception' => $exception->getMessage(),
        ]);

        // You could dispatch a Slack alert or email here
    }
}
