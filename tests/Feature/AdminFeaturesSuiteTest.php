<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
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

    public function test_live_active_sessions_list()
    {
        // Mock RouterClient active sessions output
        $this->app->bind(RouterClient::class, function () {
            $mock = \Mockery::mock(RouterClient::class);
            $mock->shouldReceive('query')->with('/ip/hotspot/active/print')->once()->andReturnSelf();
            $mock->shouldReceive('read')->once()->andReturn([
                [
                    '.id' => '*1',
                    'user' => 'AA:BB:CC:DD:EE:11',
                    'address' => '192.168.88.254',
                    'uptime' => '00:10:45',
                    'bytes-in' => '512000',
                    'bytes-out' => '1048576',
                    'comment' => 'Test active session comment'
                ]
            ]);
            return $mock;
        });

        $response = $this->withSession(['admin_logged_in' => true])
            ->get(route('admin.active_sessions'));

        $response->assertStatus(200);
        $response->assertSee('AA:BB:CC:DD:EE:11');
        $response->assertSee('192.168.88.254');
        $response->assertSee('00:10:45');
        $response->assertSee('500 KB'); 
        $response->assertSee('1 MB');   
        $response->assertSee('Test active session comment');
    }

    public function test_kick_active_session()
    {
        $this->app->bind(RouterClient::class, function () {
            $mock = \Mockery::mock(RouterClient::class);
            $mock->shouldReceive('query')->with(['/ip/hotspot/active/remove', '=.id=*1'])->once()->andReturnSelf();
            $mock->shouldReceive('read')->once()->andReturn([]);
            return $mock;
        });

        $response = $this->withSession(['admin_logged_in' => true])
            ->post(route('admin.active_sessions.kick', '*1'));

        $response->assertStatus(302); // Redirect back
        $response->assertSessionHas('success', 'Active session disconnected successfully.');
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
        $response->assertSee('3');   
        $response->assertSee('2');   
        $response->assertSee('1');   
    }
}
