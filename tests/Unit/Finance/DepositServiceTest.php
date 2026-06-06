<?php

namespace Tests\Unit\Finance;

use App\Models\DepositInvoice;
use App\Models\User;
use App\Modules\Finance\Contracts\PaymentGatewayInterface;
use App\Modules\Finance\Data\CreateDepositInvoiceData;
use App\Modules\Finance\Enums\DepositInvoiceStatus;
use App\Modules\Finance\Enums\TransactionType;
use App\Modules\Finance\Services\DepositService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\FakePaymentGateway;
use Tests\TestCase;

class DepositServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_invoice_uses_gateway_and_persists_invoice(): void
    {
        $gateway = new FakePaymentGateway();
        $this->app->instance(PaymentGatewayInterface::class, $gateway);
        $user = User::factory()->create();

        $invoice = app(DepositService::class)->createInvoice($user, CreateDepositInvoiceData::fromArray([
            'amount_usd' => '25',
            'coin' => 'BTC',
            'metadata' => ['source' => 'test'],
        ]));

        $this->assertSame($user->id, $invoice->user_id);
        $this->assertSame('pending', $invoice->status);
        $this->assertSame('25.00', $gateway->lastCreatePayload['amount_usd']);
        $this->assertSame('btc', $gateway->lastCreatePayload['coin']);
        $this->assertSame($user->id, $gateway->lastCreatePayload['metadata']['user_id']);
    }

    public function test_paid_webhook_credits_wallet_once(): void
    {
        $user = User::factory()->create();
        $invoice = DepositInvoice::factory()->for($user)->create([
            'provider_invoice_id' => 'inv_paid',
            'status' => DepositInvoiceStatus::Pending->value,
            'expected_usd' => '30.00000000',
        ]);

        $webhook = [
            'event' => 'invoice.paid',
            'provider_invoice_id' => 'inv_paid',
            'payload' => ['event' => 'invoice.paid'],
        ];

        app(DepositService::class)->handleWebhook($webhook);
        app(DepositService::class)->handleWebhook($webhook);

        $this->assertDatabaseHas('deposit_invoices', [
            'id' => $invoice->id,
            'status' => DepositInvoiceStatus::Paid->value,
        ]);

        $this->assertDatabaseHas('wallets', [
            'user_id' => $user->id,
            'balance' => '30.00000000',
        ]);

        $this->assertSame(1, \App\Models\Transaction::query()
            ->where('user_id', $user->id)
            ->where('type', TransactionType::Deposit->value)
            ->count());
    }

    public function test_failed_webhook_marks_invoice_failed_without_credit(): void
    {
        $user = User::factory()->create();
        $invoice = DepositInvoice::factory()->for($user)->create([
            'provider_invoice_id' => 'inv_failed',
            'status' => DepositInvoiceStatus::Pending->value,
        ]);

        app(DepositService::class)->handleWebhook([
            'event' => 'invoice.failed',
            'provider_invoice_id' => 'inv_failed',
            'status' => 'failed',
            'payload' => ['event' => 'invoice.failed'],
        ]);

        $this->assertDatabaseHas('deposit_invoices', [
            'id' => $invoice->id,
            'status' => DepositInvoiceStatus::Failed->value,
            'credited_at' => null,
        ]);

        $this->assertDatabaseMissing('wallets', [
            'user_id' => $user->id,
        ]);
    }
}
