<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AdminWebhookLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_search_and_filter_webhook_audit_records(): void
    {
        DB::table('payment_webhook_logs')->insert([
            'gateway' => 'selcom', 'order_id' => 'HOTSPOT_AUDIT_1',
            'gateway_transaction_id' => 'SEL-TXN-1', 'gateway_reference' => 'SEL-REF-1',
            'payment_status' => 'SUCCESS', 'source_ip' => '127.0.0.1',
            'payload' => json_encode(['order_id' => 'HOTSPOT_AUDIT_1', 'token' => 'must-not-display']),
            'response_status' => 401, 'response_body' => '{"error":"Unauthorized"}',
            'received_at' => now(), 'processed_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);

        $response = $this->withSession(['admin_logged_in' => true])->get(route('admin.webhook_logs', [
            'search' => 'SEL-REF-1', 'gateway' => 'selcom', 'result' => 'rejected',
        ]));

        $response->assertOk()->assertSee('Payment Webhook Audit')->assertSee('HOTSPOT_AUDIT_1')->assertSee('Rejected')->assertSee('[redacted]')->assertDontSee('must-not-display');
    }

    public function test_webhook_audit_requires_admin_authentication(): void
    {
        $this->get(route('admin.webhook_logs'))->assertRedirect(route('admin.login'));
    }
}
