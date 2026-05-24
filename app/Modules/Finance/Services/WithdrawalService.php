<?php

namespace App\Modules\Finance\Services;

use App\Models\Transaction;
use App\Models\User;
use App\Modules\Finance\Enums\TransactionStatus;
use App\Modules\Finance\Enums\TransactionType;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class WithdrawalService
{
    private const INTERNAL_CURRENCY = 'USD';

    public function __construct(
        private WalletBalanceService $walletBalanceService,
        private TransactionLogService $transactionLogService,
    ) {}

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function requestWithdrawal(
        User $user,
        string $amount,
        string $destination,
        ?string $providerMethod = null,
        array $metadata = [],
    ): Transaction {
        return DB::transaction(function () use ($user, $amount, $destination, $providerMethod, $metadata) {
            $walletId = $this->walletBalanceService->decreaseBalance($user->id, $amount, self::INTERNAL_CURRENCY);

            $transactionId = $this->transactionLogService->record(
                userId: $user->id,
                walletId: $walletId,
                type: TransactionType::Withdrawal,
                status: TransactionStatus::Pending,
                amount: $amount,
                currency: self::INTERNAL_CURRENCY,
                reason: 'Withdrawal pending manual settlement.',
                meta: [
                    ...$metadata,
                    'destination' => $destination,
                    'provider_method' => $providerMethod,
                    'settlement' => [
                        'status' => 'manual_review',
                        'requested_at' => now()->toISOString(),
                    ],
                ],
            );

            return Transaction::query()->findOrFail($transactionId);
        });
    }

    /**
     * @return Collection<int, Transaction>
     */
    public function pendingQueue(): Collection
    {
        return Transaction::query()
            ->where('type', TransactionType::Withdrawal->value)
            ->where('status', TransactionStatus::Pending->value)
            ->latest()
            ->get();
    }

    public function approve(int $transactionId, User $admin, ?string $txHash = null): Transaction
    {
        return DB::transaction(function () use ($transactionId, $admin, $txHash) {
            $transaction = $this->pendingWithdrawalForUpdate($transactionId);
            $meta = $this->metaArray($transaction->meta);
            $settlement = $this->settlementMeta($meta);

            $transaction->status = TransactionStatus::Confirmed->value;
            $transaction->tx_hash = $txHash;
            $transaction->reason = 'Withdrawal approved by admin.';
            $transaction->meta = [
                ...$meta,
                'settlement' => [
                    ...$settlement,
                    'status' => 'approved',
                    'admin_user_id' => $admin->id,
                    'approved_at' => now()->toISOString(),
                ],
            ];
            $transaction->save();

            return $transaction;
        });
    }

    public function reject(int $transactionId, User $admin, ?string $reason = null): Transaction
    {
        return DB::transaction(function () use ($transactionId, $admin, $reason) {
            $transaction = $this->pendingWithdrawalForUpdate($transactionId);
            $meta = $this->metaArray($transaction->meta);
            $settlement = $this->settlementMeta($meta);

            $walletId = $this->walletBalanceService->increaseBalance(
                $transaction->user_id,
                (string) $transaction->amount,
                $transaction->currency,
            );

            $this->transactionLogService->record(
                userId: $transaction->user_id,
                walletId: $walletId,
                type: TransactionType::SettlementCorrection,
                status: TransactionStatus::Confirmed,
                amount: (string) $transaction->amount,
                currency: $transaction->currency,
                reason: 'Refund for rejected withdrawal.',
                meta: [
                    'withdrawal_transaction_id' => $transaction->id,
                    'admin_user_id' => $admin->id,
                    'reason' => $reason,
                ],
            );

            $transaction->status = TransactionStatus::Rejected->value;
            $transaction->reason = $reason ?? 'Withdrawal rejected by admin.';
            $transaction->meta = [
                ...$meta,
                'settlement' => [
                    ...$settlement,
                    'status' => 'rejected',
                    'admin_user_id' => $admin->id,
                    'rejected_at' => now()->toISOString(),
                    'reason' => $reason,
                ],
            ];
            $transaction->save();

            return $transaction;
        });
    }

    private function pendingWithdrawalForUpdate(int $transactionId): Transaction
    {
        $transaction = Transaction::query()
            ->whereKey($transactionId)
            ->where('type', TransactionType::Withdrawal->value)
            ->lockForUpdate()
            ->firstOrFail();

        if ($transaction->status !== TransactionStatus::Pending->value) {
            throw new RuntimeException('Withdrawal is not pending manual settlement.');
        }

        return $transaction;
    }

    /**
     * @param  array<string, mixed>|string|null  $meta
     * @return array<string, mixed>
     */
    private function metaArray(array|string|null $meta): array
    {
        if (is_array($meta)) {
            return $meta;
        }

        if (is_string($meta) && $meta !== '') {
            $decoded = json_decode($meta, true);

            return is_array($decoded) ? $decoded : [];
        }

        return [];
    }

    /**
     * @param  array<string, mixed>  $meta
     * @return array<string, mixed>
     */
    private function settlementMeta(array $meta): array
    {
        return isset($meta['settlement']) && is_array($meta['settlement'])
            ? $meta['settlement']
            : [];
    }
}
