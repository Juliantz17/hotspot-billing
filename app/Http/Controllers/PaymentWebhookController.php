<?php

namespace App\Http\Controllers;

use App\Services\PaymentGatewayManager;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class PaymentWebhookController extends Controller
{
    public function __invoke(Request $request, string $gateway, PaymentGatewayManager $gateways): Response
    {
        return $gateways->gateway($gateway)->handleWebhook($request);
    }
}
