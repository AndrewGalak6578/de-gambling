<?php

namespace App\Modules\Finance\Services;

use App\Modules\Finance\Contracts\BalanceServiceInterface;
use App\Modules\Finance\Contracts\GameSettlementServiceInterface;
use Illuminate\Support\Facades\DB;

class GameSettlementService implements GameSettlementServiceInterface
{
    public function __construct(private BalanceServiceInterface $balanceService) {}

    public function settleBet(int $userId, int $gameId, string $betAmount, string $payoutAmount, array $result): void
    {
        DB::transaction(function () use ($userId, $gameId, $betAmount, $payoutAmount, $result) {
            $this->balanceService->debit($userId, $betAmount, 'USDT', 'game_bet', [
                'game_id' => $gameId,
                'result' => $result,
            ]);

            if (bccomp($payoutAmount, '0', 8) > 0) {
                $this->balanceService->credit($userId, $payoutAmount, 'USDT', 'game_payout', [
                    'game_id' => $gameId,
                    'result' => $result,
                ]);
            }
        });
    }
}
