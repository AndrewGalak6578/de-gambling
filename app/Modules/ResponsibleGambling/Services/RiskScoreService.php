<?php

namespace App\Modules\ResponsibleGambling\Services;

use App\Models\RiskScoreOverride;
use Illuminate\Support\Facades\DB;

class RiskScoreService
{
    public function calculateForUser(int $userId): int
    {
        $override = $this->overrideForUser($userId);

        if ($override?->disabled) {
            return 0;
        }

        $score = $this->calculateRawForUser($userId) + (int) ($override?->score_adjustment ?? 0);

        return max(0, min($score, 100));
    }

    public function calculateRawForUser(int $userId): int
    {
        $recentLosses = DB::table('bets')
            ->where('user_id', $userId)
            ->where('created_at', '>=', now()->subHours(24))
            ->where('payout_amount', '=', 0)
            ->count();

        $recentDeposits = DB::table('transactions')
            ->where('user_id', $userId)
            ->where('type', 'deposit')
            ->where('created_at', '>=', now()->subHours(24))
            ->count();

        $lossChaseIndex = $this->lossChaseIndex($userId);
        $lateNightBets = DB::table('bets')
            ->where('user_id', $userId)
            ->where('created_at', '>=', now()->subHours(24))
            ->get(['created_at'])
            ->filter(fn ($bet) => (int) date('G', strtotime((string) $bet->created_at)) <= 5)
            ->count();

        $score = ($recentLosses * 5)
            + ($recentDeposits * 10)
            + ($lossChaseIndex * 50)
            + ($lateNightBets * 3);

        return min($score, 100);
    }

    public function shouldTriggerCircuitBreaker(int $userId): bool
    {
        return $this->calculateForUser($userId) >= 75;
    }

    public function riskTypeForUser(int $userId): ?string
    {
        if ($this->overrideForUser($userId)?->disabled) {
            return null;
        }

        if ($this->lossChaseIndex($userId) > 0) {
            return 'chasing_losses';
        }

        $recentDeposits = DB::table('transactions')
            ->where('user_id', $userId)
            ->where('type', 'deposit')
            ->where('created_at', '>=', now()->subHours(24))
            ->count();

        if ($recentDeposits >= 3) {
            return 'deposit_spike';
        }

        if ($this->calculateForUser($userId) >= 75) {
            return 'high_risk';
        }

        return null;
    }

    public function overrideForUser(int $userId): ?RiskScoreOverride
    {
        return RiskScoreOverride::query()->where('user_id', $userId)->first();
    }

    private function lossChaseIndex(int $userId): int
    {
        $bets = DB::table('bets')
            ->where('user_id', $userId)
            ->where('created_at', '>=', now()->subHours(24))
            ->orderByDesc('created_at')
            ->limit(4)
            ->get(['bet_amount', 'payout_amount'])
            ->reverse()
            ->values();

        if ($bets->count() < 4) {
            return 0;
        }

        $allLosses = $bets->every(fn ($bet) => bccomp((string) $bet->payout_amount, '0', 8) === 0);

        if (! $allLosses) {
            return 0;
        }

        $firstAmount = (string) $bets->first()->bet_amount;
        $lastAmount = (string) $bets->last()->bet_amount;

        return bccomp($lastAmount, bcmul($firstAmount, '3', 8), 8) >= 0 ? 1 : 0;
    }
}
