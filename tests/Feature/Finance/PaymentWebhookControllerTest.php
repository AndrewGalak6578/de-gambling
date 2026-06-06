<?php

namespace Tests\Feature\Finance;

use App\Models\DepositInvoice;
use App\Models\User;
use App\Models\Wallet;
use App\Modules\Finance\Enums\DepositInvoiceStatus;
use App\Modules\Finance\Enums\TransactionType;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesFinanceFixtures;
use Tests\Support\FakePaymentGateway;
use Tests\TestCase;

class PaymentWebhookControllerTest extends TestCase
{
    use CreatesFinanceFixtures;
    use RefreshDatabase;

    public function test_valid_webhook_updates_invoice_and_credits_wallet(): void
    {
        $gateway = $this->fakePaymentGateway();
        $user = User::factory()->create();
        $invoice = DepositInvoice::factory()->for($user)->create(['provider_invoice_id' => 'inv-paid', 'expected_usd' => '50.00000000']);

        $gateway->parsedWebhook = $this->webhook('invoice.paid', 'inv-paid');

        $this->postJson('/api/v1/payments/webhook', ['event' => 'invoice.paid'], ['X-Webhook-Signature' => 'valid'])
            ->assertOk()
            ->assertJson(['status' => 'ok', 'deposit_invoice_id' => $invoice->id, 'credited' => true]);

        $this->assertDatabaseHas('deposit_invoices', ['id' => $invoice->id, 'status' => DepositInvoiceStatus::Paid->value]);
        $this->assertDatabaseHas('wallets', ['user_id' => $user->id, 'balance' => '50.00000000']);
        $this->assertDatabaseHas('transactions', ['user_id' => $user->id, 'type' => TransactionType::Deposit->value, 'amount' => '50.00000000']);
    }

    public function test_duplicate_paid_webhook_is_idempotent(): void
    {
        $gateway = $this->fakePaymentGateway();
        $user = User::factory()->create();
        DepositInvoice::factory()->for($user)->create(['provider_invoice_id' => 'inv-dup', 'expected_usd' => '50.00000000']);
        $gateway->parsedWebhook = $this->webhook('invoice.paid', 'inv-dup');

        $this->postJson('/api/v1/payments/webhook', ['event' => 'invoice.paid'], ['X-Webhook-Signature' => 'valid'])->assertOk();
        $this->postJson('/api/v1/payments/webhook', ['event' => 'invoice.paid'], ['X-Webhook-Signature' => 'valid'])->assertOk();

        $this->assertSame(1, Wallet::query()->where('user_id', $user->id)->count());
        $this->assertDatabaseHas('wallets', ['user_id' => $user->id, 'balance' => '50.00000000']);
    }

    public function test_invalid_signature_is_rejected(): void
    {
        $gateway = $this->fakePaymentGateway();
        $gateway->signatureValid = false;

        $this->postJson('/api/v1/payments/webhook', ['event' => 'invoice.paid'], ['X-Webhook-Signature' => 'bad'])
            ->assertUnauthorized();
    }

    public function test_unknown_invoice_reference_is_rejected(): void
    {
        $gateway = $this->fakePaymentGateway();
        $gateway->parsedWebhook = $this->webhook('invoice.paid', 'missing');

        $this->withoutExceptionHandling();
        $this->expectException(ModelNotFoundException::class);

        $this->postJson('/api/v1/payments/webhook', ['event' => 'invoice.paid'], ['X-Webhook-Signature' => 'valid']);
    }

    public function test_failed_webhook_marks_invoice_failed_without_crediting(): void
    {
        $gateway = $this->fakePaymentGateway();
        $invoice = DepositInvoice::factory()->create(['provider_invoice_id' => 'inv-failed']);
        $gateway->parsedWebhook = $this->webhook('invoice.failed', 'inv-failed', 'failed');

        $this->postJson('/api/v1/payments/webhook', ['event' => 'invoice.failed'], ['X-Webhook-Signature' => 'valid'])
            ->assertOk()
            ->assertJson(['credited' => false]);

        $this->assertDatabaseHas('deposit_invoices', ['id' => $invoice->id, 'status' => DepositInvoiceStatus::Failed->value]);
        $this->assertDatabaseCount('wallets', 0);
    }

    public function test_cancelled_webhook_status_falls_back_to_pending_under_current_code(): void
    {
        $gateway = $this->fakePaymentGateway();
        $invoice = DepositInvoice::factory()->create(['provider_invoice_id' => 'inv-cancelled']);
        $gateway->parsedWebhook = $this->webhook('invoice.cancelled', 'inv-cancelled', 'cancelled');

        $this->postJson('/api/v1/payments/webhook', ['event' => 'invoice.cancelled'], ['X-Webhook-Signature' => 'valid'])
            ->assertOk()
            ->assertJson(['credited' => false]);

        $this->assertDatabaseHas('deposit_invoices', ['id' => $invoice->id, 'status' => DepositInvoiceStatus::Pending->value]);
    }

    private function webhook(string $event, string $providerInvoiceId, ?string $status = null): array
    {
        return [
            'event' => $event,
            'provider_invoice_id' => $providerInvoiceId,
            'provider_public_id' => null,
            'external_id' => null,
            'status' => $status,
            'payload' => ['event' => $event],
        ];
    }
}
