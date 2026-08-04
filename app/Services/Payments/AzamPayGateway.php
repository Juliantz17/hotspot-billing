<?php

namespace App\Services\Payments;

use App\Contracts\PaymentGateway;
use App\Services\PaymentCompletionService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Http;

class AzamPayGateway implements PaymentGateway
{
    public function __construct(private PaymentCompletionService $completion) {}

    public function name(): string
    {
        return 'azampay';
    }

    public function initiate(object $transaction): void
    {
        $response = Http::withToken($this->token())
            ->withHeaders(['X-API-KEY' => config('services.azampay.x_api_key')])
            ->post(rtrim(config('services.azampay.base_url'), '/').'/checkout', [
                'amount' => $transaction->amount,
                'currency' => 'TZS',
                'accountNumber' => $transaction->phone_number,
                'externalId' => $transaction->transaction_id,
                'provider' => $this->mobileProvider($transaction->phone_number),
            ]);

        if (! $response->successful()) {
            throw new \RuntimeException("Azam Pay checkout failed ({$response->status()}).");
        }
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

        if ($reference && in_array($status, ['success', 'failure'], true)) {
            $this->completion->apply((string) $reference, $status === 'success' ? 'SUCCESS' : 'FAILED', $this->name());
        }

        return response(['status' => 'ok']);
    }

    private function token(): string
    {
        foreach (['app_name', 'client_id', 'client_secret', 'x_api_key', 'base_url', 'auth_url'] as $key) {
            if (! config("services.azampay.{$key}")) {
                throw new \RuntimeException('Azam Pay is not configured.');
            }
        }

        $response = Http::post(rtrim(config('services.azampay.auth_url'), '/').'/AppRegistration/GenerateToken', [
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

    private function mobileProvider(string $phone): string
    {
        $local = str_starts_with($phone, '255') ? '0'.substr($phone, 3) : $phone;

        return match (substr($local, 0, 3)) {
            '062' => 'HALOPESA',
            '065', '067', '071' => 'TIGOPESA',
            '068', '069', '078' => 'AIRTELMONEY',
            default => 'MPESA',
        };
    }
}
