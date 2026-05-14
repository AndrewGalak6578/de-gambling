<?php

namespace App\Modules\Finance\Contracts;

use App\Modules\Finance\Enums\TransactionStatus;
use App\Modules\Finance\Enums\TransactionType;

interface BalanceServiceInterface
{
    public function getBalance(int $userId, string $currency = 'USDT'): string;

    public function debit(
        int $userId,
        string $amount,
        string $currency,
        TransactionType $type,
        string $reason,
        array $meta = [],
        TransactionStatus $status = TransactionStatus::Confirmed,
    ): void;

    public function credit(
        int $userId,
        string $amount,
        string $currency,
        TransactionType $type,
        string $reason,
        array $meta = [],
        TransactionStatus $status = TransactionStatus::Confirmed,
    ): void;
}
