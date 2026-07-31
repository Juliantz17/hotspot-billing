<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class FupEnforcementService
{
    public function __construct(private readonly RouterProvisioningService $router) {}

    public function enforce(): array
    {
        $result = ['checked' => 0, 'throttled' => 0, 'restored' => 0];
        $snapshots = $this->router->fupQueueSnapshots();

        $transactions = DB::table('hotspot_transactions as transactions')
            ->join('packages', 'packages.id', '=', 'transactions.package_id')
            ->where('transactions.status', 'SUCCESS')
            ->where('transactions.expires_at', '>', now())
            ->select([
                'transactions.*',
                'packages.fup_enabled',
                'packages.fup_threshold_bytes',
                'packages.fup_speed_limit',
            ])
            ->get();

        foreach ($transactions as $transaction) {
            $result['checked']++;
            $macKey = strtolower($this->normalizeMac($transaction->mac_address));
            $snapshot = $snapshots[$macKey] ?? null;
            $fupEnabled = (bool) $transaction->fup_enabled
                && (int) $transaction->fup_threshold_bytes > 0
                && ! empty($transaction->fup_speed_limit);

            if (! $fupEnabled && $transaction->fup_applied_at === null) {
                continue;
            }

            $usage = (int) $transaction->usage_bytes;
            if ($fupEnabled && $snapshot !== null) {
                $currentCounter = (int) $snapshot['counter_bytes'];
                $previousCounter = $transaction->router_counter_bytes;
                $delta = $previousCounter === null
                    ? 0
                    : ($currentCounter >= (int) $previousCounter
                        ? $currentCounter - (int) $previousCounter
                        : $currentCounter);
                $usage += max(0, $delta);

                DB::table('hotspot_transactions')->where('id', $transaction->id)->update([
                    'usage_bytes' => $usage,
                    'router_counter_bytes' => $currentCounter,
                    'usage_checked_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $shouldThrottle = $fupEnabled && $usage >= (int) $transaction->fup_threshold_bytes;
            $desiredRate = $shouldThrottle ? $transaction->fup_speed_limit : $transaction->speed_limit;
            $ip = $snapshot['target'] ?? $transaction->ip_address;

            try {
                if ($snapshot === null || ! $this->ratesEquivalent($snapshot['max_limit'] ?? null, $desiredRate)) {
                    $this->router->setManagedQueueRate(
                        $transaction->mac_address,
                        $ip,
                        $desiredRate,
                        'Managed Txn '.$transaction->transaction_id
                    );
                }

                if ($shouldThrottle) {
                    if ($transaction->fup_applied_at === null) {
                        $result['throttled']++;
                    }

                    DB::table('hotspot_transactions')->where('id', $transaction->id)->update([
                        'fup_applied_at' => $transaction->fup_applied_at ?? now(),
                        'updated_at' => now(),
                    ]);
                } elseif ($transaction->fup_applied_at !== null) {
                    $result['restored']++;
                    DB::table('hotspot_transactions')->where('id', $transaction->id)->update([
                        'fup_applied_at' => null,
                        'updated_at' => now(),
                    ]);
                }
            } catch (\Throwable $e) {
                Log::warning('Could not apply managed package rate.', [
                    'transaction_id' => $transaction->transaction_id,
                    'mac' => $transaction->mac_address,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $result;
    }

    private function normalizeMac(string $mac): string
    {
        $compact = strtoupper(preg_replace('/[^a-fA-F0-9]/', '', $mac));

        return strlen($compact) === 12 ? implode(':', str_split($compact, 2)) : strtoupper($mac);
    }

    private function ratesEquivalent(?string $routerRate, ?string $desiredRate): bool
    {
        $desiredRate = trim((string) $desiredRate);
        if ($desiredRate === '') {
            $desiredRate = '0/0';
        } elseif (! str_contains($desiredRate, '/')) {
            $desiredRate .= '/'.$desiredRate;
        }

        return $this->ratePairInBits($routerRate) === $this->ratePairInBits($desiredRate);
    }

    private function ratePairInBits(?string $ratePair): ?array
    {
        $parts = explode('/', trim((string) $ratePair));
        if (count($parts) !== 2) {
            return null;
        }

        $values = [];
        foreach ($parts as $part) {
            if (preg_match('/^([0-9]+(?:\.[0-9]+)?)([kKmMgG]?)$/', trim($part), $matches) !== 1) {
                return null;
            }

            $multiplier = match (strtoupper($matches[2])) {
                'K' => 1000,
                'M' => 1000 * 1000,
                'G' => 1000 * 1000 * 1000,
                default => 1,
            };
            $values[] = (int) round((float) $matches[1] * $multiplier);
        }

        return $values;
    }
}
