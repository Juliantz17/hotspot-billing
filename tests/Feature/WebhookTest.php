<?php

namespace Tests\Feature;

use App\Events\WifiPaymentSuccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class WebhookTest extends TestCase
{
    use RefreshDatabase;

    private const API_KEY = 'test_api_key';
    private const API_SECRET = 'test_secret';
    private const SIGNED_FIELDS = 'transid,order_id,reference,result,resultcode,payment_status';

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'services.selcom.api_key' => self::API_KEY,
            'services.selcom.api_secret' => self::API_SECRET,
        ]);
    }

    public function test_webhook_fails_without_authentication_headers(): void
    {
        $this->postJson('/webhook/selcom', $this->payload('TXN_123'))
            ->assertUnauthorized()
            ->assertJson(['reason' => 'missing_authentication_headers']);
    }

    public function test_webhook_fails_with_invalid_digest(): void
    {
        $payload = $this->payload('TXN_123');
        $timestamp = now()->toIso8601String();

        $this->withHeaders($this->headers($payload, $timestamp, 'invalid-digest'))
            ->postJson('/webhook/selcom', $payload)
            ->assertUnauthorized()
            ->assertJson(['reason' => 'invalid_digest']);
    }

    public function test_webhook_rejects_a_digest_that_does_not_sign_critical_fields(): void
    {
        $payload = $this->payload('TXN_123');
        $timestamp = now()->toIso8601String();
        $signedFields = 'transid,reference,result,resultcode,payment_status';

        $this->withHeaders($this->headers($payload, $timestamp, null, $signedFields))
            ->postJson('/webhook/selcom', $payload)
            ->assertUnauthorized()
            ->assertJson(['reason' => 'invalid_signed_fields']);
    }

    public function test_verified_webhook_completes_transaction_without_status_polling(): void
    {
        Event::fake();
        $transactionId = 'TXN_123';

        DB::table('hotspot_transactions')->insert([
            'transaction_id' => $transactionId,
            'payment_gateway' => 'selcom',
            'mac_address' => '00:11:22:33:44:55',
            'phone_number' => '255700000000',
            'amount' => 500,
            'duration_minutes' => 60,
            'status' => 'PENDING',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $payload = $this->payload($transactionId);
        $timestamp = now()->toIso8601String();

        $this->withHeaders($this->headers($payload, $timestamp))
            ->postJson('/webhook/selcom', $payload)
            ->assertOk()
            ->assertJson(['status' => 'SUCCESS']);

        $transaction = DB::table('hotspot_transactions')->where('transaction_id', $transactionId)->first();
        $this->assertSame('SUCCESS', $transaction->status);
        $this->assertNotNull($transaction->expires_at);
        $webhookLog = DB::table('payment_webhook_logs')->where('order_id', $transactionId)->first();
        $this->assertSame(200, $webhookLog->response_status);
        $this->assertNotNull($webhookLog->processed_at);
        Event::assertDispatched(WifiPaymentSuccess::class, fn ($event) => $event->transaction->transaction_id === $transactionId);
    }

    private function payload(string $transactionId): array
    {
        return [
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
    }

    private function headers(array $payload, string $timestamp, ?string $digest = null, string $signedFields = self::SIGNED_FIELDS): array
    {
        if ($digest === null) {
            $signingString = 'timestamp='.$timestamp;
            foreach (explode(',', $signedFields) as $field) {
                $signingString .= '&'.$field.'='.$payload[$field];
            }
            $digest = base64_encode(hash_hmac('sha256', $signingString, self::API_SECRET, true));
        }

        return [
            'Authorization' => 'SELCOM '.base64_encode(self::API_KEY),
            'Digest-Method' => 'HS256',
            'Digest' => $digest,
            'Timestamp' => $timestamp,
            'Signed-Fields' => $signedFields,
        ];
    }
}
