<?php

namespace App\Modules\Finance\Services;

use App\Modules\Finance\Contracts\GameSettlementServiceInterface;
use App\Modules\Finance\Enums\TransactionStatus;
use App\Modules\Finance\Enums\TransactionType;
use Illuminate\Support\Facades\DB;

class GameSettlementService implements GameSettlementServiceInterface
{
    public function __construct(
        private WalletBalanceService $walletBalanceService,
        private TransactionLogService $transactionLogService,
    ) {}

    public function settleBet(int $userId, int $gameId, string $betAmount, string $payoutAmount, array $result): void
    {
        DB::transaction(function () use ($userId, $gameId, $betAmount, $payoutAmount, $result) {
            $walletId = $this->walletBalanceService->decreaseBalance($userId, $betAmount, 'USDT');

            $this->transactionLogService->record(
                userId: $userId,
                walletId: $walletId,
                type: TransactionType::BetDebit,
                status: TransactionStatus::Confirmed,
                amount: $betAmount,
                currency: 'USDT',
                reason: 'game_bet',
                meta: [
                    'game_id' => $gameId,
                    'result' => $result,
                ],
            );

            if (bccomp($payoutAmount, '0', 8) > 0) {
                $walletId = $this->walletBalanceService->increaseBalance($userId, $payoutAmount, 'USDT');

                $this->transactionLogService->record(
                    userId: $userId,
                    walletId: $walletId,
                    type: TransactionType::BetPayout,
                    status: TransactionStatus::Confirmed,
                    amount: $payoutAmount,
                    currency: 'USDT',
                    reason: 'game_payout',
                    meta: [
                        'game_id' => $gameId,
                        'result' => $result,
                    ],
                );
            }
        });
    }
}
