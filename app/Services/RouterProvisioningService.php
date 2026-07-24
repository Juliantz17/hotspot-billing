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
        $originalMac = $session->mac_address;
        $mac = $this->normalizeMacAddress($originalMac);
        $ip = $session->ip_address ?? null;
        $speedLimit = $this->normalizeSpeedLimit($session->speed_limit ?? null);
        $comment = $commentPrefix.' '.$session->transaction_id;
        $credentials = $this->credentialsForMac($mac);

        if ($mac !== $originalMac) {
            Log::info('Normalized MAC before MikroTik provisioning.', ['original_mac' => $originalMac, 'mac' => $mac]);
        }

        Log::info('Starting MikroTik provisioning.', [
            'transaction_id' => $session->transaction_id ?? null,
            'mac' => $mac,
            'stored_ip' => $ip,
            'username' => $credentials['username'],
            'speed_limit' => $speedLimit,
        ]);

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

        $this->runRouterCommand($query, 'create hotspot user');
        $this->ensureHotspotUserCredentials($mac, $credentials);

        $ip = $this->resolveHotspotLoginIp($mac, $ip);
        if (empty($ip)) {
            Log::warning('MikroTik user prepared but auto-login skipped because device is not currently visible in Hotspot hosts.', [
                'transaction_id' => $session->transaction_id ?? null,
                'mac' => $mac,
                'stored_ip' => $session->ip_address ?? null,
                'username' => $credentials['username'],
            ]);

            return;
        }

        $this->autoLogin($mac, $ip, $credentials);
        $this->syncSimpleQueue($mac, $ip, $speedLimit, $comment);
    }

    public function removeMacAccess(string $mac, bool $includeCookies = false): void
    {
        $mac = $this->normalizeMacAddress($mac);

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
        $mac = $this->normalizeMacAddress($mac);

        $this->removeActiveSessions($mac);
        $this->removeHotspotUsers($mac);
    }

    private function ensureHotspotUserCredentials(string $mac, array $credentials): void
    {
        $hotspotUser = $this->findHotspotUser($credentials['username']);

        if (empty($hotspotUser['.id'])) {
            throw new \RuntimeException("MikroTik Hotspot user {$credentials['username']} was not created for MAC {$mac}.");
        }

        $this->runRouterCommand([
            '/ip/hotspot/user/set',
            '=numbers='.$hotspotUser['.id'],
            '=password='.$credentials['password'],
            '=mac-address='.$mac,
            '=disabled=no',
        ], 'set hotspot user credentials');

        $verifiedUser = $this->findHotspotUser($credentials['username']);
        $this->assertHotspotUserReady($mac, $credentials, $verifiedUser);

        Log::info('MikroTik Hotspot user verified before auto-login.', [
            'mac' => $mac,
            'username' => $credentials['username'],
            'router_user_id' => $verifiedUser['.id'] ?? null,
            'profile' => $verifiedUser['profile'] ?? null,
            'server' => $verifiedUser['server'] ?? null,
            'disabled' => $verifiedUser['disabled'] ?? null,
            'has_password_field' => array_key_exists('password', $verifiedUser),
        ]);
    }

    private function findHotspotUser(string $username): ?array
    {
        $rows = $this->client()->query(['/ip/hotspot/user/print', '?name='.$username])->read();

        return $rows[0] ?? null;
    }

    private function assertHotspotUserReady(string $mac, array $credentials, ?array $hotspotUser): void
    {
        if (empty($hotspotUser)) {
            throw new \RuntimeException("MikroTik Hotspot user {$credentials['username']} could not be read back after creation.");
        }

        if (($hotspotUser['disabled'] ?? 'false') === 'true') {
            throw new \RuntimeException("MikroTik Hotspot user {$credentials['username']} is disabled.");
        }

        $routerMac = $this->normalizeMacAddress($hotspotUser['mac-address'] ?? '');
        if (! empty($routerMac) && $routerMac !== $mac) {
            throw new \RuntimeException("MikroTik Hotspot user {$credentials['username']} has MAC {$routerMac}, expected {$mac}.");
        }

        if (array_key_exists('password', $hotspotUser) && $hotspotUser['password'] !== $credentials['password']) {
            throw new \RuntimeException("MikroTik Hotspot user {$credentials['username']} password did not persist before auto-login.");
        }
    }

    private function autoLogin(string $mac, ?string $ip, array $credentials): void
    {
        if (empty($ip)) {
            throw new \RuntimeException("Cannot auto-login MAC {$mac}: no IP address is available.");
        }

        Log::info('Attempting MikroTik Hotspot active login.', [
            'mac' => $mac,
            'ip' => $ip,
            'username' => $credentials['username'],
        ]);

        $loginResponse = $this->runRouterCommand([
            '/ip/hotspot/active/login',
            '=user='.$credentials['username'],
            '=password='.$credentials['password'],
            '=ip='.$ip,
            '=mac-address='.$mac,
        ], 'hotspot active login');

        Log::info('MikroTik Hotspot active login command returned.', [
            'mac' => $mac,
            'ip' => $ip,
            'username' => $credentials['username'],
            'response' => $loginResponse,
        ]);

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

        Log::warning('MikroTik auto-login verification failed.', ['mac' => $mac, 'ip' => $ip]);

        return false;
    }

    private function syncSimpleQueue(string $mac, ?string $ip, ?string $speedLimit, string $comment): void
    {
        if (empty($speedLimit) || empty($ip)) {
            return;
        }

        try {
            $this->runRouterCommand([
                '/queue/simple/add',
                '=name=RateLimit_'.$mac,
                '=target='.$ip.'/32',
                '=max-limit='.$speedLimit,
                '=comment='.$comment,
            ], 'create simple queue');
        } catch (\Exception $e) {
            Log::warning("Could not add simple queue for MAC {$mac}.", ['error' => $e->getMessage()]);
        }
    }

    private function resolveHotspotLoginIp(string $mac, ?string $ip): ?string
    {
        foreach ($this->macVariants($mac) as $macVariant) {
            foreach ([
                'active' => ['/ip/hotspot/active/print', '?mac-address='.$macVariant],
                'host' => ['/ip/hotspot/host/print', '?mac-address='.$macVariant],
            ] as $source => $query) {
                $resolvedIp = $this->firstRouterAddress($query, $mac, $source);

                if (! empty($resolvedIp)) {
                    if (! empty($ip) && $ip !== $resolvedIp) {
                        Log::info("Using current Hotspot {$source} IP {$resolvedIp} for MAC {$mac} instead of stored IP {$ip}.");
                    }

                    return $resolvedIp;
                }
            }
        }

        if (! empty($ip)) {
            foreach ([
                'active' => ['/ip/hotspot/active/print', '?address='.$ip],
                'host' => ['/ip/hotspot/host/print', '?address='.$ip],
            ] as $source => $query) {
                $resolvedIp = $this->firstRouterAddress($query, $mac, $source);

                if (! empty($resolvedIp)) {
                    return $resolvedIp;
                }
            }
        }

        Log::warning('Cannot auto-login because MikroTik has no current Hotspot host for device.', [
            'mac' => $mac,
            'stored_ip' => $ip,
        ]);

        return null;
    }

    private function firstRouterAddress(array $query, string $mac, string $source): ?string
    {
        try {
            $rows = $this->client()->query($query)->read();

            return $rows[0]['address'] ?? null;
        } catch (\Exception $e) {
            Log::warning("Could not resolve Hotspot {$source} IP for MAC {$mac}.", ['query' => $query[0] ?? null, 'error' => $e->getMessage()]);

            return null;
        }
    }

    private function runRouterCommand(array|string $query, string $action): array
    {
        $response = $this->client()->query($query)->read();
        $trapMessage = $this->routerTrapMessage($response);

        if ($trapMessage !== null) {
            Log::error('MikroTik command failed.', [
                'action' => $action,
                'query' => $this->redactRouterQuery($query),
                'response' => $response,
                'message' => $trapMessage,
            ]);

            throw new \RuntimeException("MikroTik {$action} failed: {$trapMessage}");
        }

        Log::info('MikroTik command succeeded.', [
            'action' => $action,
            'query' => $this->redactRouterQuery($query),
            'response' => $response,
        ]);

        return is_array($response) ? $response : [];
    }

    private function routerTrapMessage(mixed $response): ?string
    {
        if (! is_array($response)) {
            return null;
        }

        if (isset($response['after']['message'])) {
            return (string) $response['after']['message'];
        }

        if (isset($response['message'])) {
            return (string) $response['message'];
        }

        foreach ($response as $row) {
            if (is_array($row) && isset($row['message'])) {
                return (string) $row['message'];
            }
        }

        return null;
    }

    private function redactRouterQuery(array|string $query): array|string
    {
        if (! is_array($query)) {
            return $query;
        }

        return array_map(function ($part) {
            if (is_string($part) && str_starts_with($part, '=password=')) {
                return '=password=[redacted]';
            }

            return $part;
        }, $query);
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

    private function normalizeMacAddress(string $mac): string
    {
        $compactMac = strtoupper(preg_replace('/[^a-fA-F0-9]/', '', $mac));

        if (strlen($compactMac) === 12) {
            return implode(':', str_split($compactMac, 2));
        }

        return strtoupper($mac);
    }

    private function macVariants(string $mac): array
    {
        return array_values(array_unique([
            $mac,
            strtoupper($mac),
            strtolower($mac),
        ]));
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
        $this->removeMatching(['/ip/hotspot/user/print', '?name='.strtolower($mac)], '/ip/hotspot/user/remove');
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
                $this->runRouterCommand([$removePath, '=.id='.$item['.id']], 'remove router item');
            }
        }
    }
}
