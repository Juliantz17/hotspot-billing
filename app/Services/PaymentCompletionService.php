<?php

namespace App\Services;

use App\Events\WifiPaymentSuccess;
use Illuminate\Support\Facades\DB;

class PaymentCompletionService
{
    public function apply(string $transactionId, string $status, string $gateway): void
    {
        DB::transaction(function () use ($transactionId, $status, $gateway) {
            $transaction = DB::table('hotspot_transactions')
                ->where('transaction_id', $transactionId)
                ->where('payment_gateway', $gateway)
                ->lockForUpdate()
                ->first();

            if (! $transaction || $transaction->status !== 'PENDING') {
                return;
            }

            if ($status === 'SUCCESS') {
                DB::table('hotspot_transactions')->where('id', $transaction->id)->update([
                    'status' => 'SUCCESS',
                    'expires_at' => now()->addMinutes($transaction->duration_minutes),
                    'updated_at' => now(),
                ]);
                event(new WifiPaymentSuccess($transaction));
            } elseif ($status === 'FAILED') {
                DB::table('hotspot_transactions')->where('id', $transaction->id)->update([
                    'status' => 'FAILED',
                    'updated_at' => now(),
                ]);
            }
        });
    }
}
