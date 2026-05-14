<?php

namespace App\Modules\Finance\Services;

use App\Modules\Finance\Contracts\BalanceServiceInterface;
use App\Modules\Finance\Enums\TransactionType;
use App\Modules\Finance\Enums\TransactionStatus;
use Illuminate\Support\Facades\DB;

class BalanceService implements BalanceServiceInterface
{
    public function __construct(
        private WalletBalanceService $walletBalanceService,
        private TransactionLogService $transactionLogService,
    ) {}

    public function getBalance(int $userId, string $currency = 'USDT'): string
    {
        return $this->walletBalanceService->getBalance($userId, $currency);
    }

    public function debit(
        int $userId,
        string $amount,
        string $currency,
        TransactionType $type,
        string $reason,
        array $meta = [],
        TransactionStatus $status = TransactionStatus::Confirmed,
    ): void {
        DB::transaction(function () use ($userId, $amount, $currency, $type, $reason, $meta, $status) {
            $walletId = $this->walletBalanceService->decreaseBalance($userId, $amount, $currency);

            $this->transactionLogService->record(
                userId: $userId,
                walletId: $walletId,
                type: $type,
                status: $status,
                amount: $amount,
                currency: $currency,
                reason: $reason,
                meta: $meta,
            );
        });
    }

    public function credit(
        int $userId,
        string $amount,
        string $currency,
        TransactionType $type,
        string $reason,
        array $meta = [],
        TransactionStatus $status = TransactionStatus::Confirmed,
    ): void {
        DB::transaction(function () use ($userId, $amount, $currency, $type, $reason, $meta, $status) {
            $walletId = $this->walletBalanceService->increaseBalance($userId, $amount, $currency);

            $this->transactionLogService->record(
                userId: $userId,
                walletId: $walletId,
                type: $type,
                status: $status,
                amount: $amount,
                currency: $currency,
                reason: $reason,
                meta: $meta,
            );
        });
    }
}
