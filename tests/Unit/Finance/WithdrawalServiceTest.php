<?php

namespace Tests\Unit\Finance;

use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use App\Modules\Finance\Enums\TransactionStatus;
use App\Modules\Finance\Enums\TransactionType;
use App\Modules\Finance\Services\WithdrawalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class WithdrawalServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_request_withdrawal_debits_wallet_and_creates_pending_transaction(): void
    {
        $user = User::factory()->create();
        Wallet::factory()->for($user)->create(['balance' => '100.00000000']);

        $transaction = app(WithdrawalService::class)->requestWithdrawal(
            user: $user,
            amount: '30.00000000',
            destination: 'bank-account',
        );

        $this->assertSame(TransactionType::Withdrawal->value, $transaction->type);
        $this->assertSame(TransactionStatus::Pending->value, $transaction->status);
        $this->assertDatabaseHas('wallets', [
            'user_id' => $user->id,
            'balance' => '70.00000000',
        ]);
    }

    public function test_approve_changes_pending_withdrawal_to_confirmed(): void
    {
        $admin = User::factory()->create();
        $user = User::factory()->create();
        $wallet = Wallet::factory()->for($user)->create(['balance' => '70.00000000']);
        $transaction = Transaction::factory()->for($user)->for($wallet)->withdrawal()->create([
            'amount' => '30.00000000',
            'status' => TransactionStatus::Pending->value,
        ]);

        $approved = app(WithdrawalService::class)->approve($transaction->id, $admin, 'tx_hash');

        $this->assertSame(TransactionStatus::Confirmed->value, $approved->status);
        $this->assertSame('tx_hash', $approved->tx_hash);
        $this->assertSame('approved', $approved->meta['settlement']['status']);
    }

    public function test_reject_refunds_wallet_and_creates_correction_transaction(): void
    {
        $admin = User::factory()->create();
        $user = User::factory()->create();
        $wallet = Wallet::factory()->for($user)->create(['balance' => '70.00000000']);
        $transaction = Transaction::factory()->for($user)->for($wallet)->withdrawal()->create([
            'amount' => '30.00000000',
            'status' => TransactionStatus::Pending->value,
        ]);

        $rejected = app(WithdrawalService::class)->reject($transaction->id, $admin, 'Bad destination.');

        $this->assertSame(TransactionStatus::Rejected->value, $rejected->status);
        $this->assertDatabaseHas('wallets', [
            'user_id' => $user->id,
            'balance' => '100.00000000',
        ]);
        $this->assertDatabaseHas('transactions', [
            'user_id' => $user->id,
            'type' => TransactionType::SettlementCorrection->value,
            'amount' => '30.00000000',
        ]);
    }

    public function test_already_processed_withdrawal_cannot_be_approved(): void
    {
        $admin = User::factory()->create();
        $user = User::factory()->create();
        $wallet = Wallet::factory()->for($user)->create();
        $transaction = Transaction::factory()->for($user)->for($wallet)->withdrawal()->create([
            'status' => TransactionStatus::Confirmed->value,
        ]);

        $this->expectException(RuntimeException::class);

        app(WithdrawalService::class)->approve($transaction->id, $admin);
    }
}
