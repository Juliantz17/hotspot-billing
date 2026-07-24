<?php

namespace Tests\Unit;

use App\Services\RouterProvisioningService;
use Illuminate\Support\Facades\Log;
use RouterOS\Client as RouterClient;
use Tests\TestCase;

class RouterProvisioningServiceTest extends TestCase
{
    public function test_provision_access_uses_safe_hotspot_credentials_without_creating_bypass_binding()
    {
        $session = (object) [
            'transaction_id' => 'TXN_ROUTER_1',
            'mac_address' => 'AA:BB:CC:DD:EE:FF',
            'ip_address' => '192.168.88.10',
            'speed_limit' => '2M/2M',
        ];

        $mock = \Mockery::mock(RouterClient::class);

        $mock->shouldReceive('query')->once()->with(['/ip/hotspot/active/print', '?mac-address=AA:BB:CC:DD:EE:FF'])->andReturnSelf();
        $mock->shouldReceive('read')->once()->andReturn([['.id' => '*active']]);
        $mock->shouldReceive('query')->once()->with(['/ip/hotspot/active/remove', '=.id=*active'])->andReturnSelf();
        $mock->shouldReceive('read')->once()->andReturn([]);
        $mock->shouldReceive('query')->once()->with(['/ip/hotspot/user/print', '?name=AA:BB:CC:DD:EE:FF'])->andReturnSelf();
        $mock->shouldReceive('read')->once()->andReturn([['.id' => '*old-user']]);
        $mock->shouldReceive('query')->once()->with(['/ip/hotspot/user/remove', '=.id=*old-user'])->andReturnSelf();
        $mock->shouldReceive('read')->once()->andReturn([]);
        $mock->shouldReceive('query')->once()->with(['/ip/hotspot/user/print', '?name=hs_aabbccddeeff'])->andReturnSelf();
        $mock->shouldReceive('read')->once()->andReturn([['.id' => '*safe-user']]);
        $mock->shouldReceive('query')->once()->with(['/ip/hotspot/user/remove', '=.id=*safe-user'])->andReturnSelf();
        $mock->shouldReceive('read')->once()->andReturn([]);
        $mock->shouldReceive('query')->once()->with(['/ip/hotspot/user/print', '?mac-address=AA:BB:CC:DD:EE:FF'])->andReturnSelf();
        $mock->shouldReceive('read')->once()->andReturn([]);
        $mock->shouldReceive('query')->once()->with(['/ip/hotspot/ip-binding/print', '?mac-address=AA:BB:CC:DD:EE:FF'])->andReturnSelf();
        $mock->shouldReceive('read')->once()->andReturn([['.id' => '*binding']]);
        $mock->shouldReceive('query')->once()->with(['/ip/hotspot/ip-binding/remove', '=.id=*binding'])->andReturnSelf();
        $mock->shouldReceive('read')->once()->andReturn([]);
        $mock->shouldReceive('query')->once()->with(['/queue/simple/print', '?name=RateLimit_AA:BB:CC:DD:EE:FF'])->andReturnSelf();
        $mock->shouldReceive('read')->once()->andReturn([['.id' => '*queue']]);
        $mock->shouldReceive('query')->once()->with(['/queue/simple/remove', '=.id=*queue'])->andReturnSelf();
        $mock->shouldReceive('read')->once()->andReturn([]);

        $mock->shouldReceive('query')->once()->withArgs(fn ($query) => is_array($query)
            && $query[0] === '/ip/hotspot/user/add'
            && in_array('=name=hs_aabbccddeeff', $query)
            && in_array('=password=hs_aabbccddeeff_pw', $query)
            && in_array('=mac-address=AA:BB:CC:DD:EE:FF', $query)
            && in_array('=rate-limit=2M/2M', $query)
            && in_array('=comment=Selcom Txn TXN_ROUTER_1', $query)
            && ! in_array('=password=AA:BB:CC:DD:EE:FF', $query))->andReturnSelf();
        $mock->shouldReceive('read')->once()->andReturn([]);
        $mock->shouldReceive('query')->once()->with(['/ip/hotspot/user/set', '=numbers=hs_aabbccddeeff', '=password=hs_aabbccddeeff_pw', '=mac-address=AA:BB:CC:DD:EE:FF'])->andReturnSelf();
        $mock->shouldReceive('read')->once()->andReturn([]);
        $mock->shouldReceive('query')->once()->with(['/ip/hotspot/active/login', '=user=hs_aabbccddeeff', '=password=hs_aabbccddeeff_pw', '=ip=192.168.88.10', '=mac-address=AA:BB:CC:DD:EE:FF'])->andReturnSelf();
        $mock->shouldReceive('read')->once()->andReturn([]);
        $mock->shouldReceive('query')->once()->with(['/ip/hotspot/active/print', '?mac-address=AA:BB:CC:DD:EE:FF'])->andReturnSelf();
        $mock->shouldReceive('read')->once()->andReturn([['.id' => '*active-new', 'address' => '192.168.88.10']]);
        $mock->shouldReceive('query')->once()->with(['/queue/simple/add', '=name=RateLimit_AA:BB:CC:DD:EE:FF', '=target=192.168.88.10/32', '=max-limit=2M/2M', '=comment=Selcom Txn TXN_ROUTER_1'])->andReturnSelf();
        $mock->shouldReceive('read')->once()->andReturn([]);
        $mock->shouldReceive('query')->never()->withArgs(fn ($query) => is_array($query) && ($query[0] ?? null) === '/ip/hotspot/ip-binding/add');

        $this->app->bind(RouterClient::class, fn () => $mock);
        Log::shouldReceive('warning')->never();

        app(RouterProvisioningService::class)->provisionAccess($session, 'Selcom Txn');
    }

