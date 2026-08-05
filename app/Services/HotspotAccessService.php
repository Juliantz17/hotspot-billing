<?php

namespace App\Services;

use App\Models\Package;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class HotspotAccessService
{
    public function __construct(private RouterProvisioningService $router) {}

    public function checkout(string $mac, string $ip, bool $manual): array
    {
        $activeTransaction = null;
        if ($mac !== '00:00:00:00:00:00') {
            $this->recordVisit($mac, $ip);
            $activeTransaction = $this->activeTransactionForMac($mac);
            if ($activeTransaction && ! $manual && $this->provision($activeTransaction, $ip, 'Auto-Reconnect Txn')) {
                return ['reconnected' => true, 'expires_at' => $activeTransaction->expires_at];
            }
        }

        return ['reconnected' => false, 'mac' => $mac, 'ip' => $ip, 'packages' => Package::where('is_active', true)->get(), 'activeTxn' => $activeTransaction];
    }

    public function reconnect(string $mac, string $ip): array
    {
        $transaction = $this->activeTransactionForMac($mac);
        if (! $transaction) {
            return ['ok' => false, 'message' => 'Hakuna kifurushi kinachoendelea kwa simu hii.'];
        }

        return $this->provision($transaction, $ip, 'Reconnect Txn') ? ['ok' => true, 'message' => 'Umefanikiwa kuunganishwa tena. Unaweza kuendelea kutumia intaneti.'] : ['ok' => false, 'message' => 'Imeshindwa kuunganisha kwenye router.'];
    }

    public function recover(string $phone, string $newMac, string $ip): array
    {
        $transaction = DB::table('hotspot_transactions')->where('phone_number', '255'.substr($phone, 1))->where('status', 'SUCCESS')->where('expires_at', '>', now())->latest()->first();
        if (! $transaction) {
            return ['ok' => false, 'message' => 'Hakuna kifurushi kinachoendelea kwa namba hii ya simu.'];
        }
        try {
            $this->router->removeMacAccess($transaction->mac_address);
            $this->router->removeLoginState($newMac);
            DB::table('hotspot_transactions')->where('id', $transaction->id)->update(['mac_address' => $newMac, 'ip_address' => $ip, 'updated_at' => now()]);
            $transaction->mac_address = $newMac;
            $transaction->ip_address = $ip;
            $this->router->provisionAccess($transaction, 'Recovered Txn');

            return ['ok' => true, 'expires_at' => $transaction->expires_at];
        } catch (\Throwable $e) {
            Log::error('Package recovery failed.', ['error' => $e->getMessage()]);

            return ['ok' => false, 'message' => 'Imeshindwa kuunganisha kwenye router.'];
        }
    }

    private function activeTransactionForMac(string $mac): ?object
    {
        return DB::table('hotspot_transactions')->where('mac_address', $mac)->where('status', 'SUCCESS')->where('expires_at', '>', now())->latest()->first();
    }

    private function recordVisit(string $mac, string $ip): void
    {
        try {
            DB::table('checkout_visits')->insert(['mac_address' => $mac, 'ip_address' => $ip, 'created_at' => now(), 'updated_at' => now()]);
        } catch (\Throwable $e) {
            Log::error('Failed to log checkout visit.', ['error' => $e->getMessage()]);
        }
    }

    private function provision(object $transaction, string $ip, string $comment): bool
    {
        try {
            if (now()->diffInMinutes($transaction->expires_at) < 1) {
                return false;
            }
            $transaction->ip_address = $ip;
            $this->router->provisionAccess($transaction, $comment);

            return true;
        } catch (\Throwable $e) {
            Log::error('Hotspot provisioning failed.', ['transaction_id' => $transaction->transaction_id, 'error' => $e->getMessage()]);

            return false;
        }
    }
}
