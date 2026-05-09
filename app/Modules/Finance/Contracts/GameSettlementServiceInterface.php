<?php

namespace App\Modules\Finance\Contracts;

interface GameSettlementServiceInterface
{
    public function settleBet(int $userId, int $gameId, string $betAmount, string $payoutAmount, array $result): void;
}
