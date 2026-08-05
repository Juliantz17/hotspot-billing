<?php

namespace App\Http\Controllers;

use App\Services\PaymentGatewayManager;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class PaymentWebhookController extends Controller
{
    public function __invoke(Request $request, string $gateway, PaymentGatewayManager $gateways): Response
    {
        $payload = $request->all();
        $now = now();
        $logId = DB::table('payment_webhook_logs')->insertGetId([
            'gateway' => $gateway,
            'order_id' => $this->firstValue($payload, ['order_id', 'utilityref', 'externalreference', 'externalId']),
            'gateway_transaction_id' => $this->firstValue($payload, ['transid', 'transaction_id', 'transactionId']),
            'gateway_reference' => $this->firstValue($payload, ['reference', 'utilityref', 'externalreference']),
            'payment_status' => $this->firstValue($payload, ['payment_status', 'transactionstatus', 'status', 'result']),
            'source_ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'payload' => empty($payload) ? null : json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'raw_body' => $request->getContent(),
            'received_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        try {
            $response = $gateways->gateway($gateway)->handleWebhook($request);

            DB::table('payment_webhook_logs')->where('id', $logId)->update([
                'response_status' => $response->getStatusCode(),
                'response_body' => $response->getContent(),
                'processed_at' => now(),
                'updated_at' => now(),
            ]);

            return $response;
        } catch (Throwable $e) {
            DB::table('payment_webhook_logs')->where('id', $logId)->update([
                'response_status' => 500,
                'processing_error' => $e->getMessage(),
                'processed_at' => now(),
                'updated_at' => now(),
            ]);

            Log::error('Payment webhook processing failed.', [
                'gateway' => $gateway,
                'webhook_log_id' => $logId,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    private function firstValue(array $payload, array $keys): ?string
    {
        foreach ($keys as $key) {
            $value = data_get($payload, $key);
            if (filled($value)) {
                return (string) $value;
            }
        }

        return null;
    }
}
