<?php

namespace Tests\Unit\Finance;

use App\Models\User;
use App\Models\Wallet;
use App\Modules\Finance\Enums\TransactionStatus;
use App\Modules\Finance\Enums\TransactionType;
use App\Modules\Finance\Services\BalanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BalanceServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_credit_updates_wallet_and_records_transaction(): void
    {
        $user = User::factory()->create();

        app(BalanceService::class)->credit(
            userId: $user->id,
            amount: '100.00000000',
            currency: 'USD',
            type: TransactionType::AdminCredit,
            reason: 'Manual credit.',
        );

        $this->assertDatabaseHas('wallets', [
            'user_id' => $user->id,
            'balance' => '100.00000000',
        ]);

        $this->assertDatabaseHas('transactions', [
            'user_id' => $user->id,
            'type' => TransactionType::AdminCredit->value,
            'status' => TransactionStatus::Confirmed->value,
            'amount' => '100.00000000',
            'reason' => 'Manual credit.',
        ]);
    }

    public function test_debit_updates_wallet_and_records_transaction(): void
    {
        $user = User::factory()->create();
        Wallet::factory()->for($user)->create(['balance' => '100.00000000']);

        app(BalanceService::class)->debit(
            userId: $user->id,
            amount: '40.00000000',
            currency: 'USD',
            type: TransactionType::BetDebit,
            reason: 'game_bet',
        );

        $this->assertDatabaseHas('wallets', [
            'user_id' => $user->id,
            'balance' => '60.00000000',
        ]);

        $this->assertDatabaseHas('transactions', [
            'user_id' => $user->id,
            'type' => TransactionType::BetDebit->value,
            'amount' => '40.00000000',
            'reason' => 'game_bet',
        ]);
    }
}
