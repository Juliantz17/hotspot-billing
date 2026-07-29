<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CleanStalePendingTransactionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_deletes_only_pending_transactions_that_are_at_least_three_hours_old(): void
    {
        $this->insertTransaction('STALE_PENDING', 'PENDING', now()->subHours(3));
        $this->insertTransaction('FRESH_PENDING', 'PENDING', now()->subHours(2));
        $this->insertTransaction('OLD_SUCCESS', 'SUCCESS', now()->subHours(4));
        $this->insertTransaction('OLD_FAILED', 'FAILED', now()->subHours(4));

        $this->artisan('hotspot:clean-pending')->assertExitCode(0);

        $this->assertDatabaseMissing('hotspot_transactions', [
            'transaction_id' => 'STALE_PENDING',
        ]);
        $this->assertDatabaseHas('hotspot_transactions', [
            'transaction_id' => 'FRESH_PENDING',
        ]);
        $this->assertDatabaseHas('hotspot_transactions', [
            'transaction_id' => 'OLD_SUCCESS',
        ]);
        $this->assertDatabaseHas('hotspot_transactions', [
            'transaction_id' => 'OLD_FAILED',
        ]);
    }

    private function insertTransaction(string $transactionId, string $status, mixed $createdAt): void
    {
        DB::table('hotspot_transactions')->insert([
            'transaction_id' => $transactionId,
            'mac_address' => 'AA:BB:CC:DD:EE:FF',
            'ip_address' => '192.168.88.20',
            'phone_number' => '255700000000',
            'amount' => 1000,
            'speed_limit' => '3M/3M',
            'duration_minutes' => 60,
            'status' => $status,
            'expires_at' => null,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);
    }
}
