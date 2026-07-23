<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PackageSpeedLimitTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_package_speed_limit_is_normalized_for_mikrotik()
    {
        $response = $this->withSession(['admin_logged_in' => true])
            ->post(route('admin.packages.store'), [
                'name' => 'Fast Hour',
                'duration_minutes' => 60,
                'price' => 1000,
                'is_active' => '1',
                'speed_limit' => '3M',
            ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('packages', [
            'name' => 'Fast Hour',
            'speed_limit' => '3M/3M',
        ]);
    }

    public function test_admin_package_rejects_invalid_speed_limit_format()
    {
        $response = $this->withSession(['admin_logged_in' => true])
            ->from(route('admin.packages'))
            ->post(route('admin.packages.store'), [
                'name' => 'Bad Speed',
                'duration_minutes' => 60,
                'price' => 1000,
                'is_active' => '1',
                'speed_limit' => '3 megabytes',
            ]);

        $response->assertRedirect(route('admin.packages'));
        $response->assertSessionHasErrors('speed_limit');

        $this->assertDatabaseMissing('packages', [
            'name' => 'Bad Speed',
        ]);
    }
}
