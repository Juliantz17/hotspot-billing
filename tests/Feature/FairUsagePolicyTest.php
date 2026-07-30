<?php

namespace Tests\Feature;

use App\Models\Package;
use App\Services\FupEnforcementService;
use App\Services\RouterProvisioningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\TestCase;

class FairUsagePolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_configure_a_private_fup_rule(): void
    {
        $response = $this->withSession(['admin_logged_in' => true])
            ->post(route('admin.packages.store'), [
                'name' => 'Private policy package',
                'duration_minutes' => 1440,
                'price' => 1000,
                'is_active' => '1',
                'fup_enabled' => '1',
                'fup_threshold_gb' => '5',
                'fup_speed_limit' => '64K',
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('packages', [
            'name' => 'Private policy package',
            'fup_enabled' => true,
            'fup_threshold_bytes' => 5 * 1024 * 1024 * 1024,
            'fup_speed_limit' => '64K/64K',
        ]);
    }

    public function test_unlimited_package_uses_default_slow_rate_when_fup_speed_is_blank(): void
    {
        $response = $this->withSession(['admin_logged_in' => true])
            ->post(route('admin.packages.store'), [
                'name' => 'Unlimited before FUP',
                'duration_minutes' => 1440,
                'price' => 1000,
                'is_active' => '1',
                'speed_limit' => '',
                'fup_enabled' => '1',
                'fup_threshold_gb' => '5',
                'fup_speed_limit' => '',
            ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('packages', [
            'name' => 'Unlimited before FUP',
            'speed_limit' => null,
            'fup_enabled' => true,
            'fup_speed_limit' => '64K/64K',
        ]);
    }

    public function test_usage_crossing_the_current_threshold_applies_the_current_slow_rate(): void
    {
        [$package, $transactionId] = $this->activeTransaction([
            'fup_threshold_bytes' => 1000,
            'fup_speed_limit' => '64K/64K',
        ], [
            'usage_bytes' => 900,
            'router_counter_bytes' => 5000,
        ]);

        $router = Mockery::mock(RouterProvisioningService::class);
        $router->shouldReceive('fupQueueSnapshots')->once()->andReturn([
            'aa:bb:cc:dd:ee:ff' => [
                'counter_bytes' => 5200,
                'target' => '192.168.88.10/32',
                'max_limit' => '5M/5M',
            ],
        ]);
        $router->shouldReceive('setManagedQueueRate')->once()->with(
            'AA:BB:CC:DD:EE:FF',
            '192.168.88.10/32',
            '64K/64K',
            'Managed Txn '.$transactionId
        );

        $result = (new FupEnforcementService($router))->enforce();

        $this->assertSame(1, $result['throttled']);
        $this->assertDatabaseHas('hotspot_transactions', [
            'transaction_id' => $transactionId,
            'usage_bytes' => 1100,
        ]);
        $this->assertNotNull(DB::table('hotspot_transactions')->where('transaction_id', $transactionId)->value('fup_applied_at'));
        $this->assertTrue($package->fresh()->fup_enabled);
    }

    public function test_router_counter_reset_adds_only_the_new_counter_value(): void
    {
        [, $transactionId] = $this->activeTransaction([
            'fup_threshold_bytes' => 10000,
        ], [
            'usage_bytes' => 3000,
            'router_counter_bytes' => 9000,
        ]);

        $router = Mockery::mock(RouterProvisioningService::class);
        $router->shouldReceive('fupQueueSnapshots')->once()->andReturn([
            'aa:bb:cc:dd:ee:ff' => [
                'counter_bytes' => 250,
                'target' => '192.168.88.10/32',
                'max_limit' => '5M/5M',
            ],
        ]);
        $router->shouldReceive('setManagedQueueRate')->once();

        (new FupEnforcementService($router))->enforce();

        $this->assertDatabaseHas('hotspot_transactions', [
            'transaction_id' => $transactionId,
            'usage_bytes' => 3250,
            'router_counter_bytes' => 250,
        ]);
    }

    public function test_disabling_a_live_rule_restores_normal_speed(): void
    {
        [$package, $transactionId] = $this->activeTransaction([], [
            'usage_bytes' => 5000,
            'router_counter_bytes' => 5000,
            'fup_applied_at' => now()->subMinute(),
            'speed_limit' => '5M/5M',
        ]);
        $package->update(['fup_enabled' => false]);

        $router = Mockery::mock(RouterProvisioningService::class);
        $router->shouldReceive('fupQueueSnapshots')->once()->andReturn([
            'aa:bb:cc:dd:ee:ff' => [
                'counter_bytes' => 5100,
                'target' => '192.168.88.10/32',
                'max_limit' => '64K/64K',
            ],
        ]);
        $router->shouldReceive('setManagedQueueRate')->once()->with(
            'AA:BB:CC:DD:EE:FF',
            '192.168.88.10/32',
            '5M/5M',
            'Managed Txn '.$transactionId
        );

        $result = (new FupEnforcementService($router))->enforce();

        $this->assertSame(1, $result['restored']);
        $this->assertNull(DB::table('hotspot_transactions')->where('transaction_id', $transactionId)->value('fup_applied_at'));
    }

    public function test_first_counter_read_sets_a_baseline_without_counting_old_queue_history(): void
    {
        [, $transactionId] = $this->activeTransaction([], [
            'usage_bytes' => 0,
            'router_counter_bytes' => null,
        ]);

        $router = Mockery::mock(RouterProvisioningService::class);
        $router->shouldReceive('fupQueueSnapshots')->once()->andReturn([
            'aa:bb:cc:dd:ee:ff' => [
                'counter_bytes' => 9 * 1024 * 1024 * 1024,
                'target' => '192.168.88.10/32',
                'max_limit' => '5M/5M',
            ],
        ]);
        $router->shouldReceive('setManagedQueueRate')->once();

        (new FupEnforcementService($router))->enforce();

        $this->assertDatabaseHas('hotspot_transactions', [
            'transaction_id' => $transactionId,
            'usage_bytes' => 0,
            'router_counter_bytes' => 9 * 1024 * 1024 * 1024,
        ]);
    }

    private function activeTransaction(array $packageOverrides = [], array $transactionOverrides = []): array
    {
        $package = Package::create(array_merge([
            'name' => 'One day',
            'duration_minutes' => 1440,
            'price' => 1000,
            'is_active' => true,
            'speed_limit' => '5M/5M',
            'fup_enabled' => true,
            'fup_threshold_bytes' => 4000,
            'fup_speed_limit' => '64K/64K',
        ], $packageOverrides));

        $transactionId = 'FUP_'.str_replace('.', '', uniqid('', true));
        DB::table('hotspot_transactions')->insert(array_merge([
            'transaction_id' => $transactionId,
            'package_id' => $package->id,
            'mac_address' => 'AA:BB:CC:DD:EE:FF',
            'ip_address' => '192.168.88.10',
            'phone_number' => '255700000000',
            'amount' => 1000,
            'speed_limit' => '5M/5M',
            'status' => 'SUCCESS',
            'duration_minutes' => 1440,
            'expires_at' => now()->addDay(),
            'usage_bytes' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ], $transactionOverrides));

        return [$package, $transactionId];
    }
}
