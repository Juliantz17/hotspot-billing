<?php

namespace App\Services;

use App\Models\Package;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class HotspotPaymentService
{
    public function __construct(private PaymentGatewayManager $gateways, private PaymentCompletionService $completion) {}

    public function initiate(int $packageId, string $phone, string $mac, string $ip = ''): string
    {
        $package = Package::findOrFail($packageId);
        if (! $package->is_active) {
            throw ValidationException::withMessages(['package_id' => 'This package is no longer available.']);
        }
        $transactionId = 'HOTSPOT_'.strtoupper((string) str()->ulid());
        $gateway = $this->gateways->active();
        DB::table('hotspot_transactions')->insert([
            'transaction_id' => $transactionId, 'payment_gateway' => $gateway->name(), 'package_id' => $package->id,
            'mac_address' => $mac, 'ip_address' => $ip, 'phone_number' => '255'.substr($phone, 1),
            'amount' => $package->price, 'duration_minutes' => $package->duration_minutes, 'speed_limit' => $package->speed_limit,
            'status' => 'PENDING', 'created_at' => now(), 'updated_at' => now(),
        ]);
        try {
            $gateway->initiate(DB::table('hotspot_transactions')->where('transaction_id', $transactionId)->first());
        } catch (\Throwable $e) {
            DB::table('hotspot_transactions')->where('transaction_id', $transactionId)->update(['status' => 'FAILED', 'updated_at' => now()]);
            throw $e;
        }

        return $transactionId;
    }

    public function status(string $transactionId): ?object
    {
        $transaction = DB::table('hotspot_transactions')->where('transaction_id', $transactionId)->first();
        if (! $transaction || $transaction->status !== 'PENDING') {
            return $transaction;
        }
        if ($transaction->created_at <= now()->subMinutes(2)->toDateTimeString()) {
            DB::table('hotspot_transactions')->where('id', $transaction->id)->update(['status' => 'FAILED', 'updated_at' => now()]);
            $transaction->status = 'FAILED';

            return $transaction;
        }
        try {
            $gateway = $this->gateways->gateway($transaction->payment_gateway ?? 'selcom');
            if ($status = $gateway->checkStatus($transaction)) {
                $this->completion->apply($transactionId, $status, $gateway->name());
                $transaction->status = $status;
            }
        } catch (\Throwable $e) {
            Log::warning('Payment status polling failed.', ['transaction_id' => $transactionId, 'error' => $e->getMessage()]);
        }

        return $transaction;
    }
}
