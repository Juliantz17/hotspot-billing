<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminRouterOfflineTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_renders_when_router_integration_is_disabled(): void
    {
        $this->app->instance('env', 'local');
        config(['services.mikrotik.enabled' => false]);

        $this->withSession(['admin_logged_in' => true])
            ->get('/admin')
            ->assertOk()
            ->assertSee('Router: Checking');
    }
}
