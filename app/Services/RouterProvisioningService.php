<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use RouterOS\Client as RouterClient;

class RouterProvisioningService
{
    private ?RouterClient $client = null;

    private function client(): RouterClient
    {
        if (! $this->client) {
            $this->client = MikrotikService::getClient();
        }

        return $this->client;
    }

    public function provisionAccess(object $session, string $commentPrefix): void
    {
        $mac = $session->mac_address;
        $ip = $session->ip_address ?? null;
        $speedLimit = $this->normalizeSpeedLimit($session->speed_limit ?? null);
        $comment = $commentPrefix.' '.$session->transaction_id;

        $this->removeHotspotUsers($mac);

        $query = [
            '/ip/hotspot/user/add',
            '=name='.$mac,
            '=password='.$mac,
            '=mac-address='.$mac,
            '=comment='.$comment,
        ];

        if (! empty($speedLimit)) {
            $query[] = '=rate-limit='.$speedLimit;
        }

        $this->client()->query($query)->read();

        $this->autoLogin($mac, $ip);
        $ip = $this->resolveIpAddress($mac, $ip);
        $this->syncBypassBindingAndQueue($mac, $ip, $speedLimit, $comment);
    }

    public function removeMacAccess(string $mac, bool $includeCookies = false): void
    {
        $this->removeActiveSessions($mac);
        $this->removeHotspotUsers($mac);
        $this->removeIpBindings($mac);
        $this->removeSimpleQueues($mac);
        $this->removeHosts($mac);

        if ($includeCookies) {
            $this->removeCookies($mac);
        }
    }

    public function removeLoginState(string $mac): void
    {
        $this->removeActiveSessions($mac);
        $this->removeHotspotUsers($mac);
    }

    private function autoLogin(string $mac, ?string $ip): void
    {
        if (empty($ip)) {
            return;
        }

        try {
            $this->removeActiveSessions($mac);
            $this->client()->query([
                '/ip/hotspot/active/login',
                '=user='.$mac,
                '=password='.$mac,
                '=ip='.$ip,
                '=mac-address='.$mac,
            ])->read();
        } catch (\Exception $e) {
            Log::warning("Could not auto-login MAC {$mac}.", ['error' => $e->getMessage()]);
        }
    }

    private function syncBypassBindingAndQueue(string $mac, ?string $ip, ?string $speedLimit, string $comment): void
    {
        try {
            $this->removeIpBindings($mac);

            $bindingQuery = [
                '/ip/hotspot/ip-binding/add',
                '=mac-address='.$mac,
                '=type=bypassed',
                '=comment='.$comment,
            ];

            if (! empty($ip)) {
                $bindingQuery[] = '=address='.$ip;
            }

            $this->client()->query($bindingQuery)->read();

            if (! empty($speedLimit) && ! empty($ip)) {
                $this->removeSimpleQueues($mac);

                $this->client()->query([
                    '/queue/simple/add',
                    '=name=RateLimit_'.$mac,
                    '=target='.$ip.'/32',
                    '=max-limit='.$speedLimit,
                    '=comment='.$comment,
                ])->read();
            }
        } catch (\Exception $e) {
            Log::warning("Could not add ip-binding or queue for MAC {$mac}.", ['error' => $e->getMessage()]);
        }
    }

    private function resolveIpAddress(string $mac, ?string $ip): ?string
    {
        if (! empty($ip)) {
            return $ip;
        }

        foreach ([
            ['/ip/hotspot/active/print', '?mac-address='.$mac],
            ['/ip/hotspot/host/print', '?mac-address='.$mac],
        ] as $query) {
            try {
                $rows = $this->client()->query($query)->read();
                $resolvedIp = $rows[0]['address'] ?? null;

                if (! empty($resolvedIp)) {
                    return $resolvedIp;
                }
            } catch (\Exception $e) {
                Log::warning("Could not resolve IP address for MAC {$mac}.", ['error' => $e->getMessage()]);
            }
        }

        return null;
    }

    private function normalizeSpeedLimit(?string $speedLimit): ?string
    {
        $speedLimit = trim((string) $speedLimit);

        if ($speedLimit === '') {
            return null;
        }

        if (! str_contains($speedLimit, '/')) {
            return $speedLimit.'/'.$speedLimit;
        }

        return $speedLimit;
    }

    private function removeActiveSessions(string $mac): void
    {
        $this->removeMatching(['/ip/hotspot/active/print', '?mac-address='.$mac], '/ip/hotspot/active/remove');
    }

    private function removeHotspotUsers(string $mac): void
    {
        $this->removeMatching(['/ip/hotspot/user/print', '?name='.$mac], '/ip/hotspot/user/remove');
    }

    private function removeIpBindings(string $mac): void
    {
        $this->removeMatching(['/ip/hotspot/ip-binding/print', '?mac-address='.$mac], '/ip/hotspot/ip-binding/remove');
    }

    private function removeSimpleQueues(string $mac): void
    {
        $this->removeMatching(['/queue/simple/print', '?name=RateLimit_'.$mac], '/queue/simple/remove');
    }

    private function removeHosts(string $mac): void
    {
        $this->removeMatching(['/ip/hotspot/host/print', '?mac-address='.$mac], '/ip/hotspot/host/remove');
    }

    private function removeCookies(string $mac): void
    {
        $this->removeMatching(['/ip/hotspot/cookie/print', '?mac-address='.$mac], '/ip/hotspot/cookie/remove');
    }

    private function removeMatching(array $printQuery, string $removePath): void
    {
        $items = $this->client()->query($printQuery)->read();

        foreach ($items as $item) {
            if (! empty($item['.id'])) {
                $this->client()->query([$removePath, '=.id='.$item['.id']])->read();
            }
        }
    }
}
