<?php

namespace App\Modules\Finance\Services;

use App\Modules\Finance\Enums\TransactionStatus;
use App\Modules\Finance\Enums\TransactionType;
use Illuminate\Support\Facades\DB;

class TransactionLogService
{
    public function record(
        int $userId,
        int $walletId,
        TransactionType $type,
        TransactionStatus $status,
        string $amount,
        string $currency,
        string $reason,
        array $meta = [],
    ): void {
        DB::table('transactions')->insert([
            'user_id' => $userId,
            'wallet_id' => $walletId,
            'type' => $type->value,
            'status' => $status->value,
            'amount' => $amount,
            'currency' => $currency,
            'reason' => $reason,
            'meta' => json_encode($meta),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
