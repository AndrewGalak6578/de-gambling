<?php

namespace Tests\Feature\Game;

use App\Models\Bet;
use App\Models\Game;
use App\Models\RiskEvent;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use App\Modules\Finance\Enums\TransactionStatus;
use App\Modules\Finance\Enums\TransactionType;
use App\Modules\Game\Services\ProvablyFairService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GameSettlementResponsibleGamblingTest extends TestCase
{
    use RefreshDatabase;

    public function test_settled_bet_calls_finance_settlement_and_responsible_gambling_analysis(): void
    {
        $this->app->instance(ProvablyFairService::class, new class extends ProvablyFairService
        {
            public function generateServerSeed(): string
            {
                return 'fixed-server-seed';
            }

            public function hashServerSeed(string $serverSeed): string
            {
                return hash('sha256', $serverSeed);
            }

            public function generateResult(string $serverSeed, string $clientSeed, int $nonce): float
            {
                return 0.99;
            }
        });

        $user = User::factory()->create();
        $game = Game::factory()->create(['slug' => 'dice', 'status' => 'active']);
        $wallet = Wallet::factory()->for($user)->create(['balance' => '500.00000000']);

        foreach (['10.00000000', '20.00000000', '25.00000000'] as $index => $amount) {
            Bet::factory()->for($user)->for($game)->loss()->create([
                'bet_amount' => $amount,
                'created_at' => now()->subMinutes(30 - $index),
            ]);
        }
        Transaction::factory()->for($user)->for($wallet)->create([
            'type' => TransactionType::Deposit->value,
            'status' => TransactionStatus::Confirmed->value,
            'created_at' => now(),
        ]);

        $response = $this->actingAs($user)->postJson("/api/v1/games/{$game->id}/bet", [
            'bet_amount' => '40.00',
            'client_seed' => 'client-seed',
            'payload' => ['target' => 50],
        ]);

        $response->assertOk()
            ->assertJsonPath('bet.status', 'settled');

        $this->assertDatabaseHas('transactions', [
            'user_id' => $user->id,
            'type' => TransactionType::BetDebit->value,
            'amount' => '40.00',
            'reason' => 'game_bet',
        ]);

        $this->assertDatabaseHas('wallets', [
            'user_id' => $user->id,
            'currency' => 'USD',
            'balance' => '460.00000000',
        ]);

        $this->assertDatabaseHas('risk_events', [
            'user_id' => $user->id,
            'type' => 'chasing_losses',
        ]);

        $this->assertDatabaseHas('interventions', [
            'user_id' => $user->id,
            'type' => 'circuit_breaker',
            'status' => 'active',
        ]);

        $this->assertGreaterThanOrEqual(75, RiskEvent::query()->where('user_id', $user->id)->first()->score_delta);
    }
}
