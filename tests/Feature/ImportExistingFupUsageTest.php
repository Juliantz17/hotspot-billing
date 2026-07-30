<?php

namespace Tests\Feature;

use App\Models\Package;
use App\Services\FupEnforcementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Mockery;
use RouterOS\Client as RouterClient;
use Tests\TestCase;

class ImportExistingFupUsageTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_import_an_existing_router_users_usage_and_apply_fup(): void
    {
        $package = Package::create([
            'name' => 'Unlimited day',
            'duration_minutes' => 1440,
            'price' => 1000,
            'is_active' => true,
            'speed_limit' => null,
            'fup_enabled' => true,
            'fup_threshold_bytes' => 5 * 1024 * 1024 * 1024,
            'fup_speed_limit' => '64K/64K',
        ]);

        DB::table('hotspot_transactions')->insert([
            'transaction_id' => 'LEGACY_13GB',
            'mac_address' => '7A:7E:77:E5:9D:13',
            'phone_number' => '255700000000',
            'amount' => 1000,
            'speed_limit' => null,
            'status' => 'SUCCESS',
            'duration_minutes' => 1440,
            'expires_at' => now()->addHour(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $router = Mockery::mock(RouterClient::class);
        $router->shouldReceive('query')->once()->with([
            '/ip/hotspot/user/print',
            '?name=hs_7a7e77e59d13',
        ])->andReturnSelf();
        $router->shouldReceive('read')->once()->andReturn([[
            'name' => 'hs_7a7e77e59d13',
            'mac-address' => '7A:7E:77:E5:9D:13',
            'bytes-in' => 594542592,
            'bytes-out' => 14774687498,
        ]]);
        $this->app->bind(RouterClient::class, fn () => $router);

        $fup = Mockery::mock(FupEnforcementService::class);
        $fup->shouldReceive('enforce')->once()->andReturn(['checked' => 1, 'throttled' => 1, 'restored' => 0]);
        $this->app->instance(FupEnforcementService::class, $fup);

        $response = $this->withSession(['admin_logged_in' => true])->post(
            route('admin.active_sessions.import_fup'),
            [
                'router_user' => 'hs_7a7e77e59d13',
                'mac' => '7A:7E:77:E5:9D:13',
            ]
        );

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $transaction = DB::table('hotspot_transactions')->where('transaction_id', 'LEGACY_13GB')->first();
        $this->assertSame($package->id, $transaction->package_id);
        $this->assertSame(15369230090, $transaction->usage_bytes);
    }

    public function test_import_refuses_an_ambiguous_legacy_package_match(): void
    {
        foreach (['Package A', 'Package B'] as $name) {
            Package::create([
                'name' => $name,
                'duration_minutes' => 1440,
                'price' => 1000,
                'is_active' => true,
                'fup_enabled' => true,
                'fup_threshold_bytes' => 5 * 1024 * 1024 * 1024,
                'fup_speed_limit' => '64K/64K',
            ]);
        }

        DB::table('hotspot_transactions')->insert([
            'transaction_id' => 'AMBIGUOUS',
            'mac_address' => '7A:7E:77:E5:9D:13',
            'phone_number' => '255700000000',
            'amount' => 1000,
            'status' => 'SUCCESS',
            'duration_minutes' => 1440,
            'expires_at' => now()->addHour(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $router = Mockery::mock(RouterClient::class);
        $router->shouldReceive('query')->once()->andReturnSelf();
        $router->shouldReceive('read')->once()->andReturn([[
            'mac-address' => '7A:7E:77:E5:9D:13',
            'bytes-in' => 1,
            'bytes-out' => 2,
        ]]);
        $this->app->bind(RouterClient::class, fn () => $router);

        $response = $this->withSession(['admin_logged_in' => true])->post(
            route('admin.active_sessions.import_fup'),
            ['router_user' => 'hs_7a7e77e59d13', 'mac' => '7A:7E:77:E5:9D:13']
        );

        $response->assertSessionHasErrors('error');
        $this->assertNull(DB::table('hotspot_transactions')->where('transaction_id', 'AMBIGUOUS')->value('package_id'));
    }
}
