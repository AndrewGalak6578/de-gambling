<?php

namespace App\Modules\Finance\Services;

use App\Modules\Finance\Contracts\BalanceServiceInterface;
use Illuminate\Support\Facades\DB;

class BalanceService implements BalanceServiceInterface
{
    public function getBalance(int $userId, string $currency = 'USDT'): string
    {
        $wallet = DB::table('wallets')
            ->where('user_id', $userId)
            ->where('currency', $currency)
            ->first();

        return $wallet?->balance ?? '0.00000000';
    }

    public function debit(int $userId, string $amount, string $currency, string $reason, array $meta = []): void
    {
        DB::transaction(function () use ($userId, $amount, $currency, $reason, $meta) {
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

            DB::table('transactions')->insert([
                'user_id' => $userId,
                'wallet_id' => $wallet->id,
                'type' => 'bet_debit',
                'status' => 'confirmed',
                'amount' => $amount,
                'currency' => $currency,
                'reason' => $reason,
                'meta' => json_encode($meta),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });
    }

    public function credit(int $userId, string $amount, string $currency, string $reason, array $meta = []): void
    {
        DB::transaction(function () use ($userId, $amount, $currency, $reason, $meta) {
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
                $wallet = DB::table('wallets')->where('id', $walletId)->lockForUpdate()->first();
            }

            DB::table('wallets')->where('id', $wallet->id)->update([
                'balance' => bcadd($wallet->balance, $amount, 8),
                'updated_at' => now(),
            ]);

            DB::table('transactions')->insert([
                'user_id' => $userId,
                'wallet_id' => $wallet->id,
                'type' => 'bet_payout',
                'status' => 'confirmed',
                'amount' => $amount,
                'currency' => $currency,
                'reason' => $reason,
                'meta' => json_encode($meta),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });
    }
}
