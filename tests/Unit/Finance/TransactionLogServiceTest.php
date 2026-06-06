<?php

namespace Tests\Unit\Finance;

use App\Models\User;
use App\Models\Wallet;
use App\Modules\Finance\Enums\TransactionStatus;
use App\Modules\Finance\Enums\TransactionType;
use App\Modules\Finance\Services\TransactionLogService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransactionLogServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_record_creates_transaction_with_meta(): void
    {
        $user = User::factory()->create();
        $wallet = Wallet::factory()->for($user)->create();

        $id = app(TransactionLogService::class)->record(
            userId: $user->id,
            walletId: $wallet->id,
            type: TransactionType::Deposit,
            status: TransactionStatus::Confirmed,
            amount: '15.00000000',
            currency: 'USD',
            reason: 'Deposit invoice paid.',
            meta: ['provider_invoice_id' => 'inv_123'],
        );

        $this->assertDatabaseHas('transactions', [
            'id' => $id,
            'user_id' => $user->id,
            'wallet_id' => $wallet->id,
            'type' => TransactionType::Deposit->value,
            'status' => TransactionStatus::Confirmed->value,
            'amount' => '15.00000000',
            'meta' => json_encode(['provider_invoice_id' => 'inv_123']),
        ]);
    }
}
