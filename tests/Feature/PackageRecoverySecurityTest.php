<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use RouterOS\Client as RouterClient;
use Tests\TestCase;

class PackageRecoverySecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_package_recovery_kicks_old_host_connections()
    {
        // 1. Seed active transaction
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

        // 2. Mock RouterClient
        $this->app->bind(RouterClient::class, function () {
            $mock = \Mockery::mock(RouterClient::class);

            // Mock print active sessions for old mac
            $mock->shouldReceive('query')
                ->with(['/ip/hotspot/active/print', '?mac-address=AA:BB:CC:DD:EE:11'])
                ->once()
                ->andReturnSelf();
            $mock->shouldReceive('read')->once()->andReturn([]);

            // Mock print hotspot users for old mac
            $mock->shouldReceive('query')
                ->with(['/ip/hotspot/user/print', '?name=AA:BB:CC:DD:EE:11'])
                ->once()
                ->andReturnSelf();
            $mock->shouldReceive('read')->once()->andReturn([]);

            // Mock print ip-binding for old mac
            $mock->shouldReceive('query')
                ->with(['/ip/hotspot/ip-binding/print', '?mac-address=AA:BB:CC:DD:EE:11'])
                ->once()
                ->andReturnSelf();
            $mock->shouldReceive('read')->once()->andReturn([['.id' => '*100']]);

            // Mock remove binding
            $mock->shouldReceive('query')
                ->with(['/ip/hotspot/ip-binding/remove', '=.id=*100'])
                ->once()
                ->andReturnSelf();
            $mock->shouldReceive('read')->once()->andReturn([]);

            // Mock print simple queues for old mac
            $mock->shouldReceive('query')
                ->with(['/queue/simple/print', '?name=RateLimit_AA:BB:CC:DD:EE:11'])
                ->once()
                ->andReturnSelf();
            $mock->shouldReceive('read')->once()->andReturn([]);

            // Mock print host connection for old mac (the new addition!)
            $mock->shouldReceive('query')
                ->with(['/ip/hotspot/host/print', '?mac-address=AA:BB:CC:DD:EE:11'])
                ->once()
                ->andReturnSelf();
            $mock->shouldReceive('read')->once()->andReturn([['.id' => '*500']]);

            // Mock remove host connection
            $mock->shouldReceive('query')
                ->with(['/ip/hotspot/host/remove', '=.id=*500'])
                ->once()
                ->andReturnSelf();
            $mock->shouldReceive('read')->once()->andReturn([]);

            // Mock cleaning/releasing sessions/users for the NEW mac
            $mock->shouldReceive('query')
                ->with(['/ip/hotspot/active/print', '?mac-address=AA:BB:CC:DD:EE:22'])
                ->once()
                ->andReturnSelf();
            $mock->shouldReceive('read')->once()->andReturn([]);

            $mock->shouldReceive('query')
                ->with(['/ip/hotspot/user/print', '?name=AA:BB:CC:DD:EE:22'])
                ->once()
                ->andReturnSelf();
            $mock->shouldReceive('read')->once()->andReturn([]);

            // Mock adding the new MAC as user
            $mock->shouldReceive('query')
                ->withArgs(function ($args) {
                    return is_array($args) && $args[0] === '/ip/hotspot/user/add' && in_array('=name=AA:BB:CC:DD:EE:22', $args);
                })
                ->once()
                ->andReturnSelf();
            $mock->shouldReceive('read')->once()->andReturn([]);

            // Mock bindings print and add for new mac
            $mock->shouldReceive('query')
                ->with(['/ip/hotspot/ip-binding/print', '?mac-address=AA:BB:CC:DD:EE:22'])
                ->once()
                ->andReturnSelf();
            $mock->shouldReceive('read')->once()->andReturn([]);

            $mock->shouldReceive('query')
                ->withArgs(function ($args) {
                    return is_array($args) && $args[0] === '/ip/hotspot/ip-binding/add' && in_array('=mac-address=AA:BB:CC:DD:EE:22', $args);
                })
                ->once()
                ->andReturnSelf();
            $mock->shouldReceive('read')->once()->andReturn([]);

            return $mock;
        });

        // 3. Trigger recovery request
        $response = $this->post(route('hotspot.recover_package'), [
            'phone' => '0712345678',
            'mac' => 'AA:BB:CC:DD:EE:22',
            'ip' => '192.168.88.5'
        ]);

        $response->assertStatus(200); // Renders reconnected template
        
        // 4. Assert that database is updated to the new MAC
        $this->assertDatabaseHas('hotspot_transactions', [
            'transaction_id' => 'TXN_RECOVER_123',
            'mac_address' => 'AA:BB:CC:DD:EE:22',
            'ip_address' => '192.168.88.5',
        ]);
    }
}
