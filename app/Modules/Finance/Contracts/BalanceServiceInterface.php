<?php

namespace App\Modules\Finance\Contracts;

interface BalanceServiceInterface
{
    public function getBalance(int $userId, string $currency = 'USDT'): string;

    public function debit(int $userId, string $amount, string $currency, string $reason, array $meta = []): void;

    public function credit(int $userId, string $amount, string $currency, string $reason, array $meta = []): void;
}
