<?php

namespace App\Modules\ResponsibleGambling\Services;

use App\Models\Bet;
use App\Models\Intervention;
use App\Models\RiskEvent;
use App\Models\User;
use Illuminate\Http\Exceptions\HttpResponseException;

class ResponsibleGamblingService
{
    public function __construct(
        private RiskScoreService $riskScoreService,
    ) {}

    public function ensureCanBet(User $user): void
    {
        $intervention = $this->activeBlockingIntervention($user->id, ['self_exclusion', 'circuit_breaker']);

        if ($intervention !== null) {
            $this->throwBlocked('Betting is paused by an active responsible gambling intervention.');
        }
    }

    public function ensureCanDeposit(User $user): void
    {
        $intervention = $this->activeBlockingIntervention($user->id, ['circuit_breaker']);

        if ($intervention !== null) {
            $this->throwBlocked('Deposits are paused by the responsible gambling circuit breaker.');
        }
    }

    public function analyzeSettledBet(Bet $bet): int
    {
        $score = $this->riskScoreService->calculateForUser($bet->user_id);
        $riskType = $this->riskScoreService->riskTypeForUser($bet->user_id);

        if ($riskType !== null) {
            RiskEvent::create([
                'user_id' => $bet->user_id,
                'type' => $riskType,
                'score_delta' => min($score, 100),
                'payload' => [
                    'bet_id' => $bet->id,
                    'bet_amount' => (string) $bet->bet_amount,
                    'payout_amount' => (string) $bet->payout_amount,
                    'risk_score' => $score,
                ],
            ]);
        }

        if ($score >= 75) {
            $this->createCircuitBreaker($bet->user_id, $score, $riskType);
        }

        return $score;
    }

    /**
     * @param  array<int, string>  $types
     */
    public function activeBlockingIntervention(int $userId, array $types): ?Intervention
    {
        return Intervention::query()
            ->where('user_id', $userId)
            ->whereIn('type', $types)
            ->where('status', 'active')
            ->where(function ($query) {
                $query->whereNull('starts_at')
                    ->orWhere('starts_at', '<=', now());
            })
            ->where(function ($query) {
                $query->whereNull('ends_at')
                    ->orWhere('ends_at', '>', now());
            })
            ->latest()
            ->first();
    }

    private function createCircuitBreaker(int $userId, int $score, ?string $riskType): Intervention
    {
        $existing = $this->activeBlockingIntervention($userId, ['circuit_breaker']);

        if ($existing !== null) {
            return $existing;
        }

        return Intervention::create([
            'user_id' => $userId,
            'type' => 'circuit_breaker',
            'status' => 'active',
            'starts_at' => now(),
            'ends_at' => now()->addHours(24),
            'payload' => [
                'risk_score' => $score,
                'risk_type' => $riskType,
                'message' => 'Reality check: activity indicates elevated gambling risk.',
            ],
        ]);
    }

    private function throwBlocked(string $message): never
    {
        throw new HttpResponseException(response()->json([
            'message' => $message,
        ], 423));
    }
}
