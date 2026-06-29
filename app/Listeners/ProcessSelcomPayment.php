<?php

namespace App\Listeners;

use Bryceandy\Selcom\Events\CheckoutWebhookReceived;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RouterOS\Config;
use RouterOS\Client;

class ProcessSelcomPayment
{
    /**
     * This method runs automatically whenever Selcom's background 
     * webhook confirms a payment transaction.
     */
    public function handle(CheckoutWebhookReceived $event)
    {
        // Extract the payload fields sent from Selcom's servers
        $payload = $event->data;

        if (isset($payload['payment_status']) && $payload['payment_status'] === 'SUCCESS') {
            
            $transactionId = $payload['transaction_id'];

            // 1. Fetch our matching pending tracking entry from the DB
            $localTxn = DB::table('hotspot_transactions')
                ->where('transaction_id', $transactionId)
                ->first();

            if ($localTxn && $localTxn->status === 'PENDING') {
                
                // 2. Mark payment complete and calculate the expiration timestamp
                DB::table('hotspot_transactions')
                    ->where('transaction_id', $transactionId)
                    ->update([
                        'status' => 'SUCCESS',
                        'expires_at' => now()->addMinutes($localTxn->duration_minutes),
                        'updated_at' => now()
                    ]);

                Log::info("Selcom payment success tracked for reference: " . $transactionId);

                // 3. Connect directly to your MikroTik Router using the API package
                try {
                    $config = (new Config())
                        ->set('host', env('MIKROTIK_HOST'))
                        ->set('user', env('MIKROTIK_USER'))
                        ->set('pass', env('MIKROTIK_PASS'))
                        ->set('port', 8728)
                        ->set('timeout', 5);

                    $routerClient = new Client($config);

                    // 4. Execute the command to dynamically whitelist this customer's MAC address.
                    $routerClient->query([
                        '/ip/hotspot/ip-binding/add',
                        '=mac-address=' . $localTxn->mac_address,
                        '=type=bypassed',
                        '=comment=Paid_' . $transactionId
                    ])->read();

                    Log::info("Device MAC [{$localTxn->mac_address}] bypassed on MikroTik successfully!");

                } catch (\Exception $e) {
                    Log::error("MikroTik Router API Error: " . $e->getMessage());
                }
            }
        }
    }
}