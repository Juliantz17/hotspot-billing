<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use RouterOS\Client as RouterClient;
use Tests\TestCase;

class RouterPanelTest extends TestCase
{
    use RefreshDatabase;

    public function test_router_panel_displays_router_health_snapshot_with_host_count()
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
            $mock->shouldReceive('read')->once()->andReturn([
                ['user' => 'AA:BB:CC:DD:EE:FF'],
            ]);

            $mock->shouldReceive('query')->with('/ip/hotspot/host/print')->once()->andReturnSelf();
            $mock->shouldReceive('read')->once()->andReturn([
                ['mac-address' => 'AA:BB:CC:DD:EE:FF'],
                ['mac-address' => '11:22:33:44:55:66'],
            ]);

            $mock->shouldReceive('query')->with('/queue/simple/print')->once()->andReturnSelf();
            $mock->shouldReceive('read')->once()->andReturn([['name' => 'RateLimit_AA:BB']]);

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
        $response->assertSee('Hosts');
        $response->assertSee('Queues');
        $response->assertDontSee('Active Hotspot Users');
        $response->assertDontSee('Simple Queues</h3>', false);
    }

    public function test_simple_queues_page_displays_queue_details()
    {
        $mock = \Mockery::mock(RouterClient::class);
        $queue = [
            '.id' => '*queue1',
            'name' => 'RateLimit_AA:BB',
            'target' => '192.168.88.23/32',
            'max-limit' => '3000000/3000000',
            'limit-at' => '0/0',
            'rate' => '133944/403088',
            'bytes' => '1024/2048',
            'packets' => '10/20',
            'disabled' => 'false',
            'comment' => 'Selcom Txn TXN_1',
        ];

        $mock->shouldReceive('query')->with('/queue/simple/print')->twice()->andReturnSelf();
        $mock->shouldReceive('query')->with(['/queue/simple/move', '=numbers=*queue1', '=destination=0'])->once()->andReturnSelf();
        $mock->shouldReceive('read')->andReturn([$queue], [], [$queue]);

        $this->app->bind(RouterClient::class, fn () => $mock);

        $response = $this->withSession(['admin_logged_in' => true])->get(route('admin.queues'));

        $response->assertStatus(200);
        $response->assertSee('RateLimit_AA:BB');
        $response->assertSee('192.168.88.23/32');
        $response->assertSee('3 Mbps / 3 Mbps');
        $response->assertSee('133.94 Kbps / 403.09 Kbps');
        $response->assertSee('Selcom Txn TXN_1');
    }

    public function test_mikrotik_logs_page_displays_latest_router_logs_newest_first()
    {
        $this->app->bind(RouterClient::class, function () {
            $mock = \Mockery::mock(RouterClient::class);
            $mock->shouldReceive('query')->with('/log/print')->once()->andReturnSelf();
            $mock->shouldReceive('read')->once()->andReturn([
                [
                    '.id' => '*1',
                    'time' => 'jul/23 10:10:00',
                    'topics' => 'system,info',
                    'message' => 'router rebooted',
                ],
                [
                    '.id' => '*2',
                    'time' => 'jul/23 10:11:00',
                    'topics' => 'hotspot,warning',
                    'message' => 'login failed for user AA:BB',
                ],
            ]);

            return $mock;
        });

        $response = $this->withSession(['admin_logged_in' => true])->get(route('admin.logs'));

        $response->assertStatus(200);
        $response->assertSee('RouterOS System Logs');
        $response->assertSee('hotspot,warning');
        $response->assertSee('Warning');
        $response->assertSee('login failed for user AA:BB');
        $response->assertSeeInOrder([
            'jul/23 10:11:00',
            'jul/23 10:10:00',
        ]);
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
