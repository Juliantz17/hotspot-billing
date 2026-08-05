<?php

namespace Tests\Unit;

use App\Services\PaymentCompletionService;
use App\Services\Payments\AzamPayGateway;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AzamPayGatewayTest extends TestCase
{
    public function test_sandbox_checkout_does_not_require_x_api_key(): void
    {
        config([
            'services.azampay.app_name' => 'sandbox-app',
            'services.azampay.client_id' => 'sandbox-client',
            'services.azampay.client_secret' => 'sandbox-secret',
            'services.azampay.auth_url' => 'https://authenticator-sandbox.azampay.co.tz',
            'services.azampay.base_url' => 'https://sandbox.azampay.co.tz',
            'services.azampay.mno_checkout_path' => '/azampay/mno/checkout',
        ]);

        Http::fake([
            'https://authenticator-sandbox.azampay.co.tz/AppRegistration/GenerateToken' => Http::response(['data' => ['accessToken' => 'sandbox-token']]),
            'https://sandbox.azampay.co.tz/azampay/mno/checkout' => Http::response(['success' => true]),
        ]);

        $gateway = new AzamPayGateway($this->mock(PaymentCompletionService::class));
        $gateway->initiate((object) [
            'amount' => 1000,
            'phone_number' => '255712345678',
            'transaction_id' => 'HOTSPOT_SANDBOX_TEST',
        ]);

        Http::assertSentCount(2);
        Http::assertSent(fn ($request) => $request->url() === 'https://sandbox.azampay.co.tz/azampay/mno/checkout'
            && $request->hasHeader('Authorization', 'Bearer sandbox-token')
            && ! $request->hasHeader('X-API-KEY')
            && $request['externalId'] === 'HOTSPOT_SANDBOX_TEST'
            && $request['provider'] === 'Tigo');
    }
}
