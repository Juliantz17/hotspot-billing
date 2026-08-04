<?php

namespace App\Http\Controllers;

use App\Events\WifiPaymentSuccess;
use App\Models\Package;
use App\Services\PaymentCompletionService;
use App\Services\PaymentGatewayManager;
use App\Services\RouterProvisioningService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class HotspotController extends Controller
{
    public function __construct(private PaymentGatewayManager $gateways, private PaymentCompletionService $completion) {}

    public function showCheckout(Request $request)
    {
        $mac = $request->query('mac', '00:00:00:00:00:00');
        $ip = $request->query('ip', '');
        $activeTxn = null;

        if ($mac !== '00:00:00:00:00:00') {
            try {
                DB::table('checkout_visits')->insert([
                    'mac_address' => $mac,
                    'ip_address' => $ip,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } catch (\Exception $e) {
                Log::error('Failed to log checkout visit: '.$e->getMessage());
            }
        }

        if ($mac !== '00:00:00:00:00:00') {
            $activeTxn = DB::table('hotspot_transactions')
                ->where('mac_address', $mac)
                ->where('status', 'SUCCESS')
                ->where('expires_at', '>', now())
                ->latest()
                ->first();

            // Auto-reconnect seamlessly if they are active
            if ($activeTxn && ! $request->has('manual')) {
                try {
                    $remainingMinutes = now()->diffInMinutes($activeTxn->expires_at);

                    if ($remainingMinutes > 0) {
                        $activeTxn->ip_address = $ip;
                        app(RouterProvisioningService::class)->provisionAccess($activeTxn, 'Auto-Reconnect Txn');

                        return response(view('reconnected', [
                            'expires_at' => $activeTxn->expires_at,
                        ]));
                    }
                } catch (\Exception $e) {
                    Log::error('Auto-reconnect failed in showCheckout: '.$e->getMessage());
                    // Silently fall back to showing the checkout page with the manual button
                }
            }
        }

        $packages = Package::where('is_active', true)->get();

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

        if (! $activeTxn) {
            return back()->withErrors(['reconnect' => 'Hakuna kifurushi kinachoendelea kwa simu hii.']);
        }

        try {
            $remainingMinutes = now()->diffInMinutes($activeTxn->expires_at);
            if ($remainingMinutes < 1) {
                return back()->withErrors(['reconnect' => 'Kifurushi chako kimeisha.']);
            }

            $activeTxn->ip_address = $ip;
            app(RouterProvisioningService::class)->provisionAccess($activeTxn, 'Reconnect Txn');

            return back()->with('success', 'Umefanikiwa kuunganishwa tena. Unaweza kuendelea kutumia intaneti.');

        } catch (\Exception $e) {
            Log::error('User reconnect failed: '.$e->getMessage());

            return back()->withErrors(['reconnect' => 'Imeshindwa kuunganisha kwenye router.']);
        }
    }

    public function recoverPackage(Request $request)
    {
        $request->validate([
            'phone' => 'required|regex:/^0[67][0-9]{8}$/',
            'mac' => 'required',
        ]);

        $formattedPhone = '255'.substr($request->phone, 1);
        $newMac = $request->input('mac');
        $ip = $request->input('ip', '');

        $activeTxn = DB::table('hotspot_transactions')
            ->where('phone_number', $formattedPhone)
            ->where('status', 'SUCCESS')
            ->where('expires_at', '>', now())
            ->latest()
            ->first();

        if (! $activeTxn) {
            return back()->withErrors(['recover' => 'Hakuna kifurushi kinachoendelea kwa namba hii ya simu.']);
        }

        try {
            $remainingMinutes = now()->diffInMinutes($activeTxn->expires_at);
            if ($remainingMinutes < 1) {
                return back()->withErrors(['recover' => 'Kifurushi chako kimeisha.']);
            }

            $routerProvisioning = app(RouterProvisioningService::class);
            $routerProvisioning->removeMacAccess($activeTxn->mac_address);
            $routerProvisioning->removeLoginState($newMac);

            DB::table('hotspot_transactions')
                ->where('id', $activeTxn->id)
                ->update([
                    'mac_address' => $newMac,
                    'ip_address' => $ip,
                    'updated_at' => now(),
                ]);

            $activeTxn->mac_address = $newMac;
            $activeTxn->ip_address = $ip;
            $routerProvisioning->provisionAccess($activeTxn, 'Recovered Txn');

            return response(view('reconnected', [
                'expires_at' => $activeTxn->expires_at,
            ]));

        } catch (\Exception $e) {
            Log::error('Package recovery failed: '.$e->getMessage());

            return back()->withErrors(['recover' => 'Imeshindwa kuunganisha kwenye router.']);
        }
    }

    public function showWaiting($txn)
    {
        Log::info("--- WAITING PAGE ACCESSED FOR $txn ---");

        $transaction = DB::table('hotspot_transactions')->where('transaction_id', $txn)->first();
        if (! $transaction) {
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
                // Ask the gateway that created this transaction, when it supports polling.
                try {
                    $gateway = $this->gateways->gateway($transaction->payment_gateway ?? 'selcom');
                    if ($status = $gateway->checkStatus($transaction)) {
                        $this->completion->apply($txn, $status, $gateway->name());
                        $transaction->status = $status;
                    }
                } catch (\Exception $e) {
                    // Silently ignore connection errors here and keep polling
                    Log::error("Failed to poll Selcom status for $txn: ".$e->getMessage());
                }
            }
        }

        return view('waiting', [
            'txn' => $txn,
            'status' => $transaction->status,
            'mac' => $transaction->mac_address,
            'ip' => $transaction->ip_address,
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
            'mac' => 'required',
        ]);

        $package = Package::findOrFail($request->package_id);
        if (! $package->is_active) {
            return back()->withErrors(['package_id' => 'This package is no longer available.']);
        }

        $duration = $package->duration_minutes;
        $amount = $package->price;

        $formattedPhone = '255'.substr($request->phone, 1);
        $transactionId = 'HOTSPOT_'.strtoupper((string) str()->ulid());
        $gateway = $this->gateways->active();

        // 1. Log the transaction as PENDING locally
        DB::table('hotspot_transactions')->insert([
            'transaction_id' => $transactionId,
            'payment_gateway' => $gateway->name(),
            'package_id' => $package->id,
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
            $transaction = DB::table('hotspot_transactions')->where('transaction_id', $transactionId)->first();
            $gateway->initiate($transaction);

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
            throw new \Exception('Configuration Error: SELCOM_BASE_URL, SELCOM_API_SECRET, or SELCOM_API_KEY is missing. If they are in your .env, try running: php artisan config:clear');
        }

        $timestamp = now()->toIso8601String();

        // Extract fields for Signed-Fields
        $signedFieldsArray = array_keys($body);
        $signedFields = implode(',', $signedFieldsArray);

        // Build signing string according to Selcom docs: timestamp=<val>&field1=<val>&field2=<val>
        $stringToSign = 'timestamp='.$timestamp;
        foreach ($signedFieldsArray as $key) {
            // Values must match request payload exactly (no urlencoding unless specified)
            $stringToSign .= '&'.$key.'='.$body[$key];
        }

        $signature = base64_encode(hash_hmac('sha256', $stringToSign, $apiSecret, true));
        $authToken = base64_encode($apiKey);

        $headers = [
            'Authorization' => 'SELCOM '.$authToken,
            'Digest-Method' => 'HS256',
            'Digest' => $signature,
            'Timestamp' => $timestamp,
            'Signed-Fields' => $signedFields,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ];

        $debugInfo = json_encode([
            'url' => $baseUrl.$path,
            'stringToSign' => $stringToSign,
            'generatedSignature' => $signature,
            'signedFields' => $signedFields,
            'hasApiKey' => 'YES',
            'hasApiSecret' => 'YES',
            'headers' => $headers,
        ]);

        if (strtoupper($method) === 'GET') {
            $response = Http::withHeaders($headers)->get($baseUrl.$path);
        } else {
            $response = Http::withHeaders($headers)->post($baseUrl.$path, $body);
        }

        if (! $response->successful()) {
            // Log safely to server's error log (e.g. Nginx/Apache error.log) and Laravel log
            error_log('SELCOM DEBUG INFO: '.$debugInfo);
            Log::error('SELCOM DEBUG INFO: '.$debugInfo);

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
        Log::info('Headers: '.json_encode($request->headers->all()));
        Log::info('Raw Payload: '.$jsonData);

        // Validate Selcom Signature
        $providedSignature = $request->header('X-Selcom-Signature');
        $timestamp = $request->header('X-Selcom-Timestamp');

        if (! $providedSignature || ! $timestamp) {
            Log::warning('Webhook rejected: Missing signature headers');

            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $jsonData = $request->getContent();
        $stringToSign = 'timestamp='.$timestamp.'&'.$jsonData;
        $computedSignature = base64_encode(hash_hmac('sha256', $stringToSign, config('services.selcom.api_secret'), true));

        if (! hash_equals($computedSignature, $providedSignature)) {
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
                            'updated_at' => now(),
                        ]);

                    // Fire your router configuration connection here
                    event(new WifiPaymentSuccess($localTxn));
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
