<?php

namespace Tests\Unit;

use App\Services\RouterProvisioningService;
use Illuminate\Support\Facades\Log;
use RouterOS\Client as RouterClient;
use Tests\TestCase;

class RouterProvisioningServiceTest extends TestCase
{
    public function test_provision_access_uses_verified_hotspot_credentials_without_creating_bypass_binding()
    {
        $session = (object) [
            'transaction_id' => 'TXN_ROUTER_1',
            'mac_address' => 'aa-bb-cc-dd-ee-ff',
            'ip_address' => '192.168.88.10',
            'speed_limit' => '2M/2M',
        ];

        $user = [['.id' => '*new-user', 'name' => 'hs_aabbccddeeff', 'password' => 'hs_aabbccddeeff_pw', 'mac-address' => 'AA:BB:CC:DD:EE:FF', 'disabled' => 'false']];
        $queries = [];
        $mock = $this->mockRouterClient([
            [['.id' => '*active']], [],
            [['.id' => '*old-user']], [],
            [],
            [['.id' => '*safe-user']], [],
            [],
            [['.id' => '*binding']], [],
            [['.id' => '*queue']], [],
            [],
            $user,
            [],
            $user,
            [['address' => '192.168.88.10']],
            [],
            [['.id' => '*active-new', 'address' => '192.168.88.10']],
            [],
        ], $queries);

        $this->app->bind(RouterClient::class, fn () => $mock);
        Log::shouldReceive('info')->zeroOrMoreTimes();
        Log::shouldReceive('warning')->never();

        app(RouterProvisioningService::class)->provisionAccess($session, 'Selcom Txn');

        $this->assertContains(['/ip/hotspot/user/add', '=name=hs_aabbccddeeff', '=password=hs_aabbccddeeff_pw', '=mac-address=AA:BB:CC:DD:EE:FF', '=comment=Selcom Txn TXN_ROUTER_1', '=rate-limit=2M/2M'], $queries);
        $this->assertContains(['/ip/hotspot/user/set', '=numbers=*new-user', '=password=hs_aabbccddeeff_pw', '=mac-address=AA:BB:CC:DD:EE:FF', '=disabled=no'], $queries);
        $this->assertContains(['/ip/hotspot/active/login', '=user=hs_aabbccddeeff', '=password=hs_aabbccddeeff_pw', '=ip=192.168.88.10', '=mac-address=AA:BB:CC:DD:EE:FF'], $queries);
        $this->assertContains(['/queue/simple/add', '=name=RateLimit_AA:BB:CC:DD:EE:FF', '=target=192.168.88.10/32', '=max-limit=2M/2M', '=comment=Selcom Txn TXN_ROUTER_1'], $queries);
        $this->assertFalse($this->queriesContainPath($queries, '/ip/hotspot/ip-binding/add'));
    }

    public function test_provision_access_resolves_ip_before_creating_speed_limit_queue()
    {
        $session = (object) [
            'transaction_id' => 'TXN_ROUTER_2',
            'mac_address' => '11:22:33:44:55:66',
            'ip_address' => null,
            'speed_limit' => '1M',
        ];

        $user = [['.id' => '*new-user', 'name' => 'hs_112233445566', 'password' => 'hs_112233445566_pw', 'mac-address' => '11:22:33:44:55:66', 'disabled' => 'false']];
        $queries = [];
        $mock = $this->mockRouterClient([
            [], [], [], [], [], [], [],
            [],
            $user,
            [],
            $user,
            [],
            [['address' => '192.168.88.25']],
            [],
            [['.id' => '*active-new', 'address' => '192.168.88.25']],
            [],
        ], $queries);

        $this->app->bind(RouterClient::class, fn () => $mock);
        Log::shouldReceive('info')->zeroOrMoreTimes();
        Log::shouldReceive('warning')->never();

        app(RouterProvisioningService::class)->provisionAccess($session, 'Recovered Txn');

        $this->assertContains(['/ip/hotspot/active/login', '=user=hs_112233445566', '=password=hs_112233445566_pw', '=ip=192.168.88.25', '=mac-address=11:22:33:44:55:66'], $queries);
        $this->assertContains(['/queue/simple/add', '=name=RateLimit_11:22:33:44:55:66', '=target=192.168.88.25/32', '=max-limit=1M/1M', '=comment=Recovered Txn TXN_ROUTER_2'], $queries);
        $this->assertFalse($this->queriesContainPath($queries, '/ip/hotspot/ip-binding/add'));
    }

    public function test_provision_access_surfaces_mikrotik_user_add_trap_message()
    {
        $session = (object) ['transaction_id' => 'TXN_TRAP', 'mac_address' => '78:62:56:C5:52:61', 'ip_address' => '192.168.88.233', 'speed_limit' => '5M/5M'];

        $queries = [];
        $mock = $this->mockRouterClient([
            [], [], [], [], [], [], [],
            ['after' => ['message' => 'input does not match any value of rate-limit']],
        ], $queries);

        $this->app->bind(RouterClient::class, fn () => $mock);
        Log::shouldReceive('info')->zeroOrMoreTimes();
        Log::shouldReceive('error')->once()->with('MikroTik command failed.', \Mockery::on(fn ($context) => ($context['action'] ?? null) === 'create hotspot user'
            && ($context['message'] ?? null) === 'input does not match any value of rate-limit'));
        Log::shouldReceive('warning')->never();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('MikroTik create hotspot user failed: input does not match any value of rate-limit');

        app(RouterProvisioningService::class)->provisionAccess($session, 'Selcom Txn');

        $this->assertContains(['/ip/hotspot/user/add', '=name=hs_786256c55261', '=password=hs_786256c55261_pw', '=mac-address=78:62:56:C5:52:61', '=comment=Selcom Txn TXN_TRAP', '=rate-limit=5M/5M'], $queries);
    }

