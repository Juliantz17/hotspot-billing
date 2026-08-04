<?php

namespace App\Services\Payments;

use App\Contracts\PaymentGateway;
use App\Services\PaymentCompletionService;
use Illuminate\Http\Client\Response as ClientResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Http;

class SelcomGateway implements PaymentGateway
{
    public function __construct(private PaymentCompletionService $completion) {}

    public function name(): string
    {
        return 'selcom';
    }

    public function initiate(object $transaction): void
    {
        $vendorTill = config('services.selcom.vendor_till');
        if (! $vendorTill) {
            throw new \RuntimeException('Selcom is not configured.');
        }

        $this->request('/v1/checkout/create-order-minimal', [
            'vendor' => $vendorTill,
            'order_id' => $transaction->transaction_id,
            'buyer_email' => 'customer@hotspot.net',
            'buyer_name' => 'Hotspot Customer',
            'buyer_phone' => $transaction->phone_number,
            'amount' => $transaction->amount,
            'currency' => 'TZS',
            'buyer_remarks' => 'WiFi Access',
            'merchant_remarks' => 'WiFi Access',
            'no_of_items' => 1,
            'webhook' => base64_encode(route('webhook.gateway', ['gateway' => $this->name()])),
        ]);

        $this->request('/v1/checkout/wallet-payment', [
            'transid' => 'TXN_'.uniqid(),
            'order_id' => $transaction->transaction_id,
            'msisdn' => $transaction->phone_number,
        ]);
    }

    public function checkStatus(object $transaction): ?string
    {
        $response = $this->request(
            '/v1/checkout/order-status?order_id='.$transaction->transaction_id,
            ['order_id' => $transaction->transaction_id],
            'GET'
        );
        $status = strtoupper((string) data_get($response->json(), 'data.0.payment_status'));

        return match (true) {
            in_array($status, ['COMPLETED', 'SUCCESS'], true) => 'SUCCESS',
            in_array($status, ['CANCELLED', 'USERCANCELED', 'USERCANCELLED', 'REJECTED', 'FAIL', 'FAILED'], true) => 'FAILED',
            default => null,
        };
    }

    public function handleWebhook(Request $request): Response
    {
        $signature = $request->header('X-Selcom-Signature');
        $timestamp = $request->header('X-Selcom-Timestamp');
        if (! $signature || ! $timestamp) {
            return response(['error' => 'Unauthorized'], 401);
        }

        $expected = base64_encode(hash_hmac(
            'sha256',
            'timestamp='.$timestamp.'&'.$request->getContent(),
            (string) config('services.selcom.api_secret'),
            true
        ));
        if (! hash_equals($expected, $signature)) {
            return response(['error' => 'Unauthorized'], 401);
        }

        $status = strtoupper((string) $request->input('payment_status'));
        $normalized = in_array($status, ['SUCCESS', 'COMPLETED'], true) ? 'SUCCESS'
            : (in_array($status, ['FAIL', 'FAILED', 'CANCELLED', 'USERCANCELED'], true) ? 'FAILED' : null);
        if ($normalized) {
            $this->completion->apply((string) $request->input('order_id'), $normalized, $this->name());
        }

        return response(['status' => 'SUCCESS', 'message' => 'Received']);
    }

    private function request(string $path, array $body, string $method = 'POST'): ClientResponse
    {
        $baseUrl = config('services.selcom.base_url');
        $secret = config('services.selcom.api_secret');
        $key = config('services.selcom.api_key');
        if (! $baseUrl || ! $secret || ! $key) {
            throw new \RuntimeException('Selcom is not configured.');
        }

        $timestamp = now()->toIso8601String();
        $fields = array_keys($body);
        $string = 'timestamp='.$timestamp;
        foreach ($fields as $field) {
            $string .= '&'.$field.'='.$body[$field];
        }
        $headers = [
            'Authorization' => 'SELCOM '.base64_encode($key),
            'Digest-Method' => 'HS256',
            'Digest' => base64_encode(hash_hmac('sha256', $string, $secret, true)),
            'Timestamp' => $timestamp,
            'Signed-Fields' => implode(',', $fields),
            'Accept' => 'application/json',
        ];

        $response = strtoupper($method) === 'GET'
            ? Http::withHeaders($headers)->get($baseUrl.$path)
            : Http::withHeaders($headers)->post($baseUrl.$path, $body);

        if (! $response->successful()) {
            throw new \RuntimeException("Selcom API request failed ({$response->status()}).");
        }

        return $response;
    }
}
