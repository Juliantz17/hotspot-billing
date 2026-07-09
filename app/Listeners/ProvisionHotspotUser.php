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
            // Use the MikrotikService which resolves from the container or creates a new client with retry logic
            $routerClient = \App\Services\MikrotikService::getClient();

            // Create a hotspot user on MikroTik with an uptime limit
            $duration = $session->duration_minutes . 'm';

            // Remove existing user to avoid "already exists" silent failures causing invalid password later
            try {
                $users = $routerClient->query(['/ip/hotspot/user/print', '?name=' . $session->mac_address])->read();
                foreach ($users as $u) {
                    $routerClient->query(['/ip/hotspot/user/remove', '=.id=' . $u['.id']])->read();
                }
            } catch (\Exception $e) {}

            $query = [
                '/ip/hotspot/user/add',
                '=name=' . $session->mac_address,
                '=password=' . $session->mac_address,
                '=mac-address=' . $session->mac_address,
                '=comment=Selcom Txn ' . $session->transaction_id
            ];

            if (!empty($session->speed_limit)) {
                $query[] = '=rate-limit=' . $session->speed_limit;
            }

            $routerClient->query($query)->read();

            // Force the router to instantly log them in
            if (!empty($session->ip_address)) {
                try {
                    try {
                        $activeSessions = $routerClient->query(['/ip/hotspot/active/print', '?mac-address=' . $session->mac_address])->read();
                        foreach ($activeSessions as $as) {
                            $routerClient->query(['/ip/hotspot/active/remove', '=.id=' . $as['.id']])->read();
                        }
                    } catch (\Exception $e) {}

                    $routerClient->query([
                        '/ip/hotspot/active/login',
                        '=user=' . $session->mac_address,
                        '=password=' . $session->mac_address,
                        '=ip=' . $session->ip_address,
                        '=mac-address=' . $session->mac_address
                    ])->read();
                } catch (\Exception $e) {
                    Log::warning("Could not auto-login MAC {$session->mac_address}.", ['error' => $e->getMessage()]);
                }
            }

            // ADD IP-BINDING TO ENSURE SEAMLESS RECONNECT (Bypasses Portal entirely)
            try {
                $bindings = $routerClient->query(['/ip/hotspot/ip-binding/print', '?mac-address=' . $session->mac_address])->read();
                foreach ($bindings as $b) {
                    $routerClient->query(['/ip/hotspot/ip-binding/remove', '=.id=' . $b['.id']])->read();
                }
                $routerClient->query([
                    '/ip/hotspot/ip-binding/add',
                    '=mac-address=' . $session->mac_address,
                    '=type=bypassed',
                    '=comment=Selcom Txn ' . $session->transaction_id
                ])->read();

                // Enforce speed limit for bypassed users using a Simple Queue
                if (!empty($session->speed_limit) && !empty($session->ip_address)) {
                    $queues = $routerClient->query(['/queue/simple/print', '?name=RateLimit_' . $session->mac_address])->read();
                    foreach ($queues as $q) {
                        $routerClient->query(['/queue/simple/remove', '=.id=' . $q['.id']])->read();
                    }
                    $routerClient->query([
                        '/queue/simple/add',
                        '=name=RateLimit_' . $session->mac_address,
                        '=target=' . $session->ip_address . '/32',
                        '=max-limit=' . $session->speed_limit,
                        '=comment=Selcom Txn ' . $session->transaction_id
                    ])->read();
                }

            } catch (\Exception $e) {
                Log::warning("Could not add ip-binding or queue for MAC {$session->mac_address}.", ['error' => $e->getMessage()]);
            }

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
