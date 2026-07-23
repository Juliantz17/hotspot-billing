<?php

namespace App\Console\Commands;

use App\Services\RouterProvisioningService;
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
            $routerProvisioning = app(RouterProvisioningService::class);

            foreach ($expiredSessions as $session) {
                $routerProvisioning->removeMacAccess($session->mac_address, true);

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
