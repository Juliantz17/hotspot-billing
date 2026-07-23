<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use RouterOS\Client as RouterClient;
use Tests\TestCase;

class RouterPanelTest extends TestCase
{
    use RefreshDatabase;

    public function test_router_panel_displays_router_health_snapshot()
    {
        $this->app->bind(RouterClient::class, function () {
            $mock = \Mockery::mock(RouterClient::class);

            $mock->shouldReceive('query')->with('/system/identity/print')->once()->andReturnSelf();
            $mock->shouldReceive('read')->once()->andReturn([['name' => 'Main Hotspot']]);

            $mock->shouldReceive('query')->with('/system/resource/print')->once()->andReturnSelf();
            $mock->shouldReceive('read')->once()->andReturn([[
                'version' => '7.15.1',
                'uptime' => '1d2h3m',
                'cpu-load' => '12',
                'total-memory' => '1000000',
                'free-memory' => '750000',
            ]]);

            $mock->shouldReceive('query')->with('/ip/hotspot/active/print')->once()->andReturnSelf();
            $mock->shouldReceive('read')->once()->andReturn([[
                'user' => 'AA:BB',
                'address' => '192.168.88.23',
                'mac-address' => 'AA:BB:CC:DD:EE:FF',
                'uptime' => '10m',
                'idle-time' => '1m',
                'bytes-in' => '1024',
                'bytes-out' => '2048',
            ]]);

            $mock->shouldReceive('query')->with('/ip/hotspot/host/print')->once()->andReturnSelf();
            $mock->shouldReceive('read')->once()->andReturn([['mac-address' => 'AA:BB']]);

            $mock->shouldReceive('query')->with('/queue/simple/print')->once()->andReturnSelf();
            $mock->shouldReceive('read')->once()->andReturn([[
                'name' => 'RateLimit_AA:BB',
                'target' => '192.168.88.23/32',
                'max-limit' => '2M/2M',
                'rate' => '200k/500k',
                'bytes' => '1024/2048',
                'disabled' => 'false',
            ]]);

            $mock->shouldReceive('query')->with('/interface/print')->once()->andReturnSelf();
            $mock->shouldReceive('read')->once()->andReturn([[
                'name' => 'ether1',
                'type' => 'ether',
                'running' => 'true',
                'disabled' => 'false',
            ]]);

            return $mock;
        });

        $response = $this->withSession(['admin_logged_in' => true])->get(route('admin.router'));

        $response->assertStatus(200);
        $response->assertSee('Main Hotspot');
        $response->assertSee('1d2h3m');
        $response->assertSee('7.15.1');
        $response->assertSee('ether1');
        $response->assertSee('192.168.88.23');
        $response->assertSee('AA:BB:CC:DD:EE:FF');
        $response->assertSee('RateLimit_AA:BB');
        $response->assertSee('2M/2M');
    }

    public function test_router_snapshot_returns_offline_payload_when_router_is_unreachable()
    {
        $this->app->bind(RouterClient::class, function () {
            $mock = \Mockery::mock(RouterClient::class);
            $mock->shouldReceive('query')->with('/system/identity/print')->once()->andThrow(new \Exception('network unreachable'));

            return $mock;
        });

        $response = $this->withSession(['admin_logged_in' => true])->get(route('admin.router_snapshot'));

        $response->assertStatus(200);
        $response->assertJson([
            'online' => false,
            'error' => 'network unreachable',
        ]);
    }

    public function test_admin_can_send_router_reboot_command()
    {
        $this->app->bind(RouterClient::class, function () {
            $mock = \Mockery::mock(RouterClient::class);
            $mock->shouldReceive('query')->with('/system/reboot')->once()->andReturnSelf();
            $mock->shouldReceive('read')->once()->andReturn([]);

            return $mock;
        });

        $response = $this->withSession(['admin_logged_in' => true])->post(route('admin.router_reboot'));

        $response->assertRedirect();
        $response->assertSessionHas('success');
    }
}
