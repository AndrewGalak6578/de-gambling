<?php

namespace App\Modules\ResponsibleGambling\Services;

use Illuminate\Support\Facades\DB;

class RiskScoreService
{
    public function calculateForUser(int $userId): int
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

        $score = ($recentLosses * 5) + ($recentDeposits * 10);

        return min($score, 100);
    }

    public function shouldTriggerCircuitBreaker(int $userId): bool
    {
        return $this->calculateForUser($userId) >= 75;
    }
}
