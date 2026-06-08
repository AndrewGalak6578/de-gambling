<?php

namespace Tests\Unit\ResponsibleGambling;

use App\Models\Bet;
use App\Models\Game;
use App\Models\RiskScoreOverride;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use App\Modules\Finance\Enums\TransactionStatus;
use App\Modules\Finance\Enums\TransactionType;
use App\Modules\ResponsibleGambling\Services\RiskScoreService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RiskScoreServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_low_risk_user_has_zero_score(): void
    {
        $user = User::factory()->create();

        $this->assertSame(0, app(RiskScoreService::class)->calculateForUser($user->id));
    }

    public function test_medium_risk_user_scores_from_recent_deposits(): void
    {
        $user = User::factory()->create();
        $wallet = Wallet::factory()->for($user)->create();

        Transaction::factory()->count(4)->for($user)->for($wallet)->create([
            'type' => TransactionType::Deposit->value,
            'status' => TransactionStatus::Confirmed->value,
            'created_at' => now(),
        ]);

        $service = app(RiskScoreService::class);

        $this->assertSame(40, $service->calculateForUser($user->id));
        $this->assertSame('deposit_spike', $service->riskTypeForUser($user->id));
        $this->assertFalse($service->shouldTriggerCircuitBreaker($user->id));
    }

    public function test_high_risk_user_scores_from_chasing_losses(): void
    {
        $user = User::factory()->create();
        $game = Game::factory()->create();
        $wallet = Wallet::factory()->for($user)->create();
        $baseTime = now()->setTime(12, 0);

        if ($baseTime->isFuture()) {
            $baseTime = now()->subDay()->setTime(12, 0);
        }

        foreach (['10.00000000', '15.00000000', '20.00000000', '30.00000000'] as $index => $amount) {
            Bet::factory()->for($user)->for($game)->loss()->create([
                'bet_amount' => $amount,
                'created_at' => $baseTime->copy()->addMinutes($index),
            ]);
        }
        Transaction::factory()->for($user)->for($wallet)->create([
            'type' => TransactionType::Deposit->value,
            'status' => TransactionStatus::Confirmed->value,
            'created_at' => now(),
        ]);

        $service = app(RiskScoreService::class);

        $this->assertSame(80, $service->calculateForUser($user->id));
        $this->assertSame('chasing_losses', $service->riskTypeForUser($user->id));
        $this->assertTrue($service->shouldTriggerCircuitBreaker($user->id));
    }

    public function test_override_adjusts_score_and_disabled_override_returns_zero(): void
    {
        $user = User::factory()->create();
        $wallet = Wallet::factory()->for($user)->create();

        Transaction::factory()->count(3)->for($user)->for($wallet)->create([
            'type' => TransactionType::Deposit->value,
            'created_at' => now(),
        ]);

        RiskScoreOverride::factory()->for($user)->create(['score_adjustment' => -10, 'disabled' => false]);

        $service = app(RiskScoreService::class);
        $this->assertSame(20, $service->calculateForUser($user->id));

        RiskScoreOverride::query()->where('user_id', $user->id)->update(['disabled' => true]);

        $this->assertSame(0, $service->calculateForUser($user->id));
        $this->assertNull($service->riskTypeForUser($user->id));
    }
}
