<?php

namespace Tests\Unit\Finance;

use App\Models\Game;
use App\Models\User;
use App\Models\Wallet;
use App\Modules\Finance\Enums\TransactionType;
use App\Modules\Finance\Services\GameSettlementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GameSettlementServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_settle_losing_bet_debits_wallet_only(): void
    {
        $user = User::factory()->create();
        $game = Game::factory()->create();
        Wallet::factory()->for($user)->create(['balance' => '100.00000000']);

        app(GameSettlementService::class)->settleBet($user->id, $game->id, '25.00000000', '0.00000000', [
            'roll' => 99,
        ]);

        $this->assertDatabaseHas('wallets', [
            'user_id' => $user->id,
            'balance' => '75.00000000',
        ]);
        $this->assertDatabaseHas('transactions', [
            'user_id' => $user->id,
            'type' => TransactionType::BetDebit->value,
            'amount' => '25.00000000',
        ]);
        $this->assertDatabaseMissing('transactions', [
            'user_id' => $user->id,
            'type' => TransactionType::BetPayout->value,
        ]);
    }

    public function test_settle_winning_bet_debits_and_credits_payout(): void
    {
        $user = User::factory()->create();
        $game = Game::factory()->create();
        Wallet::factory()->for($user)->create(['balance' => '100.00000000']);

        app(GameSettlementService::class)->settleBet($user->id, $game->id, '25.00000000', '50.00000000', [
            'roll' => 10,
        ]);

        $this->assertDatabaseHas('wallets', [
            'user_id' => $user->id,
            'balance' => '125.00000000',
        ]);
        $this->assertDatabaseHas('transactions', [
            'user_id' => $user->id,
            'type' => TransactionType::BetDebit->value,
            'amount' => '25.00000000',
        ]);
        $this->assertDatabaseHas('transactions', [
            'user_id' => $user->id,
            'type' => TransactionType::BetPayout->value,
            'amount' => '50.00000000',
        ]);
    }
}
