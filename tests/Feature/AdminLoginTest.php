<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminLoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_log_in_with_configured_credentials(): void
    {
        config([
            'admin.username' => 'configured-admin',
            'admin.password' => 'configured-secret',
        ]);

        $this->post(route('admin.login.submit'), [
            'username' => 'configured-admin',
            'password' => 'configured-secret',
        ])->assertRedirect(route('admin.dashboard'));

        $this->assertTrue(session()->get('admin_logged_in'));
    }

    public function test_login_fails_closed_when_admin_credentials_are_not_configured(): void
    {
        config([
            'admin.username' => null,
            'admin.password' => null,
        ]);

        $this->from(route('admin.login'))->post(route('admin.login.submit'), [
            'username' => 'admin',
            'password' => 'password',
        ])->assertRedirect(route('admin.login'))
            ->assertSessionHasErrors('error');

        $this->assertFalse(session()->has('admin_logged_in'));
    }

    public function test_logout_invalidates_admin_session(): void
    {
        $this->withSession(['admin_logged_in' => true])
            ->post(route('admin.logout'))
            ->assertRedirect(route('admin.login'))
            ->assertSessionMissing('admin_logged_in');
    }

    public function test_admin_login_is_rate_limited(): void
    {
        config([
            'admin.username' => 'configured-admin',
            'admin.password' => 'configured-secret',
        ]);

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->post(route('admin.login.submit'), [
                'username' => 'configured-admin',
                'password' => 'wrong-password',
            ])->assertRedirect();
        }

        $this->post(route('admin.login.submit'), [
            'username' => 'configured-admin',
            'password' => 'wrong-password',
        ])->assertTooManyRequests();
    }
}
