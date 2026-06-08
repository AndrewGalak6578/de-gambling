<?php

namespace Tests\Unit\ResponsibleGambling;

use App\Models\Bet;
use App\Models\Game;
use App\Models\Intervention;
use App\Models\RiskScoreOverride;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use App\Modules\Finance\Enums\TransactionStatus;
use App\Modules\Finance\Enums\TransactionType;
use App\Modules\ResponsibleGambling\Services\ResponsibleGamblingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Exceptions\HttpResponseException;
use Tests\TestCase;

class ResponsibleGamblingServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_ensure_can_bet_throws_when_blocking_intervention_is_active(): void
    {
        $user = User::factory()->create();
        Intervention::factory()->for($user)->create(['type' => 'admin_bet_block']);

        try {
            app(ResponsibleGamblingService::class)->ensureCanBet($user);
            $this->fail('Expected betting to be blocked.');
        } catch (HttpResponseException $exception) {
            $this->assertSame(423, $exception->getResponse()->getStatusCode());
        }
    }

    public function test_ensure_can_deposit_throws_when_deposit_block_is_active(): void
    {
        $user = User::factory()->create();
        Intervention::factory()->for($user)->create(['type' => 'admin_deposit_block']);

        try {
            app(ResponsibleGamblingService::class)->ensureCanDeposit($user);
            $this->fail('Expected deposit to be blocked.');
        } catch (HttpResponseException $exception) {
            $this->assertSame(423, $exception->getResponse()->getStatusCode());
        }
    }

    public function test_analyze_settled_bet_creates_risk_event_and_circuit_breaker_for_high_risk(): void
    {
        $user = User::factory()->create();
        $game = Game::factory()->create();
        $wallet = Wallet::factory()->for($user)->create();

        foreach (['10.00000000', '15.00000000', '20.00000000', '30.00000000'] as $index => $amount) {
            Bet::factory()->for($user)->for($game)->loss()->create([
                'bet_amount' => $amount,
                'created_at' => now()->subMinutes(10 - $index),
            ]);
        }
        Transaction::factory()->for($user)->for($wallet)->create([
            'type' => TransactionType::Deposit->value,
            'status' => TransactionStatus::Confirmed->value,
            'created_at' => now(),
        ]);

        $bet = Bet::query()->where('user_id', $user->id)->latest()->firstOrFail();

        $score = app(ResponsibleGamblingService::class)->analyzeSettledBet($bet);

        $this->assertGreaterThanOrEqual(75, $score);
        $this->assertDatabaseHas('risk_events', [
            'user_id' => $user->id,
            'type' => 'chasing_losses',
        ]);
        $this->assertDatabaseHas('interventions', [
            'user_id' => $user->id,
            'type' => 'circuit_breaker',
            'status' => 'active',
        ]);
    }

    public function test_disabled_risk_override_suppresses_risk_event_creation(): void
    {
        $user = User::factory()->create();
        $game = Game::factory()->create();
        RiskScoreOverride::factory()->for($user)->create(['disabled' => true]);

        foreach (['10.00000000', '15.00000000', '20.00000000', '30.00000000'] as $index => $amount) {
            Bet::factory()->for($user)->for($game)->loss()->create([
                'bet_amount' => $amount,
                'created_at' => now()->subMinutes(10 - $index),
            ]);
        }

        $bet = Bet::query()->where('user_id', $user->id)->latest()->firstOrFail();

        $score = app(ResponsibleGamblingService::class)->analyzeSettledBet($bet);

        $this->assertSame(0, $score);
        $this->assertDatabaseMissing('risk_events', [
            'user_id' => $user->id,
        ]);
        $this->assertDatabaseMissing('interventions', [
            'user_id' => $user->id,
            'type' => 'circuit_breaker',
        ]);
    }

    public function test_admin_win_limit_blocks_after_max_wins_reached(): void
    {
        $user = User::factory()->create();
        $game = Game::factory()->create();
        Intervention::factory()->for($user)->create([
            'type' => 'admin_win_limit',
            'payload' => ['max_wins' => 1, 'window' => 'day'],
        ]);
        Bet::factory()->for($user)->for($game)->win()->create([
            'bet_amount' => '10.00000000',
            'payout_amount' => '20.00000000',
            'created_at' => now(),
        ]);

        try {
            app(ResponsibleGamblingService::class)->ensureCanBet($user);
            $this->fail('Expected win limit to block betting.');
        } catch (HttpResponseException $exception) {
            $this->assertSame(423, $exception->getResponse()->getStatusCode());
        }
    }
}
