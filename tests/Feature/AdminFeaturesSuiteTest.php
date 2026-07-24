<?php

namespace Tests\Feature;

use App\Services\RouterProvisioningService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RouterOS\Client as RouterClient;
use Tests\TestCase;

class AdminFeaturesSuiteTest extends TestCase
{
    use RefreshDatabase;

    public function test_search_transactions_by_phone_mac_and_id()
    {
        // Seed some transactions
        DB::table('hotspot_transactions')->insert([
            'transaction_id' => 'TXN_SEARCHABLE_ONE',
            'mac_address' => 'AA:BB:CC:DD:EE:F1',
            'phone_number' => '255711111111',
            'amount' => 1000,
            'duration_minutes' => 60,
            'status' => 'SUCCESS',
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);

        DB::table('hotspot_transactions')->insert([
            'transaction_id' => 'TXN_SEARCHABLE_TWO',
            'mac_address' => 'AA:BB:CC:DD:EE:F2',
            'phone_number' => '255722222222',
            'amount' => 2000,
            'duration_minutes' => 120,
            'status' => 'PENDING',
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);

        // Search by ID
        $response = $this->withSession(['admin_logged_in' => true])
            ->get(route('admin.dashboard', ['search' => 'ONE']));
        $response->assertStatus(200);
        $response->assertSee('TXN_SEARCHABLE_ONE');
        $response->assertDontSee('TXN_SEARCHABLE_TWO');

        // Search by Phone
        $response = $this->withSession(['admin_logged_in' => true])
            ->get(route('admin.dashboard', ['search' => '22222222']));
        $response->assertStatus(200);
        $response->assertDontSee('TXN_SEARCHABLE_ONE');
        $response->assertSee('TXN_SEARCHABLE_TWO');

        // Search by MAC
        $response = $this->withSession(['admin_logged_in' => true])
            ->get(route('admin.dashboard', ['search' => 'EE:F1']));
        $response->assertStatus(200);
        $response->assertSee('TXN_SEARCHABLE_ONE');
        $response->assertDontSee('TXN_SEARCHABLE_TWO');
    }

    public function test_router_status_heartbeat_online()
    {
        // Mock RouterClient
        $this->app->bind(RouterClient::class, function () {
            $mock = \Mockery::mock(RouterClient::class);
            $mock->shouldReceive('query')->with('/system/identity/print')->once()->andReturnSelf();
            $mock->shouldReceive('read')->once()->andReturn([['name' => 'MikroTik']]);

            return $mock;
        });

        $response = $this->withSession(['admin_logged_in' => true])
            ->get(route('admin.router_status'));

        $response->assertStatus(200);
        $response->assertJson(['online' => true]);
    }

    public function test_router_status_heartbeat_offline()
    {
        // Mock RouterClient to throw exception
        $this->app->bind(RouterClient::class, function () {
            $mock = \Mockery::mock(RouterClient::class);
            $mock->shouldReceive('query')->with('/system/identity/print')->once()->andThrow(new \Exception('Connection refused'));

            return $mock;
        });

        $response = $this->withSession(['admin_logged_in' => true])
            ->get(route('admin.router_status'));

        $response->assertStatus(200);
        $response->assertJson(['online' => false, 'error' => 'Connection refused']);
    }

    public function test_extend_transaction_updates_expiry_and_reprovisions_router_access()
    {
        $expiresAt = Carbon::now()->addHour()->seconds(0);
        $id = DB::table('hotspot_transactions')->insertGetId([
            'transaction_id' => 'TXN_EXTEND_REPROVISION',
            'mac_address' => 'AA:BB:CC:DD:EE:66',
            'phone_number' => '255766000000',
            'amount' => 1000,
            'speed_limit' => '5M/5M',
            'duration_minutes' => 60,
            'status' => 'SUCCESS',
            'expires_at' => $expiresAt,
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);

        $routerProvisioning = \Mockery::mock(RouterProvisioningService::class);
        $routerProvisioning->shouldReceive('provisionAccess')
            ->once()
            ->with(\Mockery::on(function ($transaction) {
                return $transaction->transaction_id === 'TXN_EXTEND_REPROVISION'
                    && $transaction->mac_address === 'AA:BB:CC:DD:EE:66';
            }), 'Admin Extend Txn');
        $this->app->instance(RouterProvisioningService::class, $routerProvisioning);

        $response = $this->withSession(['admin_logged_in' => true])
            ->post(route('admin.extend', $id), ['extend_hours' => 2]);

        $response->assertRedirect();
        $response->assertSessionHas('success', 'User package extended and router access restored successfully.');
        $this->assertDatabaseHas('hotspot_transactions', [
            'id' => $id,
            'expires_at' => $expiresAt->copy()->addHours(2)->toDateTimeString(),
        ]);
    }

    public function test_extend_transaction_reports_router_reprovision_failure()
    {
        $id = DB::table('hotspot_transactions')->insertGetId([
            'transaction_id' => 'TXN_EXTEND_ROUTER_FAIL',
            'mac_address' => 'AA:BB:CC:DD:EE:77',
            'phone_number' => '255777000000',
            'amount' => 1000,
            'speed_limit' => '5M/5M',
            'duration_minutes' => 60,
            'status' => 'SUCCESS',
            'expires_at' => Carbon::now()->addHour(),
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);

        $routerProvisioning = \Mockery::mock(RouterProvisioningService::class);
        $routerProvisioning->shouldReceive('provisionAccess')
            ->once()
            ->andThrow(new \RuntimeException('router login failed'));
        $this->app->instance(RouterProvisioningService::class, $routerProvisioning);

        $response = $this->withSession(['admin_logged_in' => true])
            ->post(route('admin.extend', $id), ['extend_hours' => 1]);

        $response->assertRedirect();
        $response->assertSessionHasErrors(['error']);
    }

    public function test_kick_transaction_removes_mikrotik_access_and_expires_transaction()
    {
        $id = DB::table('hotspot_transactions')->insertGetId([
            'transaction_id' => 'TXN_ADMIN_KICK',
            'mac_address' => 'AA:BB:CC:DD:EE:88',
            'phone_number' => '255788000000',
            'amount' => 1000,
            'speed_limit' => '5M/5M',
            'duration_minutes' => 60,
            'status' => 'SUCCESS',
            'expires_at' => Carbon::now()->addHour(),
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);

        $routerProvisioning = \Mockery::mock(RouterProvisioningService::class);
        $routerProvisioning->shouldReceive('removeMacAccess')
            ->once()
            ->with('AA:BB:CC:DD:EE:88', true);
        $this->app->instance(RouterProvisioningService::class, $routerProvisioning);

        $response = $this->withSession(['admin_logged_in' => true])
            ->post(route('admin.kick', $id));

        $response->assertRedirect();
        $response->assertSessionHas('success', 'User has been kicked out of MikroTik and marked expired.');
        $this->assertTrue(Carbon::parse(DB::table('hotspot_transactions')->where('id', $id)->value('expires_at'))->isPast());
    }

    public function test_kick_transaction_reports_mikrotik_cleanup_failure()
    {
        $id = DB::table('hotspot_transactions')->insertGetId([
            'transaction_id' => 'TXN_ADMIN_KICK_FAIL',
            'mac_address' => 'AA:BB:CC:DD:EE:99',
            'phone_number' => '255799000000',
            'amount' => 1000,
            'speed_limit' => '5M/5M',
            'duration_minutes' => 60,
            'status' => 'SUCCESS',
            'expires_at' => Carbon::now()->addHour(),
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);

        $routerProvisioning = \Mockery::mock(RouterProvisioningService::class);
        $routerProvisioning->shouldReceive('removeMacAccess')
            ->once()
            ->with('AA:BB:CC:DD:EE:99', true)
            ->andThrow(new \RuntimeException('router cleanup failed'));
        $this->app->instance(RouterProvisioningService::class, $routerProvisioning);

        $response = $this->withSession(['admin_logged_in' => true])
            ->post(route('admin.kick', $id));

        $response->assertRedirect();
        $response->assertSessionHasErrors(['error']);
    }

    public function test_live_active_sessions_list()
    {

        $this->app->bind(RouterClient::class, function () {
            $mock = \Mockery::mock(RouterClient::class);

            $mock->shouldReceive('query')->with('/ip/hotspot/active/print')->once()->andReturnSelf();
            $mock->shouldReceive('read')->once()->andReturn([
                [
                    '.id' => '*active1',
                    'user' => 'AA:BB:CC:DD:EE:11',
                    'mac-address' => 'AA:BB:CC:DD:EE:11',
                    'address' => '192.168.88.254',
                    'uptime' => '00:20:00',
                    'idle-time' => '00:05:00',
                    'rx-rate' => '54kbps',
                    'tx-rate' => '128kbps',
                    'bytes-in' => '512000',
                    'bytes-out' => '1048576',
                ],
            ]);

            $mock->shouldReceive('query')->with('/ip/hotspot/host/print')->once()->andReturnSelf();
            $mock->shouldReceive('read')->once()->andReturn([
                [
                    '.id' => '*host1',
                    'mac-address' => 'AA:BB:CC:DD:EE:11',
                    'address' => '192.168.88.254',
                ],
                [
                    '.id' => '*host2',
                    'mac-address' => 'AA:BB:CC:DD:EE:22',
                    'address' => '192.168.88.88',
                    'idle-time' => '00:01:00',
                    'bytes-in' => '1024',
                    'bytes-out' => '2048',
                ],
            ]);

            $mock->shouldReceive('query')->with('/ip/hotspot/ip-binding/print')->once()->andReturnSelf();
            $mock->shouldReceive('read')->once()->andReturn([
                [
                    '.id' => '*binding1',
                    'mac-address' => 'AA:BB:CC:DD:EE:11',
                    'address' => '192.168.88.254',
                    'type' => 'regular',
                    'comment' => 'Test active session comment',
                ],
                [
                    '.id' => '*binding2',
                    'mac-address' => 'AA:BB:CC:DD:EE:33',
                    'address' => '192.168.88.90',
                    'type' => 'bypassed',
                    'server' => 'hotspot1',
                    'comment' => 'Offline saved binding',
                ],
            ]);

            $mock->shouldReceive('query')->with('/ip/hotspot/user/print')->once()->andReturnSelf();
            $mock->shouldReceive('read')->once()->andReturn([
                [
                    '.id' => '*user1',
                    'name' => 'hs_aabbccddee11',
                    'mac-address' => 'AA:BB:CC:DD:EE:11',
                    'profile' => 'default',
                    'limit-uptime' => '01:00:00',
                    'uptime' => '00:20:00',
                    'bytes-in' => '512000',
                    'bytes-out' => '1048576',
                    'comment' => 'MikroTik user authenticated',
                ],
                [
                    '.id' => '*user2',
                    'name' => 'hs_aabbccddee44',
                    'profile' => 'default',
                    'limit-uptime' => '00:30:00',
                    'uptime' => '00:00:00',
                    'comment' => 'MikroTik user not authenticated',
                ],
            ]);

            $mock->shouldReceive('query')->with('/ip/dhcp-server/lease/print')->once()->andReturnSelf();
            $mock->shouldReceive('read')->once()->andReturn([
                [
                    '.id' => '*lease1',
                    'mac-address' => 'AA:BB:CC:DD:EE:55',
                    'address' => '192.168.88.2',
                    'host-name' => 'pharmacy-ap',
                    'status' => 'bound',
                    'dynamic' => 'true',
                    'last-seen' => '00:00:10',
                    'expires-after' => '00:09:50',
                    'comment' => 'DHCP AP lease',
                ],
            ]);

            $mock->shouldReceive('query')->with('/queue/simple/print')->once()->andReturnSelf();
            $mock->shouldReceive('read')->once()->andReturn([
                [
                    'name' => 'RateLimit_AA:BB:CC:DD:EE:11',
                    'bytes' => '2097152/4194304',
                ],
            ]);

            return $mock;
        });

        $response = $this->withSession(['admin_logged_in' => true])
            ->get(route('admin.active_sessions'));

        $response->assertStatus(200);
        $response->assertSee('Router Sessions & Bindings', false);
        $response->assertSee('Active Sessions');
        $response->assertSee('Hotspot Hosts');
        $response->assertSee('DHCP Leases');
        $response->assertSee('IP Bindings');
        $response->assertSee('Hotspot Users');
        $response->assertSee('AA:BB:CC:DD:EE:11');
        $response->assertSee('192.168.88.254');
        $response->assertSee('Authenticated');
        $response->assertSee('Host Seen');
        $response->assertSee('Has Binding');
        $response->assertSee('54kbps / 128kbps');
        $response->assertSee('00:20:00');
        $response->assertSee('500 KB');
        $response->assertSee('1 MB');
        $response->assertSee('2 MB');
        $response->assertSee('4 MB');
        $response->assertSee('Test active session comment');
        $response->assertSee('AA:BB:CC:DD:EE:22');
        $response->assertSee('192.168.88.88');
        $response->assertSee('Host Only');
        $response->assertSee('AA:BB:CC:DD:EE:33');
        $response->assertSee('Offline saved binding');
        $response->assertSee('Not Connected');
        $response->assertSee('192.168.88.2');
        $response->assertSee('pharmacy-ap');
        $response->assertSee('Bound');
        $response->assertSee('DHCP Only');
    }

    public function test_kick_active_session()
    {
        $this->app->bind(RouterClient::class, function () {
            $mock = \Mockery::mock(RouterClient::class);
            $mock->shouldReceive('query')->with(['/ip/hotspot/host/remove', '=.id=*1'])->once()->andReturnSelf();
            $mock->shouldReceive('read')->once()->andReturn([]);

            return $mock;
        });

        $response = $this->withSession(['admin_logged_in' => true])
            ->post(route('admin.active_sessions.kick', '*1'));

        $response->assertStatus(302); // Redirect back
        $response->assertSessionHas('success', 'Host connection removed successfully.');
    }

    public function test_conversion_rate_analytics()
    {
        // Unique visits: 2 unique devices (total 3 hits)
        DB::table('checkout_visits')->insert([
            'mac_address' => 'AA:BB:CC:DD:EE:01',
            'ip_address' => '192.168.88.2',
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);
        DB::table('checkout_visits')->insert([
            'mac_address' => 'AA:BB:CC:DD:EE:01',
            'ip_address' => '192.168.88.2',
            'created_at' => Carbon::now()->subMinute(),
            'updated_at' => Carbon::now()->subMinute(),
        ]);
        DB::table('checkout_visits')->insert([
            'mac_address' => 'AA:BB:CC:DD:EE:02',
            'ip_address' => '192.168.88.3',
            'created_at' => Carbon::now()->subDays(1),
            'updated_at' => Carbon::now()->subDays(1),
        ]);

        // Unique payers: 1 unique device paid (out of 2 visitor devices)
        DB::table('hotspot_transactions')->insert([
            'transaction_id' => 'TXN_CONV_1',
            'mac_address' => 'AA:BB:CC:DD:EE:01',
            'phone_number' => '255700000000',
            'amount' => 1000,
            'duration_minutes' => 60,
            'status' => 'SUCCESS',
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);

        // 1 payer / 2 unique visitors = 50% conversion rate
        $response = $this->withSession(['admin_logged_in' => true])
            ->get(route('admin.analytics'));

        $response->assertStatus(200);
        $response->assertSee('50%');
    }

    public function test_analytics_checkout_visits_are_grouped_by_mac_and_ip_with_history()
    {
        $oldVisit = Carbon::parse('2026-07-24 19:53:56');
        $latestVisit = Carbon::parse('2026-07-24 19:54:39');

        DB::table('checkout_visits')->insert([
            [
                'mac_address' => '9A:F6:08:17:9A:2E',
                'ip_address' => '192.168.88.164',
                'created_at' => $oldVisit,
                'updated_at' => $oldVisit,
            ],
            [
                'mac_address' => '9A:F6:08:17:9A:2E',
                'ip_address' => '192.168.88.164',
                'created_at' => $latestVisit,
                'updated_at' => $latestVisit,
            ],
            [
                'mac_address' => '78:62:56:C5:52:61',
                'ip_address' => '192.168.88.233',
                'created_at' => Carbon::parse('2026-07-24 19:36:34'),
                'updated_at' => Carbon::parse('2026-07-24 19:36:34'),
            ],
        ]);

        $response = $this->withSession(['admin_logged_in' => true])
            ->get(route('admin.analytics'));

        $response->assertStatus(200);
        $response->assertSee('Grouped by MAC and IP');
        $response->assertSee('2026-07-24 19:54:39');
        $response->assertSee('2026-07-24 19:53:56');
        $response->assertSee('9A:F6:08:17:9A:2E');
        $response->assertSee('192.168.88.164');
        $response->assertSeeInOrder(['2026-07-24 19:54:39', '9A:F6:08:17:9A:2E', '192.168.88.164', '2']);
    }

    public function test_analytics_uses_active_hotspot_users_for_router_usage_and_still_shows_hosts()
    {
        $this->app->bind(RouterClient::class, function () {
            $mock = \Mockery::mock(RouterClient::class);

            $mock->shouldReceive('query')->with('/ip/hotspot/active/print')->once()->andReturnSelf();
            $mock->shouldReceive('read')->once()->andReturn([
                [
                    'user' => 'AA:BB:CC:DD:EE:01',
                    'mac-address' => 'AA:BB:CC:DD:EE:01',
                    'bytes-in' => '1048576',
                    'bytes-out' => '1048576',
                ],
                [
                    'user' => 'AA:BB:CC:DD:EE:02',
                    'mac-address' => 'AA:BB:CC:DD:EE:02',
                    'bytes-in' => '524288',
                    'bytes-out' => '524288',
                ],
            ]);

            $mock->shouldReceive('query')->with('/ip/hotspot/host/print')->once()->andReturnSelf();
            $mock->shouldReceive('read')->once()->andReturn([
                ['mac-address' => 'AA:BB:CC:DD:EE:01'],
                ['mac-address' => 'AA:BB:CC:DD:EE:02'],
                ['mac-address' => 'AA:BB:CC:DD:EE:03'],
            ]);

            $mock->shouldReceive('query')->with('/queue/simple/print')->once()->andReturnSelf();
            $mock->shouldReceive('read')->once()->andReturn([
                ['name' => 'RateLimit_AA:BB:CC:DD:EE:01', 'bytes' => '10485760/10485760'],
            ]);

            return $mock;
        });

        $response = $this->withSession(['admin_logged_in' => true])
            ->get(route('admin.analytics'));

        $response->assertStatus(200);
        $response->assertSee('Active Hotspot Users');
        $response->assertSee('Connected Hosts');
        $response->assertSee('Hotspot active sessions');
        $response->assertSee('1.5 MB');
    }

    public function test_new_analytics_metrics()
    {
        // 1. Setup Package
        DB::table('packages')->insert([
            'name' => 'Super Fast 1 Hr',
            'duration_minutes' => 60,
            'price' => 1000,
            'is_active' => true,
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);

        // 2. Setup Checkout Visits (20:00 peak hour)
        DB::table('checkout_visits')->insert([
            'mac_address' => '11:22:33:44:55:66',
            'ip_address' => '192.168.88.10',
            'created_at' => Carbon::today()->setHour(20)->setMinute(15),
            'updated_at' => Carbon::now(),
        ]);
        DB::table('checkout_visits')->insert([
            'mac_address' => '11:22:33:44:55:66',
            'ip_address' => '192.168.88.10',
            'created_at' => Carbon::today()->setHour(20)->setMinute(30),
            'updated_at' => Carbon::now(),
        ]);
        // Abandoned visitor (visits, but never pays)
        DB::table('checkout_visits')->insert([
            'mac_address' => '99:88:77:66:55:44',
            'ip_address' => '192.168.88.99',
            'created_at' => Carbon::today()->setHour(14)->setMinute(10),
            'updated_at' => Carbon::now(),
        ]);

        // 3. Transactions (returning customer: MAC 11:22:33:44:55:66 has 2 payments today)
        DB::table('hotspot_transactions')->insert([
            'transaction_id' => 'TXN_ANALYTICS_1',
            'mac_address' => '11:22:33:44:55:66',
            'phone_number' => '255711111111',
            'amount' => 1000,
            'duration_minutes' => 60,
            'status' => 'SUCCESS',
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);
        DB::table('hotspot_transactions')->insert([
            'transaction_id' => 'TXN_ANALYTICS_2',
            'mac_address' => '11:22:33:44:55:66',
            'phone_number' => '255711111111',
            'amount' => 1000,
            'duration_minutes' => 60,
            'status' => 'SUCCESS',
            'created_at' => Carbon::now()->subHour(),
            'updated_at' => Carbon::now(),
        ]);

        $response = $this->withSession(['admin_logged_in' => true])
            ->get(route('admin.analytics'));

        $response->assertStatus(200);

        // Assert Revenue Today & This Week (2,000 TZS)
        $response->assertSee('2,000');

        // Assert Peak Hour (20:00 - 21:00)
        $response->assertSee('20:00 - 21:00');

        // Assert Most Popular Package
        $response->assertSee('Super Fast 1 Hr');

        // Assert Returning Customer count
        $response->assertSee('Returning Customers');

        // Assert Abandoned Checkout count & Section
        $response->assertSee('Abandoned Checkout');

        // Assert Data Used metric section
        $response->assertSee('Avg Data / Customer');
    }
}
