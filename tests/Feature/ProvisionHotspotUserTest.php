<?php

namespace Tests\Feature;

use App\Events\WifiPaymentSuccess;
use App\Listeners\ProvisionHotspotUser;
use App\Services\RouterProvisioningService;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class ProvisionHotspotUserTest extends TestCase
{
    public function test_it_delegates_paid_user_provisioning_to_router_service()
    {
        $transaction = (object) [
            'transaction_id' => 'TXN-999',
            'mac_address' => 'AA:BB:CC:DD:EE:FF',
            'duration_minutes' => 60,
        ];

        $routerProvisioning = \Mockery::mock(RouterProvisioningService::class);
        $routerProvisioning->shouldReceive('provisionAccess')
            ->once()
            ->with($transaction, 'Selcom Txn');

        $this->app->instance(RouterProvisioningService::class, $routerProvisioning);

        Log::shouldReceive('info')->once();
        Log::shouldReceive('error')->never();

        $listener = new ProvisionHotspotUser;
        $listener->handle(new WifiPaymentSuccess($transaction));
    }
}
