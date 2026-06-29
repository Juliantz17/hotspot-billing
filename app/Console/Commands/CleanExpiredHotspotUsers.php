<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RouterOS\Config;
use RouterOS\Client as RouterClient;

class CleanExpiredHotspotUsers extends Command
{
    protected $signature = 'hotspot:clean-expired';
    protected $description = 'Kicks expired paid devices out of the MikroTik network';

    public function handle()
    {
        // Gather valid local database tracking entries whose limits have passed
        $expiredSessions = DB::table('hotspot_transactions')
            ->where('status', 'SUCCESS')
            ->where('expires_at', '<=', now())
            ->get();

        if ($expiredSessions->isEmpty()) {
            return Command::SUCCESS;
        }

        try {
            $config = (new Config())
                ->set('host', env('MIKROTIK_HOST'))
                ->set('user', env('MIKROTIK_USER'))
                ->set('pass', env('MIKROTIK_PASS'))
                ->set('port', 8728);

            $routerClient = new RouterClient($config);

            foreach ($expiredSessions as $session) {
                // Find the internal system ID (.id) assigned to this MAC address dynamically
                $bindings = $routerClient->query([
                    '/ip/hotspot/ip-binding/print',
                    '?mac-address=' . $session->mac_address
                ])->read();

                if (!empty($bindings)) {
                    // Clear out the bypass rule to lock the customer device behind the captive gateway
                    $routerClient->query([
                        '/ip/hotspot/ip-binding/remove',
                        '=.id=' . $bindings[0]['.id']
                    ])->read();

                    Log::info("Session Limit Exceeded. Device MAC [{$session->mac_address}] removed from active bindings.");
                }

                // Switch state parameters locally to isolate execution tracking strings
                DB::table('hotspot_transactions')
                    ->where('id', $session->id)
                    ->update(['status' => 'FAILED', 'updated_at' => now()]);
            }

        } catch (\Exception $e) {
            Log::error("Expired Scheduler Runtime Interface Fault: " . $e->getMessage());
        }

        return Command::SUCCESS;
    }
}