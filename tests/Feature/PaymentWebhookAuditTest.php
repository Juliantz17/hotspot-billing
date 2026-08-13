<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentWebhookAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_rejected_selcom_webhook_is_recorded_before_validation(): void
    {
        config(['services.selcom.verify_webhook' => true]);

        $this->postJson('/webhook/selcom', [
            'order_id' => 'AUDIT-SELCOM-1',
            'transid' => 'GATEWAY-TXN-1',
            'reference' => 'GATEWAY-REF-1',
            'payment_status' => 'SUCCESS',
        ])->assertUnauthorized();

        $this->assertDatabaseHas('payment_webhook_logs', [
            'gateway' => 'selcom',
            'order_id' => 'AUDIT-SELCOM-1',
            'gateway_transaction_id' => 'GATEWAY-TXN-1',
            'gateway_reference' => 'GATEWAY-REF-1',
            'payment_status' => 'SUCCESS',
            'response_status' => 401,
        ]);
    }

    public function test_azam_webhook_is_recorded_by_the_shared_audit_layer(): void
    {
        $this->postJson('/webhook/azampay', [
            'utilityref' => 'AUDIT-AZAM-1',
            'transactionstatus' => 'success',
        ])->assertOk();

        $this->assertDatabaseHas('payment_webhook_logs', [
            'gateway' => 'azampay',
            'order_id' => 'AUDIT-AZAM-1',
            'gateway_reference' => 'AUDIT-AZAM-1',
            'payment_status' => 'success',
            'response_status' => 200,
        ]);
    }
}
