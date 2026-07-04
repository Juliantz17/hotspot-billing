<?php

namespace Tests\Feature;

use App\Events\WifiPaymentSuccess;
use App\Listeners\ProvisionHotspotUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Log;
use Mockery\MockInterface;
use RouterOS\Client as RouterClient;
use Tests\TestCase;

class ProvisionHotspotUserTest extends TestCase
{
    /**
     * Test that a hotspot user is successfully provisioned when a payment succeeds.
     */
    public function test_it_provisions_user_on_mikrotik()
    {
        // 1. Arrange: Create a fake transaction object as it would come from the database
        $transaction = (object) [
            'transaction_id' => 'TXN-999',
            'mac_address' => 'AA:BB:CC:DD:EE:FF',
            'duration_minutes' => 60
        ];

        // Create the event that carries the transaction
        $event = new WifiPaymentSuccess($transaction);

        // 2. Mock the MikroTik RouterClient to intercept API calls
        // We use $this->app->bind instead of $this->mock because the listener passes parameters
        // to app(), which forces Laravel to build a new instance unless bound with a closure.
        $this->app->bind(RouterClient::class, function ($app, $params) use ($transaction) {
            $mock = \Mockery::mock(RouterClient::class);
            
            // We expect the 'query' method to be called exactly once
            $mock->shouldReceive('query')
                ->once()
                ->withArgs(function ($query) use ($transaction) {
                    if ($query[0] !== '/ip/hotspot/user/add') return false;
                    if (!in_array('=name=' . $transaction->mac_address, $query)) return false;
                    
                    return true;
                })
                ->andReturnSelf(); // The query() method returns the client itself for chaining
                
            // We expect the 'read' method to be called once to execute the query
            $mock->shouldReceive('read')
                ->once()
                ->andReturn([]); // Mock an empty response from the router
                
            return $mock;
        });

        // Optional: Suppress or check for log output during testing
        Log::shouldReceive('info')->andReturnNull()->byDefault();
        Log::shouldReceive('error')->andReturnNull()->byDefault();

        // 3. Act: Instantiate the listener and handle the event manually
        $listener = new ProvisionHotspotUser();
        $listener->handle($event);

        // 4. Assert: No exceptions were thrown, meaning our mocks intercepted the calls correctly
        $this->assertTrue(true);
    }
}
