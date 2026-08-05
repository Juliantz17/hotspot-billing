<?php

namespace App\Http\Controllers;

use App\Services\HotspotAccessService;
use App\Services\HotspotPaymentService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class HotspotController extends Controller
{
    public function __construct(private HotspotAccessService $access, private HotspotPaymentService $payments) {}

    public function showCheckout(Request $request)
    {
        $data = $this->access->checkout($request->query('mac', '00:00:00:00:00:00'), $request->query('ip', ''), $request->has('manual'));

        return $data['reconnected'] ? response(view('reconnected', ['expires_at' => $data['expires_at']])) : view('checkout', $data);
    }

    public function reconnectUser(Request $request)
    {
        $result = $this->access->reconnect((string) $request->input('mac'), (string) $request->input('ip'));

        return $result['ok'] ? back()->with('success', $result['message']) : back()->withErrors(['reconnect' => $result['message']]);
    }

    public function recoverPackage(Request $request)
    {
        $data = $request->validate(['phone' => 'required|regex:/^0[67][0-9]{8}$/', 'mac' => 'required']);
        $result = $this->access->recover($data['phone'], $data['mac'], (string) $request->input('ip', ''));

        return $result['ok'] ? response(view('reconnected', ['expires_at' => $result['expires_at']])) : back()->withErrors(['recover' => $result['message']]);
    }

    public function showWaiting(string $txn)
    {
        $transaction = $this->payments->status($txn);
        if (! $transaction) {
            return redirect()->route('hotspot.checkout');
        }

        return view('waiting', ['txn' => $txn, 'status' => $transaction->status, 'mac' => $transaction->mac_address, 'ip' => $transaction->ip_address]);
    }

    public function processPayment(Request $request)
    {
        $data = $request->validate(['phone' => 'required|regex:/^0[67][0-9]{8}$/', 'package_id' => 'required|exists:packages,id', 'mac' => 'required']);
        try {
            $transactionId = $this->payments->initiate((int) $data['package_id'], $data['phone'], $data['mac'], (string) $request->input('ip', ''));

            return redirect()->route('hotspot.waiting', ['txn' => $transactionId]);
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            return back()->withErrors(['phone' => $e->getMessage()]);
        }
    }
}
