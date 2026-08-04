<?php

namespace App\Contracts;

use Illuminate\Http\Request;
use Illuminate\Http\Response;

interface PaymentGateway
{
    public function name(): string;

    public function initiate(object $transaction): void;

    public function checkStatus(object $transaction): ?string;

    public function handleWebhook(Request $request): Response;
}
