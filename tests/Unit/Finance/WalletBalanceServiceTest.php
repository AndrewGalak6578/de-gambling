<?php

namespace Tests\Unit\Finance;

use App\Models\User;
use App\Models\Wallet;
use App\Modules\Finance\Services\WalletBalanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class WalletBalanceServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_get_balance_returns_zero_when_wallet_does_not_exist(): void
    {
        $user = User::factory()->create();

        $this->assertSame('0.00000000', app(WalletBalanceService::class)->getBalance($user->id));
    }

    public function test_increase_balance_creates_wallet_and_adds_amount(): void
    {
        $user = User::factory()->create();

        $walletId = app(WalletBalanceService::class)->increaseBalance($user->id, '25.50000000', 'USD');

        $this->assertDatabaseHas('wallets', [
            'id' => $walletId,
            'user_id' => $user->id,
            'currency' => 'USD',
            'balance' => '25.50000000',
        ]);
    }

    public function test_decrease_balance_subtracts_amount(): void
    {
        $user = User::factory()->create();
        Wallet::factory()->for($user)->create(['balance' => '30.00000000']);

        app(WalletBalanceService::class)->decreaseBalance($user->id, '12.25000000', 'USD');

        $this->assertDatabaseHas('wallets', [
            'user_id' => $user->id,
            'balance' => '17.75000000',
        ]);
    }

    public function test_decrease_balance_throws_for_insufficient_balance(): void
    {
        $user = User::factory()->create();
        Wallet::factory()->for($user)->create(['balance' => '5.00000000']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Insufficient balance.');

        app(WalletBalanceService::class)->decreaseBalance($user->id, '6.00000000', 'USD');
    }
}
