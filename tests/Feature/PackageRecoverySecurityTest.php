<?php

namespace Tests\Feature;

use App\Services\RouterProvisioningService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PackageRecoverySecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_package_recovery_kicks_old_device_and_provisions_new_device()
    {
        DB::table('hotspot_transactions')->insert([
            'transaction_id' => 'TXN_RECOVER_123',
            'mac_address' => 'AA:BB:CC:DD:EE:11',
            'phone_number' => '255712345678',
            'amount' => 1000,
            'duration_minutes' => 60,
            'status' => 'SUCCESS',
            'expires_at' => Carbon::now()->addMinutes(30),
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);

        $routerProvisioning = \Mockery::mock(RouterProvisioningService::class);
        $routerProvisioning->shouldReceive('removeMacAccess')
            ->once()
            ->with('AA:BB:CC:DD:EE:11');
        $routerProvisioning->shouldReceive('removeLoginState')
            ->once()
            ->with('AA:BB:CC:DD:EE:22');
        $routerProvisioning->shouldReceive('provisionAccess')
            ->once()
            ->withArgs(function ($session, $commentPrefix) {
                return $commentPrefix === 'Recovered Txn'
                    && $session->transaction_id === 'TXN_RECOVER_123'
                    && $session->mac_address === 'AA:BB:CC:DD:EE:22'
                    && $session->ip_address === '192.168.88.5';
            });

        $this->app->instance(RouterProvisioningService::class, $routerProvisioning);

        $response = $this->post(route('hotspot.recover_package'), [
            'phone' => '0712345678',
            'mac' => 'AA:BB:CC:DD:EE:22',
            'ip' => '192.168.88.5',
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('hotspot_transactions', [
            'transaction_id' => 'TXN_RECOVER_123',
            'mac_address' => 'AA:BB:CC:DD:EE:22',
            'ip_address' => '192.168.88.5',
        ]);
    }
}
