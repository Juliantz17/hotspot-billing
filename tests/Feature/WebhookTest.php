<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;
use App\Events\WifiPaymentSuccess;

class WebhookTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Create the necessary table schema for the test
        // Since we are using RefreshDatabase, migrations will run.
        // We assume the migration for hotspot_transactions exists.
        // Just in case it doesn't run properly in test env, we can 
        // quickly ensure it exists.
        if (!DB::getSchemaBuilder()->hasTable('hotspot_transactions')) {
            DB::getSchemaBuilder()->create('hotspot_transactions', function ($table) {
                $table->id();
                $table->string('transaction_id');
                $table->string('mac_address');
                $table->string('phone_number');
                $table->integer('amount');
                $table->integer('duration_minutes');
                $table->string('status');
                $table->timestamp('expires_at')->nullable();
                $table->timestamps();
            });
        }
    }

    public function test_webhook_fails_without_signature()
    {
        $response = $this->postJson('/webhook/selcom', [
            'order_id' => 'TXN_123',
            'payment_status' => 'SUCCESS',
        ]);

        $response->assertStatus(401);
    }

    public function test_webhook_fails_with_invalid_signature()
    {
        $response = $this->withHeaders([
            'X-Selcom-Signature' => 'invalid_signature',
            'X-Selcom-Timestamp' => now()->toIso8601String(),
        ])->postJson('/webhook/selcom', [
            'order_id' => 'TXN_123',
            'payment_status' => 'SUCCESS',
        ]);

        $response->assertStatus(401);
    }

    public function test_webhook_succeeds_with_valid_signature()
    {
        Event::fake();

        $transactionId = 'TXN_123';
        
        DB::table('hotspot_transactions')->insert([
            'transaction_id' => $transactionId,
            'mac_address' => '00:11:22:33:44:55',
            'phone_number' => '255700000000',
            'amount' => 500,
            'duration_minutes' => 60,
            'status' => 'PENDING',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $payload = [
            'result' => 'SUCCESS',
            'resultcode' => '000',
            'order_id' => $transactionId,
            'transid' => '7945454515',
            'reference' => '856266164161',
            'channel' => 'TIGOPESATZ',
            'amount' => '500',
            'phone' => '255700000000',
            'payment_status' => 'COMPLETED',
        ];

        $jsonData = json_encode($payload);
        $timestamp = now()->toIso8601String();
        $stringToSign = "timestamp=" . $timestamp . "&" . $jsonData;
        $signature = base64_encode(hash_hmac('sha256', $stringToSign, env('SELCOM_API_SECRET', 'test_secret'), true));

        // Override the env secret for the test
        config(['app.env' => 'testing']);
        putenv('SELCOM_API_SECRET=test_secret');

        $response = $this->withHeaders([
            'X-Selcom-Signature' => $signature,
            'X-Selcom-Timestamp' => $timestamp,
        ])->postJson('/webhook/selcom', $payload);

        $response->assertStatus(200);

        $txn = DB::table('hotspot_transactions')->where('transaction_id', $transactionId)->first();
        $this->assertEquals('SUCCESS', $txn->status);
        $this->assertNotNull($txn->expires_at);

        Event::assertDispatched(WifiPaymentSuccess::class, function ($event) use ($transactionId) {
            return $event->transaction->transaction_id === $transactionId;
        });
    }
}
