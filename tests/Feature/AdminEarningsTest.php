<?php

namespace Tests\Feature;

use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AdminEarningsTest extends TestCase
{
    use RefreshDatabase;

    public function test_earnings_can_be_grouped_by_phone(): void
    {
        $this->insertTransaction('PHONE-1', 'AA:AA:AA:AA:AA:01', '255700000001', 1000);
        $this->insertTransaction('PHONE-2', 'BB:BB:BB:BB:BB:02', '255700000001', 2500);
        $this->insertTransaction('PHONE-3', 'CC:CC:CC:CC:CC:03', '255700000002', 500);

        $response = $this->withSession(['admin_logged_in' => true])
            ->get(route('admin.earnings', ['filter' => 'all', 'group_by' => 'phone']));

        $response->assertOk();
        $response->assertSee('Customer Total Earnings by Phone');
        $response->assertSeeInOrder(['255700000001', '3,500', '255700000002', '500']);
    }

    public function test_earnings_can_be_grouped_by_mac_and_exclude_failed_payments(): void
    {
        $this->insertTransaction('MAC-1', 'AA:BB:CC:DD:EE:01', '255700000001', 1200);
        $this->insertTransaction('MAC-2', 'AA:BB:CC:DD:EE:01', '255700000002', 1800);
        $this->insertTransaction('MAC-FAILED', 'AA:BB:CC:DD:EE:01', '255700000003', 9000, 'FAILED');

        $response = $this->withSession(['admin_logged_in' => true])
            ->get(route('admin.earnings', ['filter' => 'all', 'group_by' => 'mac']));

        $response->assertOk();
        $response->assertSee('Customer Total Earnings by MAC Address');
        $response->assertSee('AA:BB:CC:DD:EE:01');
        $response->assertSee('3,000');
        $response->assertDontSee('9,000');
    }

    private function insertTransaction(
        string $transactionId,
        string $mac,
        string $phone,
        int $amount,
        string $status = 'SUCCESS'
    ): void {
        DB::table('hotspot_transactions')->insert([
            'transaction_id' => $transactionId,
            'mac_address' => $mac,
            'phone_number' => $phone,
            'amount' => $amount,
            'duration_minutes' => 60,
            'status' => $status,
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);
    }
}
