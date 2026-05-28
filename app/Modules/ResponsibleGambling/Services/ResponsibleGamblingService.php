<?php

namespace App\Modules\ResponsibleGambling\Services;

use App\Models\Bet;
use App\Models\Intervention;
use App\Models\RiskEvent;
use App\Models\User;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Collection;

class ResponsibleGamblingService
{
    public function __construct(
        private RiskScoreService $riskScoreService,
    ) {}

    public function ensureCanBet(User $user): void
    {
        $intervention = $this->activeBlockingIntervention($user->id, [
            'self_exclusion',
            'circuit_breaker',
            'admin_bet_block',
            'admin_cool_off',
        ]);

        if ($intervention !== null) {
            $this->throwBlocked('Betting is paused by an active responsible gambling intervention.');
        }

        $winLimit = $this->activeWinLimitViolation($user);

        if ($winLimit !== null) {
            $this->throwBlocked('Betting is paused because the admin win limit has been reached.');
        }
    }

    public function ensureCanDeposit(User $user): void
    {
        $intervention = $this->activeBlockingIntervention($user->id, [
            'circuit_breaker',
            'admin_deposit_block',
            'admin_cool_off',
        ]);

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
        return $this->activeInterventions($userId, $types)->first();
    }

    /**
     * @param  array<int, string>|null  $types
     * @return Collection<int, Intervention>
     */
    public function activeInterventions(int $userId, ?array $types = null): Collection
    {
        $query = Intervention::query()
            ->where('user_id', $userId)
            ->where('status', 'active')
            ->where(function ($query) {
                $query->whereNull('starts_at')
                    ->orWhere('starts_at', '<=', now());
            })
            ->where(function ($query) {
                $query->whereNull('ends_at')
                    ->orWhere('ends_at', '>', now());
            })
            ->latest();

        if ($types !== null) {
            $query->whereIn('type', $types);
        }

        return $query->get();
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

    private function activeWinLimitViolation(User $user): ?Intervention
    {
        $limits = $this->activeInterventions($user->id, ['admin_win_limit']);

        foreach ($limits as $limit) {
            $payload = is_array($limit->payload) ? $limit->payload : [];
            $maxWins = (int) ($payload['max_wins'] ?? 0);

            if ($maxWins < 1) {
                continue;
            }

            $wins = Bet::query()
                ->where('user_id', $user->id)
                ->where('status', 'settled')
                ->whereColumn('payout_amount', '>', 'bet_amount')
                ->when(($payload['window'] ?? 'day') === '24h', function ($query) {
                    $query->where('created_at', '>=', now()->subHours(24));
                }, function ($query) {
                    $query->whereDate('created_at', now()->toDateString());
                })
                ->count();

            if ($wins >= $maxWins) {
                return $limit;
            }
        }

        return null;
    }
}
