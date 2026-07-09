<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class HotspotController extends Controller
{
    public function showCheckout(Request $request)
    {
        $mac = $request->query('mac', '00:00:00:00:00:00'); 
        $ip = $request->query('ip', '');
        
        if ($mac !== '00:00:00:00:00:00') {
            $activeTxn = DB::table('hotspot_transactions')
                ->where('mac_address', $mac)
                ->where('status', 'SUCCESS')
                ->where('expires_at', '>', now())
                ->latest()
                ->first();

            // Auto-reconnect seamlessly if they are active
            if ($activeTxn && !$request->has('manual')) {
                try {
                    $routerClient = \App\Services\MikrotikService::getClient();
                    $remainingMinutes = now()->diffInMinutes($activeTxn->expires_at);

                    if ($remainingMinutes > 0) {
                        try {
                            $users = $routerClient->query(['/ip/hotspot/user/print', '?name=' . $mac])->read();
                            foreach ($users as $u) {
                                $routerClient->query(['/ip/hotspot/user/remove', '=.id=' . $u['.id']])->read();
                            }
                        } catch (\Exception $e) {}

                        $query = [
                            '/ip/hotspot/user/add',
                            '=name=' . $mac,
                            '=password=' . $mac,
                            '=mac-address=' . $mac,
                            '=comment=Auto-Reconnect Txn ' . $activeTxn->transaction_id
                        ];

                        if (!empty($activeTxn->speed_limit)) {
                            $query[] = '=rate-limit=' . $activeTxn->speed_limit;
                        }

                        $routerClient->query($query)->read();

                        // Clear any existing active session to prevent "already active" errors
                        try {
                            $activeSessions = $routerClient->query(['/ip/hotspot/active/print', '?mac-address=' . $mac])->read();
                            foreach ($activeSessions as $as) {
                                $routerClient->query(['/ip/hotspot/active/remove', '=.id=' . $as['.id']])->read();
                            }
                        } catch (\Exception $e) {}

                        if (!empty($ip)) {
                            try {
                                $routerClient->query([
                                    '/ip/hotspot/active/login',
                                    '=user=' . $mac,
                                    '=password=' . $mac,
                                    '=ip=' . $ip,
                                    '=mac-address=' . $mac
                                ])->read();
                            } catch (\Exception $e) {
                                Log::error("MikroTik active login failed: " . $e->getMessage());
                            }
                        }

                        // Seamlessly return a success message instead of checkout
                        return response(view('reconnected', [
                            'expires_at' => $activeTxn->expires_at
                        ]));
                    }
                } catch (\Exception $e) {
                    Log::error("Auto-reconnect failed in showCheckout: " . $e->getMessage());
                    // Silently fall back to showing the checkout page with the manual button
                }
            }
        }

        $packages = \App\Models\Package::where('is_active', true)->get();
        return view('checkout', compact('mac', 'ip', 'packages', 'activeTxn'));
    }

    public function reconnectUser(Request $request)
    {
        $mac = $request->input('mac');
        $ip = $request->input('ip');

        $activeTxn = DB::table('hotspot_transactions')
            ->where('mac_address', $mac)
            ->where('status', 'SUCCESS')
            ->where('expires_at', '>', now())
            ->latest()
            ->first();

        if (!$activeTxn) {
            return back()->withErrors(['reconnect' => 'Hakuna kifurushi kinachoendelea kwa simu hii.']);
        }

        try {
            $routerClient = \App\Services\MikrotikService::getClient();

            $remainingMinutes = now()->diffInMinutes($activeTxn->expires_at);
            if ($remainingMinutes < 1) {
                return back()->withErrors(['reconnect' => 'Kifurushi chako kimeisha.']);
            }
            
            // Remove existing user to avoid duplicate errors
            try {
                $users = $routerClient->query(['/ip/hotspot/user/print', '?name=' . $mac])->read();
                foreach ($users as $u) {
                    $routerClient->query(['/ip/hotspot/user/remove', '=.id=' . $u['.id']])->read();
                }
            } catch (\Exception $e) {}

            $query = [
                '/ip/hotspot/user/add',
                '=name=' . $mac,
                '=password=' . $mac,
                '=mac-address=' . $mac,
                '=comment=Reconnect Txn ' . $activeTxn->transaction_id
            ];

            if (!empty($activeTxn->speed_limit)) {
                $query[] = '=rate-limit=' . $activeTxn->speed_limit;
            }

            $routerClient->query($query)->read();

            // Try active login
            if (!empty($ip)) {
                try {
                    $routerClient->query([
                        '/ip/hotspot/active/login',
                        '=user=' . $mac,
                        '=password=' . $mac,
                        '=ip=' . $ip,
                        '=mac-address=' . $mac
                    ])->read();
                } catch (\Exception $e) {}
            }

            return back()->with('success', 'Umefanikiwa kuunganishwa tena. Unaweza kuendelea kutumia intaneti.');

        } catch (\Exception $e) {
            Log::error("User reconnect failed: " . $e->getMessage());
            return back()->withErrors(['reconnect' => 'Imeshindwa kuunganisha kwenye router.']);
        }
    }

    public function recoverPackage(Request $request)
    {
        $request->validate([
            'phone' => 'required|regex:/^0[67][0-9]{8}$/',
            'mac' => 'required'
        ]);

        $formattedPhone = '255' . substr($request->phone, 1);
        $newMac = $request->input('mac');
        $ip = $request->input('ip', '');

        // Find the active transaction for this phone
        $activeTxn = DB::table('hotspot_transactions')
            ->where('phone_number', $formattedPhone)
            ->where('status', 'SUCCESS')
            ->where('expires_at', '>', now())
            ->latest()
            ->first();

        if (!$activeTxn) {
            return back()->withErrors(['recover' => 'Hakuna kifurushi kinachoendelea kwa namba hii ya simu.']);
        }

        try {
            $routerClient = \App\Services\MikrotikService::getClient();

            $remainingMinutes = now()->diffInMinutes($activeTxn->expires_at);
            if ($remainingMinutes < 1) {
                return back()->withErrors(['recover' => 'Kifurushi chako kimeisha.']);
            }

            // 1. Kick the old MAC address to prevent connection sharing
            $oldMac = $activeTxn->mac_address;
            try {
                // Remove from active
                $activeSessions = $routerClient->query(['/ip/hotspot/active/print', '?mac-address=' . $oldMac])->read();
                foreach ($activeSessions as $as) {
                    $routerClient->query(['/ip/hotspot/active/remove', '=.id=' . $as['.id']])->read();
                }
                // Remove from users
                $users = $routerClient->query(['/ip/hotspot/user/print', '?name=' . $oldMac])->read();
                foreach ($users as $u) {
                    $routerClient->query(['/ip/hotspot/user/remove', '=.id=' . $u['.id']])->read();
                }
            } catch (\Exception $e) {}

            // 2. Clear any existing session for the NEW MAC just in case
            try {
                $activeSessions = $routerClient->query(['/ip/hotspot/active/print', '?mac-address=' . $newMac])->read();
                foreach ($activeSessions as $as) {
                    $routerClient->query(['/ip/hotspot/active/remove', '=.id=' . $as['.id']])->read();
                }
                $users = $routerClient->query(['/ip/hotspot/user/print', '?name=' . $newMac])->read();
                foreach ($users as $u) {
                    $routerClient->query(['/ip/hotspot/user/remove', '=.id=' . $u['.id']])->read();
                }
            } catch (\Exception $e) {}

            // 3. Add the new MAC to users
            $query = [
                '/ip/hotspot/user/add',
                '=name=' . $newMac,
                '=password=' . $newMac,
                '=mac-address=' . $newMac,
                '=comment=Recovered Txn ' . $activeTxn->transaction_id
            ];

            if (!empty($activeTxn->speed_limit)) {
                $query[] = '=rate-limit=' . $activeTxn->speed_limit;
            }

            $routerClient->query($query)->read();

            // 4. Update the database to point to the new MAC
            DB::table('hotspot_transactions')
                ->where('id', $activeTxn->id)
                ->update([
                    'mac_address' => $newMac,
                    'ip_address' => $ip,
                    'updated_at' => now()
                ]);

            // 5. Try active login
            if (!empty($ip)) {
                try {
                    $routerClient->query([
                        '/ip/hotspot/active/login',
                        '=user=' . $newMac,
                        '=password=' . $newMac,
                        '=ip=' . $ip,
                        '=mac-address=' . $newMac
                    ])->read();
                } catch (\Exception $e) {}
            }

            // Return success view
            return response(view('reconnected', [
                'expires_at' => $activeTxn->expires_at
            ]));

        } catch (\Exception $e) {
            Log::error("Package recovery failed: " . $e->getMessage());
            return back()->withErrors(['recover' => 'Imeshindwa kuunganisha kwenye router.']);
        }
    }

    public function showWaiting($txn)
    {
        Log::info("--- WAITING PAGE ACCESSED FOR $txn ---");
        
        $transaction = DB::table('hotspot_transactions')->where('transaction_id', $txn)->first();
        if (!$transaction) {
            return redirect()->route('hotspot.checkout');
        }

        if ($transaction->status === 'PENDING') {
            // Use string comparison to avoid any timezone parsing offsets
            $timeoutThreshold = now()->subMinutes(2)->toDateTimeString();
            
            if ($transaction->created_at <= $timeoutThreshold) {
                Log::info("Transaction $txn timed out.");
                // Time out after 2 minutes of waiting
                DB::table('hotspot_transactions')
                    ->where('id', $transaction->id)
                    ->update(['status' => 'FAILED', 'updated_at' => now()]);
                
                $transaction->status = 'FAILED';
            } else {
                // Actively poll Selcom for the latest order status
                try {
                    $statusPath = '/v1/checkout/order-status?order_id=' . $txn;
                    $statusResponse = $this->sendSelcomRequest($statusPath, ['order_id' => $txn], 'GET');
                    
                    if ($statusResponse->successful()) {
                        $responseData = $statusResponse->json();
                        
                        // Selcom returns status inside the data array: "data": [{"payment_status": "..."}]
                        if (!empty($responseData['data']) && isset($responseData['data'][0]['payment_status'])) {
                            $paymentStatus = strtoupper($responseData['data'][0]['payment_status']);
                            
                            if (in_array($paymentStatus, ['COMPLETED', 'SUCCESS'])) {
                                DB::transaction(function () use ($transaction, $txn) {
                                    DB::table('hotspot_transactions')
                                        ->where('transaction_id', $txn)
                                        ->update([
                                            'status' => 'SUCCESS', 
                                            'expires_at' => now()->addMinutes($transaction->duration_minutes),
                                            'updated_at' => now()
                                        ]);
                                });
                                $transaction->status = 'SUCCESS';
                                event(new \App\Events\WifiPaymentSuccess($transaction));
                            } elseif (in_array($paymentStatus, ['CANCELLED', 'USERCANCELED', 'USERCANCELLED', 'REJECTED', 'FAIL', 'FAILED'])) {
                                DB::table('hotspot_transactions')
                                    ->where('transaction_id', $txn)
                                    ->update(['status' => 'FAILED', 'updated_at' => now()]);
                                $transaction->status = 'FAILED';
                            }
                        }
                    }
                } catch (\Exception $e) {
                    // Silently ignore connection errors here and keep polling
                    Log::error("Failed to poll Selcom status for $txn: " . $e->getMessage());
                }
            }
        }

        return view('waiting', [
            'txn' => $txn, 
            'status' => $transaction->status,
            'mac' => $transaction->mac_address,
            'ip' => $transaction->ip_address
        ]);
    }

    /**
     * Handle Form Submission & Process both Selcom API calls
     */
    public function processPayment(Request $request)
    {
        $request->validate([
            'phone' => 'required|regex:/^0[67][0-9]{8}$/', 
            'package_id' => 'required|exists:packages,id',
            'mac' => 'required'
        ]);

        $package = \App\Models\Package::findOrFail($request->package_id);
        if (!$package->is_active) {
            return back()->withErrors(['package_id' => 'This package is no longer available.']);
        }

        $duration = $package->duration_minutes;
        $amount = $package->price;
        
        $formattedPhone = '255' . substr($request->phone, 1);
        $transactionId = 'HOTSPOT_' . time();

        // 1. Log the transaction as PENDING locally
        DB::table('hotspot_transactions')->insert([
            'transaction_id' => $transactionId,
            'mac_address' => $request->mac,
            'ip_address' => $request->ip,
            'phone_number' => $formattedPhone,
            'amount' => $amount,
            'duration_minutes' => $duration,
            'speed_limit' => $package->speed_limit,
            'status' => 'PENDING',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        try {
            // ========================================================
            // STEP 1: CREATE ORDER MINIMAL
            // ========================================================
            $vendorTill = config('services.selcom.vendor_till');
            if (empty($vendorTill)) {
                throw new \Exception('Configuration Error: SELCOM_VENDOR_TILL is missing or config is cached.');
            }

            $orderBody = [
                'vendor' => $vendorTill,
                'order_id' => $transactionId,
                'buyer_email' => 'customer@hotspot.net',
                'buyer_name' => 'Hotspot Customer',
                'buyer_phone' => $formattedPhone,
                'amount' => $amount,
                'currency' => 'TZS',
                'buyer_remarks' => 'WiFi Access',
                'merchant_remarks' => 'WiFi Access',
                'no_of_items' => 1,
                'webhook' => base64_encode(route('webhook.selcom'))
            ];

            $orderResponse = $this->sendSelcomRequest('/v1/checkout/create-order-minimal', $orderBody);

            // ========================================================
            // STEP 2: WALLET PULL PAYMENT (Triggers USSD STK Push)
            // ========================================================
            $walletBody = [
                'transid' => 'TXN_' . uniqid(),
                'order_id' => $transactionId,
                'msisdn' => $formattedPhone
            ];

            $walletResponse = $this->sendSelcomRequest('/v1/checkout/wallet-payment', $walletBody);

            // Successfully triggered both calls! Redirect to the waiting UI (Post/Redirect/Get pattern)
            return redirect()->route('hotspot.waiting', ['txn' => $transactionId]);

        } catch (\Exception $e) {
            // Clean up DB tracking entry on failure
            DB::table('hotspot_transactions')->where('transaction_id', $transactionId)->delete();
            return back()->withErrors(['phone' => $e->getMessage()]);
        }
    }

    /**
     * Dynamic Helper to compute custom signatures and execute calls natively
     */
    private function sendSelcomRequest(string $path, array $body = [], string $method = 'POST')
    {
        $baseUrl = config('services.selcom.base_url');
        $apiSecret = config('services.selcom.api_secret');
        $apiKey = config('services.selcom.api_key');

        if (empty($baseUrl) || empty($apiSecret) || empty($apiKey)) {
            throw new \Exception("Configuration Error: SELCOM_BASE_URL, SELCOM_API_SECRET, or SELCOM_API_KEY is missing. If they are in your .env, try running: php artisan config:clear");
        }

        $timestamp = now()->toIso8601String();
        
        // Extract fields for Signed-Fields
        $signedFieldsArray = array_keys($body);
        $signedFields = implode(',', $signedFieldsArray);
        
        // Build signing string according to Selcom docs: timestamp=<val>&field1=<val>&field2=<val>
        $stringToSign = "timestamp=" . $timestamp;
        foreach ($signedFieldsArray as $key) {
            // Values must match request payload exactly (no urlencoding unless specified)
            $stringToSign .= "&" . $key . "=" . $body[$key];
        }
        
        $signature = base64_encode(hash_hmac('sha256', $stringToSign, $apiSecret, true));
        $authToken = base64_encode($apiKey);

        $headers = [
            'Authorization' => 'SELCOM ' . $authToken,
            'Digest-Method' => 'HS256',
            'Digest' => $signature,
            'Timestamp' => $timestamp,
            'Signed-Fields' => $signedFields,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json'
        ];

        $debugInfo = json_encode([
            'url' => $baseUrl . $path,
            'stringToSign' => $stringToSign,
            'generatedSignature' => $signature,
            'signedFields' => $signedFields,
            'hasApiKey' => 'YES',
            'hasApiSecret' => 'YES',
            'headers' => $headers,
        ]);

        if (strtoupper($method) === 'GET') {
            $response = Http::withHeaders($headers)->get($baseUrl . $path);
        } else {
            $response = Http::withHeaders($headers)->post($baseUrl . $path, $body);
        }
        
        if (!$response->successful()) {
            // Log safely to server's error log (e.g. Nginx/Apache error.log) and Laravel log
            error_log("SELCOM DEBUG INFO: " . $debugInfo);
            Log::error("SELCOM DEBUG INFO: " . $debugInfo);
            
            throw new \Exception("Selcom API Failed on $path. Check server error logs for details.");
        }
        
        return $response;
    }

    /**
     * 3. Selcom Webhook Listener Endpoint
     */
    public function handleWebhook(Request $request)
    {
        $jsonData = $request->getContent();
        
        Log::info('--- SELCOM WEBHOOK RECEIVED ---');
        Log::info('Headers: ' . json_encode($request->headers->all()));
        Log::info('Raw Payload: ' . $jsonData);

        // Validate Selcom Signature
        $providedSignature = $request->header('X-Selcom-Signature');
        $timestamp = $request->header('X-Selcom-Timestamp');
        
        if (!$providedSignature || !$timestamp) {
            Log::warning('Webhook rejected: Missing signature headers');
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $jsonData = $request->getContent();
        $stringToSign = "timestamp=" . $timestamp . "&" . $jsonData;
        $computedSignature = base64_encode(hash_hmac('sha256', $stringToSign, config('services.selcom.api_secret'), true));

        if (!hash_equals($computedSignature, $providedSignature)) {
            Log::warning('Webhook rejected: Invalid signature');
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $transactionId = $request->input('order_id');
        $status = $request->input('payment_status'); 

        if ($status === 'SUCCESS' || $status === 'COMPLETED') {
            DB::transaction(function () use ($transactionId) {
                $localTxn = DB::table('hotspot_transactions')
                    ->where('transaction_id', $transactionId)
                    ->lockForUpdate()
                    ->first();

                if ($localTxn && $localTxn->status === 'PENDING') {
                    
                    DB::table('hotspot_transactions')
                        ->where('transaction_id', $transactionId)
                        ->update([
                            'status' => 'SUCCESS',
                            'expires_at' => now()->addMinutes($localTxn->duration_minutes),
                            'updated_at' => now()
                        ]);

                    // Fire your router configuration connection here
                    event(new \App\Events\WifiPaymentSuccess($localTxn));
                }
            });
        } elseif (in_array($status, ['FAIL', 'FAILED', 'CANCELLED', 'USERCANCELED'])) {
            DB::table('hotspot_transactions')
                ->where('transaction_id', $transactionId)
                ->where('status', 'PENDING')
                ->update(['status' => 'FAILED', 'updated_at' => now()]);
        }

        return response()->json(['status' => 'SUCCESS', 'message' => 'Received'], 200);
    }

}