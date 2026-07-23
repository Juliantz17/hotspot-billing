<?php

namespace Tests\Feature;

use App\Services\RouterProvisioningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CleanExpiredHotspotUsersTest extends TestCase
{
    use RefreshDatabase;

    public function test_expired_cleanup_removes_router_access_and_marks_session_processed()
    {
        DB::table('hotspot_transactions')->insert([
            'transaction_id' => 'TXN_EXPIRED_CLEANUP',
            'mac_address' => 'AA:BB:CC:DD:EE:FF',
            'ip_address' => '192.168.88.20',
            'phone_number' => '255700000000',
            'amount' => 1000,
            'speed_limit' => '3M/3M',
            'duration_minutes' => 60,
            'status' => 'SUCCESS',
            'expires_at' => now()->subMinute(),
            'created_at' => now()->subHour(),
            'updated_at' => now()->subHour(),
        ]);

        $routerProvisioning = \Mockery::mock(RouterProvisioningService::class);
        $routerProvisioning->shouldReceive('removeMacAccess')
            ->once()
            ->with('AA:BB:CC:DD:EE:FF', true);

        $this->app->instance(RouterProvisioningService::class, $routerProvisioning);

        $this->artisan('hotspot:clean-expired')->assertExitCode(0);

        $this->assertDatabaseHas('hotspot_transactions', [
            'transaction_id' => 'TXN_EXPIRED_CLEANUP',
            'expires_at' => null,
        ]);
    }
}