    public function test_provision_access_fails_before_auto_login_when_hotspot_password_does_not_persist()
    {
        $session = (object) ['transaction_id' => 'TXN_BAD_USER', 'mac_address' => '78:62:56:C5:52:61', 'ip_address' => '192.168.88.233', 'speed_limit' => '5M/5M'];

        $queries = [];
        $mock = $this->mockRouterClient([
            [], [], [], [], [], [], [],
            [],
            [['.id' => '*new-user', 'name' => 'hs_786256c55261', 'password' => 'wrong', 'mac-address' => '78:62:56:C5:52:61', 'disabled' => 'false']],
            [],
            [['.id' => '*new-user', 'name' => 'hs_786256c55261', 'password' => 'wrong', 'mac-address' => '78:62:56:C5:52:61', 'disabled' => 'false']],
        ], $queries);

        $this->app->bind(RouterClient::class, fn () => $mock);
        Log::shouldReceive('info')->zeroOrMoreTimes();
        Log::shouldReceive('warning')->never();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('password did not persist');

        app(RouterProvisioningService::class)->provisionAccess($session, 'Auto-Reconnect Txn');
    }

    public function test_provision_access_fails_when_auto_login_does_not_create_active_session()
    {
        $session = (object) ['transaction_id' => 'TXN_AUTH_FAIL', 'mac_address' => '78:62:56:C5:52:61', 'ip_address' => '192.168.88.233', 'speed_limit' => '5M/5M'];
        $user = [['.id' => '*new-user', 'name' => 'hs_786256c55261', 'password' => 'hs_786256c55261_pw', 'mac-address' => '78:62:56:C5:52:61', 'disabled' => 'false']];

        $queries = [];
        $mock = $this->mockRouterClient([
            [], [], [], [], [], [], [],
            [],
            $user,
            [],
            $user,
            [], [], [],
            [],
            [], [], [],
        ], $queries);

        $this->app->bind(RouterClient::class, fn () => $mock);
        Log::shouldReceive('info')->zeroOrMoreTimes();
        Log::shouldReceive('warning')->once();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('MikroTik auto-login did not create an active Hotspot session');

        app(RouterProvisioningService::class)->provisionAccess($session, 'Auto-Reconnect Txn');

        $this->assertFalse($this->queriesContainPath($queries, '/queue/simple/add'));
    }

    public function test_provision_access_prefers_current_dhcp_lease_over_stored_ip()
    {
        $session = (object) [
            'transaction_id' => 'TXN_STALE_IP',
            'mac_address' => '22:33:44:55:66:77',
            'ip_address' => '192.168.88.200',
            'speed_limit' => '3M/3M',
        ];

        $user = [['.id' => '*new-user', 'name' => 'hs_223344556677', 'password' => 'hs_223344556677_pw', 'mac-address' => '22:33:44:55:66:77', 'disabled' => 'false']];
        $queries = [];
        $mock = $this->mockRouterClient([
            [], [], [], [], [], [], [],
            [],
            $user,
            [],
            $user,
            [], [],
            [['address' => '192.168.88.77']],
            [],
            [['.id' => '*active-new', 'address' => '192.168.88.77']],
            [],
        ], $queries);

        $this->app->bind(RouterClient::class, fn () => $mock);
        Log::shouldReceive('info')->zeroOrMoreTimes();
        Log::shouldReceive('warning')->never();

        app(RouterProvisioningService::class)->provisionAccess($session, 'Admin Extend Txn');

        $this->assertContains(['/ip/hotspot/active/login', '=user=hs_223344556677', '=password=hs_223344556677_pw', '=ip=192.168.88.77', '=mac-address=22:33:44:55:66:77'], $queries);
        $this->assertContains(['/queue/simple/add', '=name=RateLimit_22:33:44:55:66:77', '=target=192.168.88.77/32', '=max-limit=3M/3M', '=comment=Admin Extend Txn TXN_STALE_IP'], $queries);
    }

    public function test_remove_mac_access_removes_every_matching_simple_queue()
    {
        $queries = [];
        $mock = $this->mockRouterClient([
            [], [], [], [], [], [],
            [['.id' => '*queue1'], ['.id' => '*queue2']], [], [],
            [], [],
        ], $queries);

        $this->app->bind(RouterClient::class, fn () => $mock);

        app(RouterProvisioningService::class)->removeMacAccess('aa-bb-cc-dd-ee-ff', true);

        $this->assertContains(['/queue/simple/remove', '=.id=*queue1'], $queries);
        $this->assertContains(['/queue/simple/remove', '=.id=*queue2'], $queries);
        $this->assertContains(['/ip/hotspot/user/print', '?name=aa:bb:cc:dd:ee:ff'], $queries);
    }

    private function mockRouterClient(array $readResponses, array &$queries): RouterClient
    {
        $mock = \Mockery::mock(RouterClient::class);

        $mock->shouldReceive('query')->zeroOrMoreTimes()->andReturnUsing(function ($query) use (&$queries, &$mock) {
            $queries[] = $query;

            return $mock;
        });

        $mock->shouldReceive('read')->zeroOrMoreTimes()->andReturnUsing(function () use (&$readResponses) {
            return array_shift($readResponses) ?? [];
        });

        return $mock;
    }

    private function queriesContainPath(array $queries, string $path): bool
    {
        foreach ($queries as $query) {
            if (is_array($query) && ($query[0] ?? null) === $path) {
                return true;
            }
        }

        return false;
    }
}
