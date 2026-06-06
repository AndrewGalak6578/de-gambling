<?php

namespace Tests\Feature\Finance;

use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use App\Modules\Finance\Enums\TransactionStatus;
use App\Modules\Finance\Enums\TransactionType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesFinanceFixtures;
use Tests\TestCase;

class AdminWithdrawalControllerTest extends TestCase
{
    use CreatesFinanceFixtures;
    use RefreshDatabase;

    public function test_non_admin_is_forbidden(): void
    {
        $this->actingAs(User::factory()->create())
            ->getJson('/api/v1/admin/withdrawals')
            ->assertForbidden();
    }

    public function test_admin_can_list_pending_withdrawals(): void
    {
        $transaction = $this->pendingWithdrawal();

        $this->actingAs($this->adminUser())
            ->getJson('/api/v1/admin/withdrawals')
            ->assertOk()
            ->assertJsonPath('withdrawals.0.id', $transaction->id);
    }

    public function test_admin_can_approve_pending_withdrawal(): void
    {
        $transaction = $this->pendingWithdrawal();

        $this->actingAs($this->adminUser())
            ->patchJson("/api/v1/admin/withdrawals/{$transaction->id}/approve", ['tx_hash' => 'tx-123'])
            ->assertOk()
            ->assertJsonPath('withdrawal.status', TransactionStatus::Confirmed->value);

        $this->assertDatabaseHas('transactions', [
            'id' => $transaction->id,
            'status' => TransactionStatus::Confirmed->value,
            'tx_hash' => 'tx-123',
        ]);
    }

    public function test_admin_can_reject_pending_withdrawal_and_refund_balance(): void
    {
        $user = User::factory()->create();
        $wallet = Wallet::factory()->for($user)->create(['balance' => '75.00000000']);
        $transaction = Transaction::factory()->for($user)->for($wallet)->create([
            'type' => TransactionType::Withdrawal->value,
            'status' => TransactionStatus::Pending->value,
            'amount' => '25.00000000',
            'currency' => 'USD',
        ]);

        $this->actingAs($this->adminUser())
            ->patchJson("/api/v1/admin/withdrawals/{$transaction->id}/reject", ['reason' => 'bad address'])
            ->assertOk()
            ->assertJsonPath('withdrawal.status', TransactionStatus::Rejected->value);

        $this->assertDatabaseHas('wallets', ['id' => $wallet->id, 'balance' => '100.00000000']);
        $this->assertDatabaseHas('transactions', [
            'type' => TransactionType::SettlementCorrection->value,
            'amount' => '25.00000000',
            'reason' => 'Refund for rejected withdrawal.',
        ]);
    }

    public function test_already_approved_withdrawal_cannot_be_processed_again(): void
    {
        $transaction = $this->pendingWithdrawal(['status' => TransactionStatus::Confirmed->value]);

        $this->actingAs($this->adminUser())
            ->patchJson("/api/v1/admin/withdrawals/{$transaction->id}/approve")
            ->assertStatus(500);
    }

    public function test_already_rejected_withdrawal_cannot_be_processed_again(): void
    {
        $transaction = $this->pendingWithdrawal(['status' => TransactionStatus::Rejected->value]);

        $this->actingAs($this->adminUser())
            ->patchJson("/api/v1/admin/withdrawals/{$transaction->id}/reject", ['reason' => 'again'])
            ->assertStatus(500);
    }

    private function pendingWithdrawal(array $overrides = []): Transaction
    {
        $user = User::factory()->create();
        $wallet = Wallet::factory()->for($user)->create(['balance' => '75.00000000']);

        return Transaction::factory()->for($user)->for($wallet)->create([
            'type' => TransactionType::Withdrawal->value,
            'status' => TransactionStatus::Pending->value,
            'amount' => '25.00000000',
            'currency' => 'USD',
            ...$overrides,
        ]);
    }
}
