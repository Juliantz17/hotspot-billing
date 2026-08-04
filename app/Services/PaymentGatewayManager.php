<?php

namespace App\Services;

use App\Contracts\PaymentGateway;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class PaymentGatewayManager
{
    public function __construct(private array $gateways = []) {}

    public function register(string $name, PaymentGateway $gateway): void
    {
        $this->gateways[$name] = $gateway;
    }

    public function activeName(): string
    {
        $configured = config('payments.default', 'selcom');

        if (DB::getSchemaBuilder()->hasTable('settings')) {
            $configured = DB::table('settings')->where('key', 'payment_gateway')->value('value') ?: $configured;
        }

        return $configured;
    }

    public function active(): PaymentGateway
    {
        return $this->gateway($this->activeName());
    }

    public function gateway(string $name): PaymentGateway
    {
        if (! isset($this->gateways[$name])) {
            throw new InvalidArgumentException("Unsupported payment gateway: {$name}");
        }

        return $this->gateways[$name];
    }

    public function available(): array
    {
        return array_keys($this->gateways);
    }
}
