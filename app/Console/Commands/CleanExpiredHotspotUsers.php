<?php

namespace App\Console\Commands;

use App\Services\MikrotikService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

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
            $routerClient = MikrotikService::getClient();

            foreach ($expiredSessions as $session) {
                // Find the actual user account on Mikrotik
                $users = $routerClient->query([
                    '/ip/hotspot/user/print',
                    '?mac-address='.$session->mac_address,
                ])->read();

                if (! empty($users)) {
                    // Delete the user account from Mikrotik
                    $routerClient->query([
                        '/ip/hotspot/user/remove',
                        '=.id='.$users[0]['.id'],
                    ])->read();
                }

                // Remove IP-binding so they get redirected to portal again
                $bindings = $routerClient->query([
                    '/ip/hotspot/ip-binding/print',
                    '?mac-address='.$session->mac_address,
                ])->read();

                if (! empty($bindings)) {
                    $routerClient->query([
                        '/ip/hotspot/ip-binding/remove',
                        '=.id='.$bindings[0]['.id'],
                    ])->read();
                }

                // Remove Simple Queue
                $queues = $routerClient->query([
                    '/queue/simple/print',
                    '?name=RateLimit_'.$session->mac_address,
                ])->read();

                if (! empty($queues)) {
                    $routerClient->query([
                        '/queue/simple/remove',
                        '=.id='.$queues[0]['.id'],
                    ])->read();
                }

                // Also kick them out if they are currently logged in
                $active = $routerClient->query([
                    '/ip/hotspot/active/print',
                    '?user='.$session->mac_address,
                ])->read();

                if (! empty($active)) {
                    $routerClient->query([
                        '/ip/hotspot/active/remove',
                        '=.id='.$active[0]['.id'],
                    ])->read();
                }

                Log::info("Session Limit Exceeded. Device MAC [{$session->mac_address}] fully removed from router.");

                // To prevent SQL Enum errors (status doesn't allow 'EXPIRED'), we just clear the expires_at timestamp to mark it as processed, keeping status=SUCCESS for earnings!
                DB::table('hotspot_transactions')
                    ->where('id', $session->id)
                    ->update(['expires_at' => null, 'updated_at' => now()]);
            }

        } catch (\Exception $e) {
            Log::error('Expired Scheduler Runtime Interface Fault: '.$e->getMessage());
        }

        return Command::SUCCESS;
    }
}
