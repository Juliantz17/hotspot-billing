<?php

namespace App\Services;

use RouterOS\Config;
use RouterOS\Client as RouterClient;
use Illuminate\Support\Facades\Log;

class MikrotikService
{
    /**
     * Get a connected RouterOS Client with retry logic to handle 
     * "already authorizing" concurrency issues on Mikrotik.
     * 
     * @return \RouterOS\Client
     * @throws \Exception
     */
    public static function getClient(): RouterClient
    {
        $config = (new Config())
            ->set('host', config('services.mikrotik.host'))
            ->set('user', config('services.mikrotik.user'))
            ->set('pass', config('services.mikrotik.pass'))
            ->set('port', 8728);

        $maxRetries = 5;
        $retryDelay = 500000; // 500ms

        for ($i = 0; $i < $maxRetries; $i++) {
            try {
                // If resolving via container is preferred (like in ProvisionHotspotUser):
                if (app()->bound(RouterClient::class)) {
                    return app(RouterClient::class, ['config' => $config]);
                }
                
                return new RouterClient($config);
            } catch (\Exception $e) {
                if ($i === $maxRetries - 1) {
                    Log::error("Mikrotik connection failed after {$maxRetries} retries: " . $e->getMessage());
                    throw $e;
                }
                // Log and wait before retrying
                Log::warning("Mikrotik connection retry " . ($i + 1) . " due to: " . $e->getMessage());
                usleep($retryDelay);
            }
        }
        
        throw new \Exception("Failed to connect to Mikrotik");
    }
}
