<?php

namespace Tests\Feature;

use App\Contracts\PaymentGateway;
use App\Events\WifiPaymentSuccess;
use App\Models\Package;
use App\Services\PaymentCompletionService;
use App\Services\PaymentGatewayManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class PaymentGatewaySelectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_switch_the_gateway_used_by_new_checkouts(): void
    {
        $fake = new class implements PaymentGateway
        {
            public bool $initiated = false;

            public function name(): string
            {
                return 'azampay';
            }

            public function initiate(object $transaction): void
            {
                $this->initiated = true;
            }

            public function checkStatus(object $transaction): ?string
            {
                return null;
            }

            public function handleWebhook(Request $request): Response
            {
                return response(['status' => 'ok']);
            }
        };
        app(PaymentGatewayManager::class)->register('azampay', $fake);

        $this->withSession(['admin_logged_in' => true])
            ->put('/admin/payment-gateway', ['payment_gateway' => 'azampay'])
            ->assertRedirect();

        $package = Package::create([
            'name' => 'Test Package',
            'price' => 1000,
            'duration_minutes' => 60,
            'speed_limit' => '2M/2M',
            'is_active' => true,
        ]);

        $this->post('/process-payment', [
            'phone' => '0712345678',
            'package_id' => $package->id,
            'mac' => '00:11:22:33:44:55',
            'ip' => '192.168.88.10',
        ])->assertRedirect();

        $this->assertTrue($fake->initiated);
        $this->assertSame('azampay', DB::table('hotspot_transactions')->value('payment_gateway'));
        $this->assertSame('azampay', DB::table('settings')->where('key', 'payment_gateway')->value('value'));
    }

    public function test_admin_cannot_select_an_unregistered_gateway(): void
    {
        $this->withSession(['admin_logged_in' => true])
            ->put('/admin/payment-gateway', ['payment_gateway' => 'unknown'])
            ->assertSessionHasErrors('payment_gateway');
    }

    public function test_switching_gateway_does_not_corrupt_an_in_progress_payment(): void
    {
        Event::fake([WifiPaymentSuccess::class]);

        DB::table('hotspot_transactions')->insert([
            'transaction_id' => 'AZAM-IN-PROGRESS',
            'payment_gateway' => 'azampay',
            'mac_address' => '00:11:22:33:44:55',
            'phone_number' => '255712345678',
            'amount' => 1000,
            'duration_minutes' => 60,
            'status' => 'PENDING',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('settings')->where('key', 'payment_gateway')->update(['value' => 'selcom', 'updated_at' => now()]);

        app(PaymentCompletionService::class)->apply('AZAM-IN-PROGRESS', 'SUCCESS', 'selcom');
        $this->assertSame('PENDING', DB::table('hotspot_transactions')->where('transaction_id', 'AZAM-IN-PROGRESS')->value('status'));

        app(PaymentCompletionService::class)->apply('AZAM-IN-PROGRESS', 'SUCCESS', 'azampay');
        app(PaymentCompletionService::class)->apply('AZAM-IN-PROGRESS', 'SUCCESS', 'azampay');

        $transaction = DB::table('hotspot_transactions')->where('transaction_id', 'AZAM-IN-PROGRESS')->first();
        $this->assertSame('SUCCESS', $transaction->status);
        $this->assertNotNull($transaction->expires_at);
        $this->assertSame('selcom', DB::table('settings')->where('key', 'payment_gateway')->value('value'));
        Event::assertDispatchedTimes(WifiPaymentSuccess::class, 1);
    }

    public function test_azam_callback_works_without_query_parameters(): void
    {
        Event::fake([WifiPaymentSuccess::class]);

        DB::table('hotspot_transactions')->insert([
            'transaction_id' => 'AZAM-CALLBACK-REFERENCE',
            'payment_gateway' => 'azampay',
            'mac_address' => '00:11:22:33:44:66',
            'phone_number' => '255712345679',
            'amount' => 1000,
            'duration_minutes' => 60,
            'status' => 'PENDING',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->postJson('/webhook/azampay', [
            'transactionstatus' => 'success',
            'utilityref' => 'AZAM-CALLBACK-REFERENCE',
        ])->assertOk()->assertJson(['status' => 'ok']);

        $this->assertDatabaseHas('hotspot_transactions', [
            'transaction_id' => 'AZAM-CALLBACK-REFERENCE',
            'payment_gateway' => 'azampay',
            'status' => 'SUCCESS',
        ]);
    }

    public function test_failed_gateway_initiation_is_preserved_for_auditing(): void
    {
        $failingGateway = new class implements PaymentGateway
        {
            public function name(): string
            {
                return 'selcom';
            }

            public function initiate(object $transaction): void
            {
                throw new \RuntimeException('Gateway unavailable');
            }

            public function checkStatus(object $transaction): ?string
            {
                return null;
            }

            public function handleWebhook(Request $request): Response
            {
                return response();
            }
        };
        app(PaymentGatewayManager::class)->register('selcom', $failingGateway);

        $package = Package::create([
            'name' => 'Audit Package', 'price' => 1000, 'duration_minutes' => 60,
            'speed_limit' => '2M/2M', 'is_active' => true,
        ]);

        $this->from('/checkout')->post('/process-payment', [
            'phone' => '0712345678', 'package_id' => $package->id,
            'mac' => '00:11:22:33:44:77', 'ip' => '192.168.88.11',
        ])->assertRedirect('/checkout')->assertSessionHasErrors('phone');

        $this->assertDatabaseHas('hotspot_transactions', [
            'payment_gateway' => 'selcom', 'status' => 'FAILED',
            'mac_address' => '00:11:22:33:44:77',
        ]);
    }
}
