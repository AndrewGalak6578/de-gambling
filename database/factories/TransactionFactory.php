<?php

namespace Database\Factories;

use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use App\Modules\Finance\Enums\TransactionStatus;
use App\Modules\Finance\Enums\TransactionType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Transaction>
 */
class TransactionFactory extends Factory
{
    protected $model = Transaction::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'wallet_id' => Wallet::factory(),
            'type' => TransactionType::Deposit->value,
            'status' => TransactionStatus::Confirmed->value,
            'amount' => '25.00000000',
            'currency' => 'USD',
            'tx_hash' => null,
            'reason' => 'Factory transaction.',
            'meta' => [],
        ];
    }

    public function withdrawal(): static
    {
        return $this->state(fn () => [
            'type' => TransactionType::Withdrawal->value,
            'status' => TransactionStatus::Pending->value,
            'reason' => 'Withdrawal pending manual settlement.',
            'meta' => [
                'destination' => 'test-destination',
                'settlement' => [
                    'status' => 'manual_review',
                ],
            ],
        ]);
    }
}