    public function test_provision_access_resolves_ip_before_creating_speed_limit_queue()
    {
        $session = (object) [
            'transaction_id' => 'TXN_ROUTER_2',
            'mac_address' => '11:22:33:44:55:66',
            'ip_address' => null,
            'speed_limit' => '1M',
        ];

        $mock = \Mockery::mock(RouterClient::class);
        $mock->shouldReceive('query')->once()->with(['/ip/hotspot/active/print', '?mac-address=11:22:33:44:55:66'])->andReturnSelf();
        $mock->shouldReceive('read')->once()->andReturn([]);
        $mock->shouldReceive('query')->once()->with(['/ip/hotspot/user/print', '?name=11:22:33:44:55:66'])->andReturnSelf();
        $mock->shouldReceive('read')->once()->andReturn([]);
        $mock->shouldReceive('query')->once()->with(['/ip/hotspot/user/print', '?name=hs_112233445566'])->andReturnSelf();
        $mock->shouldReceive('read')->once()->andReturn([]);
        $mock->shouldReceive('query')->once()->with(['/ip/hotspot/user/print', '?mac-address=11:22:33:44:55:66'])->andReturnSelf();
        $mock->shouldReceive('read')->once()->andReturn([]);
        $mock->shouldReceive('query')->once()->with(['/ip/hotspot/ip-binding/print', '?mac-address=11:22:33:44:55:66'])->andReturnSelf();
        $mock->shouldReceive('read')->once()->andReturn([]);
        $mock->shouldReceive('query')->once()->with(['/queue/simple/print', '?name=RateLimit_11:22:33:44:55:66'])->andReturnSelf();
        $mock->shouldReceive('read')->once()->andReturn([]);
        $mock->shouldReceive('query')->once()->withArgs(fn ($query) => is_array($query) && $query[0] === '/ip/hotspot/user/add' && in_array('=name=hs_112233445566', $query) && in_array('=password=hs_112233445566_pw', $query) && in_array('=rate-limit=1M/1M', $query))->andReturnSelf();
        $mock->shouldReceive('read')->once()->andReturn([]);
        $mock->shouldReceive('query')->once()->with(['/ip/hotspot/user/set', '=numbers=hs_112233445566', '=password=hs_112233445566_pw', '=mac-address=11:22:33:44:55:66'])->andReturnSelf();
        $mock->shouldReceive('read')->once()->andReturn([]);
        $mock->shouldReceive('query')->once()->with(['/ip/hotspot/active/print', '?mac-address=11:22:33:44:55:66'])->andReturnSelf();
        $mock->shouldReceive('read')->once()->andReturn([]);
        $mock->shouldReceive('query')->once()->with(['/ip/hotspot/host/print', '?mac-address=11:22:33:44:55:66'])->andReturnSelf();
        $mock->shouldReceive('read')->once()->andReturn([['address' => '192.168.88.25']]);
        $mock->shouldReceive('query')->once()->with(['/ip/hotspot/active/login', '=user=hs_112233445566', '=password=hs_112233445566_pw', '=ip=192.168.88.25', '=mac-address=11:22:33:44:55:66'])->andReturnSelf();
        $mock->shouldReceive('read')->once()->andReturn([]);
        $mock->shouldReceive('query')->once()->with(['/ip/hotspot/active/print', '?mac-address=11:22:33:44:55:66'])->andReturnSelf();
        $mock->shouldReceive('read')->once()->andReturn([['.id' => '*active-new', 'address' => '192.168.88.25']]);
        $mock->shouldReceive('query')->once()->with(['/queue/simple/add', '=name=RateLimit_11:22:33:44:55:66', '=target=192.168.88.25/32', '=max-limit=1M/1M', '=comment=Recovered Txn TXN_ROUTER_2'])->andReturnSelf();
        $mock->shouldReceive('read')->once()->andReturn([]);
        $mock->shouldReceive('query')->never()->withArgs(fn ($query) => is_array($query) && ($query[0] ?? null) === '/ip/hotspot/ip-binding/add');

        $this->app->bind(RouterClient::class, fn () => $mock);
        Log::shouldReceive('warning')->never();

        app(RouterProvisioningService::class)->provisionAccess($session, 'Recovered Txn');
    }

