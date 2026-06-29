<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class PackageSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        \App\Models\Package::create([
            'name' => '1 Hour',
            'duration_minutes' => 60,
            'price' => 500,
            'is_active' => true,
        ]);

        \App\Models\Package::create([
            'name' => '24 Hours',
            'duration_minutes' => 1440,
            'price' => 2000,
            'is_active' => true,
        ]);
    }
}
