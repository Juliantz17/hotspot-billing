<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\PaymentGatewayManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PaymentGatewayController extends Controller
{
    public function edit(PaymentGatewayManager $gateways): View
    {
        return view('admin.payment-gateway', ['activeGateway' => $gateways->activeName(), 'gateways' => $gateways->available()]);
    }

    public function update(Request $request, PaymentGatewayManager $gateways): RedirectResponse
    {
        $data = $request->validate(['payment_gateway' => ['required', Rule::in($gateways->available())]]);
        DB::table('settings')->updateOrInsert(['key' => 'payment_gateway'], ['value' => $data['payment_gateway'], 'updated_at' => now(), 'created_at' => now()]);

        return back()->with('success', 'Payment gateway updated. New checkouts will use '.ucfirst($data['payment_gateway']).'.');
    }
}
