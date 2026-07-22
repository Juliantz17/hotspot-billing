<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Tests\TestCase;

class AdminDashboardFilterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
    }

    public function test_dashboard_filters_work()
    {
        // 1. Seed transactions
        // Transaction 1: Active, created today
        DB::table('hotspot_transactions')->insert([
            'transaction_id' => 'TXN_ACTIVE_TODAY',
            'mac_address' => '11:22:33:44:55:66',
            'phone_number' => '255700000001',
            'amount' => 1000,
            'duration_minutes' => 60,
            'status' => 'SUCCESS',
            'expires_at' => Carbon::now()->addHours(1),
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);

        // Transaction 2: Pending, created yesterday
        DB::table('hotspot_transactions')->insert([
            'transaction_id' => 'TXN_PENDING_YESTERDAY',
            'mac_address' => '22:33:44:55:66:77',
            'phone_number' => '255700000002',
            'amount' => 2000,
            'duration_minutes' => 120,
            'status' => 'PENDING',
            'expires_at' => null,
            'created_at' => Carbon::yesterday(),
            'updated_at' => Carbon::yesterday(),
        ]);

        // Transaction 3: Failed, created last week
        DB::table('hotspot_transactions')->insert([
            'transaction_id' => 'TXN_FAILED_LAST_WEEK',
            'mac_address' => '33:44:55:66:77:88',
            'phone_number' => '255700000003',
            'amount' => 3000,
            'duration_minutes' => 180,
            'status' => 'FAILED',
            'expires_at' => null,
            'created_at' => Carbon::now()->subWeek(),
            'updated_at' => Carbon::now()->subWeek(),
        ]);

        // Transaction 4: Expired, created 2 days ago (this week)
        DB::table('hotspot_transactions')->insert([
            'transaction_id' => 'TXN_EXPIRED_THIS_WEEK',
            'mac_address' => '44:55:66:77:88:99',
            'phone_number' => '255700000004',
            'amount' => 4000,
            'duration_minutes' => 240,
            'status' => 'SUCCESS',
            'expires_at' => Carbon::now()->subHours(1),
            'created_at' => Carbon::now()->subDays(2),
            'updated_at' => Carbon::now()->subDays(2),
        ]);

        // 2. Query dashboard with active filter
        $response = $this->withSession(['admin_logged_in' => true])
            ->get(route('admin.dashboard', ['status' => 'active']));

        $response->assertStatus(200);
        $response->assertSee('TXN_ACTIVE_TODAY');
        $response->assertDontSee('TXN_PENDING_YESTERDAY');
        $response->assertDontSee('TXN_FAILED_LAST_WEEK');
        $response->assertDontSee('TXN_EXPIRED_THIS_WEEK');

        // 3. Query dashboard with pending filter
        $response = $this->withSession(['admin_logged_in' => true])
            ->get(route('admin.dashboard', ['status' => 'pending']));

        $response->assertStatus(200);
        $response->assertDontSee('TXN_ACTIVE_TODAY');
        $response->assertSee('TXN_PENDING_YESTERDAY');
        $response->assertDontSee('TXN_FAILED_LAST_WEEK');
        $response->assertDontSee('TXN_EXPIRED_THIS_WEEK');

        // 4. Query dashboard with failed filter
        $response = $this->withSession(['admin_logged_in' => true])
            ->get(route('admin.dashboard', ['status' => 'failed']));

        $response->assertStatus(200);
        $response->assertDontSee('TXN_ACTIVE_TODAY');
        $response->assertDontSee('TXN_PENDING_YESTERDAY');
        $response->assertSee('TXN_FAILED_LAST_WEEK');
        $response->assertDontSee('TXN_EXPIRED_THIS_WEEK');

        // 5. Query dashboard with expired filter
        $response = $this->withSession(['admin_logged_in' => true])
            ->get(route('admin.dashboard', ['status' => 'expired']));

        $response->assertStatus(200);
        $response->assertDontSee('TXN_ACTIVE_TODAY');
        $response->assertDontSee('TXN_PENDING_YESTERDAY');
        $response->assertDontSee('TXN_FAILED_LAST_WEEK');
        $response->assertSee('TXN_EXPIRED_THIS_WEEK');

        // 6. Query dashboard with time filters
        // Today
        $response = $this->withSession(['admin_logged_in' => true])
            ->get(route('admin.dashboard', ['time' => 'today']));
        $response->assertSee('TXN_ACTIVE_TODAY');
        $response->assertDontSee('TXN_PENDING_YESTERDAY');

        // Yesterday
        $response = $this->withSession(['admin_logged_in' => true])
            ->get(route('admin.dashboard', ['time' => 'yesterday']));
        $response->assertDontSee('TXN_ACTIVE_TODAY');
        $response->assertSee('TXN_PENDING_YESTERDAY');

        // Combined: status active and time yesterday (should be empty)
        $response = $this->withSession(['admin_logged_in' => true])
            ->get(route('admin.dashboard', ['status' => 'active', 'time' => 'yesterday']));
        $response->assertDontSee('TXN_ACTIVE_TODAY');
        $response->assertDontSee('TXN_PENDING_YESTERDAY');
    }

    public function test_dashboard_displays_kpi_metrics_and_package_names()
    {
        // Create a package
        DB::table('packages')->insert([
            'name' => 'Kifurushi cha Saa 1',
            'duration_minutes' => 60,
            'price' => 500,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Insert a transaction matching this package
        DB::table('hotspot_transactions')->insert([
            'transaction_id' => 'TXN_PKG_TEST',
            'mac_address' => 'AA:BB:CC:DD:EE:FF',
            'phone_number' => '255712345678',
            'amount' => 500,
            'duration_minutes' => 60,
            'status' => 'SUCCESS',
            'expires_at' => Carbon::now()->addHour(),
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);

        $response = $this->withSession(['admin_logged_in' => true])
            ->get(route('admin.dashboard'));

        $response->assertStatus(200);
        $response->assertSee('Online Users');
        $response->assertSee('Revenue');
        $response->assertSee('Bandwidth');
        $response->assertSee('Internet Status');
        $response->assertSee('Router CPU');
        $response->assertSee('Router Memory');
        $response->assertSee('Expired Today');
        $response->assertSee('Kifurushi cha Saa 1');
    }

    public function test_live_metrics_endpoint_returns_json()
    {
        $response = $this->withSession(['admin_logged_in' => true])
            ->get(route('admin.live_metrics'));

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'online_users',
            'revenue_today',
            'revenue_today_formatted',
            'current_bandwidth',
            'internet_status',
            'router_cpu',
            'router_memory',
            'expired_sessions_today'
        ]);
    }
}
