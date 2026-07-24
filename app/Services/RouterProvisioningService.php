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
        $credentials = $this->credentialsForMac($mac);

        $this->removeActiveSessions($mac);
        $this->removeHotspotUsers($mac);
        $this->removeIpBindings($mac);
        $this->removeSimpleQueues($mac);

        $query = [
            '/ip/hotspot/user/add',
            '=name='.$credentials['username'],
            '=password='.$credentials['password'],
            '=mac-address='.$mac,
            '=comment='.$comment,
        ];

        if (! empty($speedLimit)) {
            $query[] = '=rate-limit='.$speedLimit;
        }

        $createdUser = $this->client()->query($query)->read();
        $this->ensureHotspotUserCredentials($mac, $credentials, $createdUser);

        $ip = $this->resolveIpAddress($mac, $ip);
        $this->autoLogin($mac, $ip, $credentials);
        $this->syncSimpleQueue($mac, $ip, $speedLimit, $comment);
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

    private function ensureHotspotUserCredentials(string $mac, array $credentials, array $createdUser = []): void
    {
        $numbers = $createdUser[0]['.id'] ?? $credentials['username'];

        $this->client()->query([
            '/ip/hotspot/user/set',
            '=numbers='.$numbers,
            '=password='.$credentials['password'],
            '=mac-address='.$mac,
        ])->read();
    }

    private function autoLogin(string $mac, ?string $ip, array $credentials): void
    {
        if (empty($ip)) {
            throw new \RuntimeException("Cannot auto-login MAC {$mac}: no IP address is available.");
        }

        $this->client()->query([
            '/ip/hotspot/active/login',
            '=user='.$credentials['username'],
            '=password='.$credentials['password'],
            '=ip='.$ip,
            '=mac-address='.$mac,
        ])->read();

        if (! $this->isActiveSessionPresent($mac, $ip)) {
            throw new \RuntimeException("MikroTik auto-login did not create an active Hotspot session for MAC {$mac}.");
        }
    }

    private function isActiveSessionPresent(string $mac, ?string $ip): bool
    {
        for ($attempt = 0; $attempt < 3; $attempt++) {
            $activeRows = $this->client()->query(['/ip/hotspot/active/print', '?mac-address='.$mac])->read();

            foreach ($activeRows as $row) {
                $rowIp = $row['address'] ?? null;
                if (empty($ip) || empty($rowIp) || $rowIp === $ip) {
                    return true;
                }
            }

            if ($attempt < 2) {
                usleep(250000);
            }
        }

        Log::warning("MikroTik auto-login verification failed for MAC {$mac}.", ['ip' => $ip]);

        return false;
    }

    private function syncSimpleQueue(string $mac, ?string $ip, ?string $speedLimit, string $comment): void
    {
        if (empty($speedLimit) || empty($ip)) {
            return;
        }

        try {
            $this->client()->query([
                '/queue/simple/add',
                '=name=RateLimit_'.$mac,
                '=target='.$ip.'/32',
                '=max-limit='.$speedLimit,
                '=comment='.$comment,
            ])->read();
        } catch (\Exception $e) {
            Log::warning("Could not add simple queue for MAC {$mac}.", ['error' => $e->getMessage()]);
        }
    }

    private function resolveIpAddress(string $mac, ?string $ip): ?string
    {
        foreach ([
            ['/ip/hotspot/active/print', '?mac-address='.$mac],
            ['/ip/hotspot/host/print', '?mac-address='.$mac],
            ['/ip/dhcp-server/lease/print', '?mac-address='.$mac],
        ] as $query) {
            try {
                $rows = $this->client()->query($query)->read();
                $resolvedIp = $rows[0]['address'] ?? null;

                if (! empty($resolvedIp)) {
                    if (! empty($ip) && $ip !== $resolvedIp) {
                        Log::info("Using current router IP {$resolvedIp} for MAC {$mac} instead of stored IP {$ip}.");
                    }

                    return $resolvedIp;
                }
            } catch (\Exception $e) {
                Log::warning("Could not resolve IP address for MAC {$mac}.", ['query' => $query[0] ?? null, 'error' => $e->getMessage()]);
            }
        }

        return $ip;
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

    private function credentialsForMac(string $mac): array
    {
        $compactMac = strtolower(preg_replace('/[^a-fA-F0-9]/', '', $mac));

        return [
            'username' => 'hs_'.$compactMac,
            'password' => 'hs_'.$compactMac.'_pw',
        ];
    }

    private function removeActiveSessions(string $mac): void
    {
        $this->removeMatching(['/ip/hotspot/active/print', '?mac-address='.$mac], '/ip/hotspot/active/remove');
    }

    private function removeHotspotUsers(string $mac): void
    {
        $credentials = $this->credentialsForMac($mac);

        $this->removeMatching(['/ip/hotspot/user/print', '?name='.$mac], '/ip/hotspot/user/remove');
        $this->removeMatching(['/ip/hotspot/user/print', '?name='.$credentials['username']], '/ip/hotspot/user/remove');
        $this->removeMatching(['/ip/hotspot/user/print', '?mac-address='.$mac], '/ip/hotspot/user/remove');
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
