<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\HotspotTransaction; // We will use default DB queries or model

class HotspotController extends Controller
{
    /**
     * Show the custom checkout page when redirected from MikroTik
     */
    public function showCheckout(Request $request)
    {
        // Capture the MAC address sent over the URL by MikroTik's login.html
        $mac = $request->query('mac', 'UNKNOWN-MAC');
        
        // Pass it straight to our view
        return view('checkout', compact('mac'));
    }

    /**
     * Handle the form submission to trigger payment
     */
    public function processPayment(Request $request)
    {
        $request->validate([
            'phone' => 'required|regex:/^0[67][0-9]{8}$/', // Validates Tanzanian formats like 0712345678
            'package' => 'required'
        ]);

        // Determine price and duration based on package selection
        $duration = $request->package == '1hour' ? 60 : 1440;
        $amount = $request->package == '1hour' ? 500 : 2000;
        $transactionId = 'SELCOM_' . time();

        // 1. Save the initial pending payment track record to the database
        \DB::table('hotspot_transactions')->insert([
            'transaction_id' => $transactionId,
            'mac_address' => $request->mac,
            'phone_number' => $request->phone,
            'amount' => $amount,
            'duration_minutes' => $duration,
            'status' => 'PENDING',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 2. Technical Placeholder: Next step is to call Selcom API here.
        // For now, we simulate sending the customer to a processing screen.
        return "Transaction generated! Reference: $transactionId. An STK push prompt will be sent to $request->phone.";
    }
}