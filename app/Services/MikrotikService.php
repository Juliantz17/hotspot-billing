<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use RouterOS\Client;
use RouterOS\Client as RouterClient;
use RouterOS\Config;

class MikrotikService
{
    /**
     * Get a connected RouterOS Client with retry logic to handle
     * "already authorizing" concurrency issues on Mikrotik.
     *
     * @throws \Exception
     */
    public static function getClient(): RouterClient
    {
        if (! config('services.mikrotik.enabled', true) && ! app()->bound(RouterClient::class)) {
            throw new \RuntimeException('MikroTik integration is disabled.');
        }

        $config = (new Config)
            ->set('host', config('services.mikrotik.host'))
            ->set('user', config('services.mikrotik.user'))
            ->set('pass', config('services.mikrotik.pass'))
            ->set('port', config('services.mikrotik.port', 8728))
            ->set('timeout', max(1, config('services.mikrotik.connect_timeout', 2)))
            ->set('socket_timeout', max(1, config('services.mikrotik.socket_timeout', 2)))
            ->set('attempts', max(1, config('services.mikrotik.attempts', 1)))
            ->set('delay', 0);

        try {
            if (app()->bound(RouterClient::class)) {
                return app(RouterClient::class, ['config' => $config]);
            }

            return new RouterClient($config);
        } catch (\Throwable $e) {
            Log::warning('MikroTik unavailable; continuing without router data.', ['error' => $e->getMessage()]);
            throw $e;
        }
    }
}
