<?php

namespace Tests\Feature\Finance;

use App\Models\Intervention;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use App\Modules\Finance\Enums\TransactionStatus;
use App\Modules\Finance\Enums\TransactionType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\CreatesFinanceFixtures;
use Tests\TestCase;

class WalletControllerTest extends TestCase
{
    use CreatesFinanceFixtures;
    use RefreshDatabase;

    public function test_authenticated_user_can_view_wallet(): void
    {
        $user = User::factory()->create();
        Wallet::factory()->for($user)->create(['balance' => '123.45000000']);

        $this->actingAs($user)
            ->getJson('/api/v1/wallet')
            ->assertOk()
            ->assertJson(['currency' => 'USD', 'balance' => '123.45000000']);
    }

    public function test_unauthenticated_user_is_rejected_from_wallet(): void
    {
        $this->getJson('/api/v1/wallet')->assertUnauthorized();
    }

    public function test_valid_deposit_creates_invoice(): void
    {
        $this->fakePaymentGateway();
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/api/v1/wallet/deposit', ['amount_usd' => '50.00', 'coin' => 'btc'])
            ->assertCreated()
            ->assertJsonPath('deposit_invoice.user_id', $user->id)
            ->assertJsonPath('deposit_invoice.status', 'pending');

        $this->assertDatabaseHas('deposit_invoices', [
            'user_id' => $user->id,
            'expected_usd' => '50.00000000',
            'coin' => 'btc',
        ]);
    }

    #[DataProvider('invalidDepositAmounts')]
    public function test_invalid_deposit_amount_is_rejected(mixed $amount): void
    {
        $this->fakePaymentGateway();

        $this->actingAs(User::factory()->create())
            ->postJson('/api/v1/wallet/deposit', ['amount_usd' => $amount, 'coin' => 'btc'])
            ->assertUnprocessable();
    }

    public static function invalidDepositAmounts(): array
    {
        return [
            'zero' => ['0'],
            'negative' => ['-1'],
            'non_numeric' => ['abc'],
        ];
    }

    public function test_deposit_with_many_decimals_is_accepted_by_current_rules(): void
    {
        $this->fakePaymentGateway();

        $this->actingAs(User::factory()->create())
            ->postJson('/api/v1/wallet/deposit', ['amount_usd' => '10.123456789', 'coin' => 'btc'])
            ->assertCreated();
    }

    public function test_valid_withdrawal_creates_pending_withdrawal_and_debits_balance(): void
    {
        $user = User::factory()->create();
        Wallet::factory()->for($user)->create(['balance' => '100.00000000']);

        $this->actingAs($user)
            ->postJson('/api/v1/wallet/withdraw', [
                'amount' => '25.00',
                'destination' => 'bc1qdestination',
                'provider_method' => 'crypto',
            ])
            ->assertAccepted()
            ->assertJsonPath('withdrawal.status', TransactionStatus::Pending->value);

        $this->assertDatabaseHas('wallets', ['user_id' => $user->id, 'balance' => '75.00000000']);
        $this->assertDatabaseHas('transactions', [
            'user_id' => $user->id,
            'type' => TransactionType::Withdrawal->value,
            'status' => TransactionStatus::Pending->value,
            'amount' => '25.00000000',
        ]);
    }

    public function test_withdrawal_with_insufficient_balance_is_rejected(): void
    {
        $user = User::factory()->create();
        Wallet::factory()->for($user)->create(['balance' => '5.00000000']);

        $this->actingAs($user)
            ->postJson('/api/v1/wallet/withdraw', [
                'amount' => '25.00',
                'destination' => 'bc1qdestination',
            ])
            ->assertStatus(500);
    }

    #[DataProvider('invalidWithdrawalAmounts')]
    public function test_invalid_withdrawal_amount_is_rejected(mixed $amount): void
    {
        $this->actingAs(User::factory()->create())
            ->postJson('/api/v1/wallet/withdraw', ['amount' => $amount, 'destination' => 'bc1qdestination'])
            ->assertUnprocessable();
    }

    public static function invalidWithdrawalAmounts(): array
    {
        return [
            'zero' => ['0'],
            'negative' => ['-1'],
            'non_numeric' => ['abc'],
        ];
    }

    public function test_withdrawal_with_many_decimals_is_accepted_by_current_rules(): void
    {
        $user = User::factory()->create();
        Wallet::factory()->for($user)->create(['balance' => '100.00000000']);

        $this->actingAs($user)
            ->postJson('/api/v1/wallet/withdraw', ['amount' => '10.123456789', 'destination' => 'bc1qdestination'])
            ->assertAccepted();
    }

    public function test_deposit_is_blocked_by_active_circuit_breaker(): void
    {
        $this->fakePaymentGateway();
        $user = User::factory()->create();
        Intervention::factory()->for($user)->create(['type' => 'circuit_breaker']);

        $this->actingAs($user)
            ->postJson('/api/v1/wallet/deposit', ['amount_usd' => '50.00', 'coin' => 'btc'])
            ->assertStatus(423);
    }
}
