<?php

namespace App\Modules\Finance\Services;

use Illuminate\Support\Facades\DB;

class WalletBalanceService
{
    public function getBalance(int $userId, string $currency = 'USDT'): string
    {
        $wallet = DB::table('wallets')
            ->where('user_id', $userId)
            ->where('currency', $currency)
            ->first();

        return $wallet?->balance ?? '0.00000000';
    }

    public function decreaseBalance(int $userId, string $amount, string $currency): int
    {
        $wallet = DB::table('wallets')
            ->where('user_id', $userId)
            ->where('currency', $currency)
            ->lockForUpdate()
            ->first();

        if (! $wallet || bccomp($wallet->balance, $amount, 8) < 0) {
            throw new \RuntimeException('Insufficient balance.');
        }

        DB::table('wallets')->where('id', $wallet->id)->update([
            'balance' => bcsub($wallet->balance, $amount, 8),
            'updated_at' => now(),
        ]);

        return $wallet->id;
    }

    public function increaseBalance(int $userId, string $amount, string $currency): int
    {
        $wallet = DB::table('wallets')
            ->where('user_id', $userId)
            ->where('currency', $currency)
            ->lockForUpdate()
            ->first();

        if (! $wallet) {
            $walletId = DB::table('wallets')->insertGetId([
                'user_id' => $userId,
                'currency' => $currency,
                'balance' => '0.00000000',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $wallet = DB::table('wallets')
                ->where('id', $walletId)
                ->lockForUpdate()
                ->first();
        }

        DB::table('wallets')->where('id', $wallet->id)->update([
            'balance' => bcadd($wallet->balance, $amount, 8),
            'updated_at' => now(),
        ]);

        return $wallet->id;
    }
}