    public function test_provision_access_fails_when_auto_login_does_not_create_active_session()
    {
        $session = (object) ['transaction_id' => 'TXN_AUTH_FAIL', 'mac_address' => '78:62:56:C5:52:61', 'ip_address' => '192.168.88.233', 'speed_limit' => '5M/5M'];
        $mock = \Mockery::mock(RouterClient::class);
        foreach ([['/ip/hotspot/active/print', '?mac-address=78:62:56:C5:52:61'], ['/ip/hotspot/user/print', '?name=78:62:56:C5:52:61'], ['/ip/hotspot/user/print', '?name=hs_786256c55261'], ['/ip/hotspot/user/print', '?mac-address=78:62:56:C5:52:61'], ['/ip/hotspot/ip-binding/print', '?mac-address=78:62:56:C5:52:61'], ['/queue/simple/print', '?name=RateLimit_78:62:56:C5:52:61']] as $query) {
            $mock->shouldReceive('query')->once()->with($query)->andReturnSelf();
            $mock->shouldReceive('read')->once()->andReturn([]);
        }
        $mock->shouldReceive('query')->once()->withArgs(fn ($query) => is_array($query) && $query[0] === '/ip/hotspot/user/add' && in_array('=name=hs_786256c55261', $query))->andReturnSelf();
        $mock->shouldReceive('read')->once()->andReturn([]);
        $mock->shouldReceive('query')->once()->with(['/ip/hotspot/user/set', '=numbers=hs_786256c55261', '=password=hs_786256c55261_pw', '=mac-address=78:62:56:C5:52:61'])->andReturnSelf();
        $mock->shouldReceive('read')->once()->andReturn([]);
        $mock->shouldReceive('query')->once()->with(['/ip/hotspot/active/login', '=user=hs_786256c55261', '=password=hs_786256c55261_pw', '=ip=192.168.88.233', '=mac-address=78:62:56:C5:52:61'])->andReturnSelf();
        $mock->shouldReceive('read')->once()->andReturn([]);
        $mock->shouldReceive('query')->times(3)->with(['/ip/hotspot/active/print', '?mac-address=78:62:56:C5:52:61'])->andReturnSelf();
        $mock->shouldReceive('read')->times(3)->andReturn([]);
        $mock->shouldReceive('query')->never()->withArgs(fn ($query) => is_array($query) && ($query[0] ?? null) === '/queue/simple/add');
        $this->app->bind(RouterClient::class, fn () => $mock);
        Log::shouldReceive('warning')->once();
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('MikroTik auto-login did not create an active Hotspot session');
        app(RouterProvisioningService::class)->provisionAccess($session, 'Auto-Reconnect Txn');
    }

    public function test_remove_mac_access_removes_every_matching_simple_queue()
    {
        $mock = \Mockery::mock(RouterClient::class);
        foreach ([['/ip/hotspot/active/print', '?mac-address=AA:BB:CC:DD:EE:FF'], ['/ip/hotspot/user/print', '?name=AA:BB:CC:DD:EE:FF'], ['/ip/hotspot/user/print', '?name=hs_aabbccddeeff'], ['/ip/hotspot/user/print', '?mac-address=AA:BB:CC:DD:EE:FF'], ['/ip/hotspot/ip-binding/print', '?mac-address=AA:BB:CC:DD:EE:FF']] as $query) {
            $mock->shouldReceive('query')->once()->with($query)->andReturnSelf();
            $mock->shouldReceive('read')->once()->andReturn([]);
        }
        $mock->shouldReceive('query')->once()->with(['/queue/simple/print', '?name=RateLimit_AA:BB:CC:DD:EE:FF'])->andReturnSelf();
        $mock->shouldReceive('read')->once()->andReturn([['.id' => '*queue1'], ['.id' => '*queue2']]);
        $mock->shouldReceive('query')->once()->with(['/queue/simple/remove', '=.id=*queue1'])->andReturnSelf();
        $mock->shouldReceive('read')->once()->andReturn([]);
        $mock->shouldReceive('query')->once()->with(['/queue/simple/remove', '=.id=*queue2'])->andReturnSelf();
        $mock->shouldReceive('read')->once()->andReturn([]);
        $mock->shouldReceive('query')->once()->with(['/ip/hotspot/host/print', '?mac-address=AA:BB:CC:DD:EE:FF'])->andReturnSelf();
        $mock->shouldReceive('read')->once()->andReturn([]);
        $mock->shouldReceive('query')->once()->with(['/ip/hotspot/cookie/print', '?mac-address=AA:BB:CC:DD:EE:FF'])->andReturnSelf();
        $mock->shouldReceive('read')->once()->andReturn([]);
        $this->app->bind(RouterClient::class, fn () => $mock);
        app(RouterProvisioningService::class)->removeMacAccess('AA:BB:CC:DD:EE:FF', true);
    }
}
