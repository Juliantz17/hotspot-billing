<?php

namespace App\Services\Payments;

use App\Contracts\PaymentGateway;
use App\Services\PaymentCompletionService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AzamPayGateway implements PaymentGateway
{
    public function __construct(private PaymentCompletionService $completion) {}

    public function name(): string
    {
        return 'azampay';
    }

    public function initiate(object $transaction): void
    {
        $endpoint = rtrim(config('services.azampay.base_url'), '/').'/'.ltrim(config('services.azampay.mno_checkout_path'), '/');
        $payload = ['amount' => $transaction->amount, 'currency' => 'TZS', 'accountNumber' => $transaction->phone_number, 'externalId' => $transaction->transaction_id, 'provider' => $this->mobileProvider($transaction->phone_number)];
        $response = Http::withOptions($this->httpOptions())
            ->withToken($this->token())
            ->post($endpoint, $payload);
        if (! $response->successful()) {
            $message = $response->json('message') ?: $response->reason();
            throw new \RuntimeException("Azam Pay checkout failed ({$response->status()}): {$message}");
        }

        Log::info('Azam Pay order created.', [
            'transaction_id' => $transaction->transaction_id,
            'provider' => $payload['provider'],
            'amount' => $transaction->amount,
            'response_status' => $response->status(),
        ]);
    }

    public function checkStatus(object $transaction): ?string
    {
        return null; // Azam Pay confirms this checkout through its callback.
    }

    public function handleWebhook(Request $request): Response
    {
        $reference = collect([
            $request->input('utilityref'),
            $request->input('externalreference'),
            $request->input('reference'),
            $request->input('transid'),
        ])->first(fn ($value) => filled($value));
        $status = strtolower((string) $request->input('transactionstatus'));

        Log::info('Azam Pay callback received.', [
            'transaction_id' => $reference,
            'status' => $status,
        ]);

        if ($reference && in_array($status, ['success', 'failure'], true)) {
            $this->completion->apply((string) $reference, $status === 'success' ? 'SUCCESS' : 'FAILED', $this->name());
        }

        return response(['status' => 'ok']);
    }

    private function token(): string
    {
        foreach (['app_name', 'client_id', 'client_secret', 'base_url', 'auth_url'] as $key) {
            if (! config("services.azampay.{$key}")) {
                throw new \RuntimeException('Azam Pay is not configured.');
            }
        }

        $endpoint = rtrim(config('services.azampay.auth_url'), '/').'/AppRegistration/GenerateToken';
        $response = Http::withOptions($this->httpOptions())->post($endpoint, [
            'appName' => config('services.azampay.app_name'),
            'clientId' => config('services.azampay.client_id'),
            'clientSecret' => config('services.azampay.client_secret'),
        ]);
        $token = $response->json('data.accessToken');
        if (! $response->successful() || ! $token) {
            throw new \RuntimeException('Azam Pay authentication failed.');
        }

        return $token;
    }

    private function httpOptions(): array
    {
        $caBundle = config('services.azampay.ca_bundle');

        return $caBundle ? ['verify' => $caBundle] : [];
    }

    private function mobileProvider(string $phone): string
    {
        $local = str_starts_with($phone, '255') ? '0'.substr($phone, 3) : $phone;

        return match (substr($local, 0, 3)) {
            '062' => 'Halopesa',
            '065', '067', '071' => 'Tigo',
            '068', '069', '078' => 'Airtel',
            default => 'Mpesa',
        };
    }
}
