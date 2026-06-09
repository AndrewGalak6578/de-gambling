<?php

namespace Tests\Feature\Finance;

use App\Models\Bet;
use App\Models\DepositInvoice;
use App\Models\Game;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use App\Modules\Finance\Enums\DepositInvoiceStatus;
use App\Modules\Finance\Enums\TransactionStatus;
use App\Modules\Finance\Enums\TransactionType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesFinanceFixtures;
use Tests\TestCase;

class AdminFinanceOverviewControllerTest extends TestCase
{
    use CreatesFinanceFixtures;
    use RefreshDatabase;

    public function test_non_admin_is_forbidden_from_finance_overview(): void
    {
        $this->actingAs(User::factory()->create())
            ->getJson('/api/v1/admin/finance-overview')
            ->assertForbidden();
    }

    public function test_admin_can_view_finance_overview_histories_and_stats(): void
    {
        $admin = $this->adminUser();
        $user = User::factory()->create();
        $game = Game::factory()->create();
        $wallet = Wallet::factory()->for($user)->create(['balance' => '125.00000000']);

        Bet::factory()->for($user)->for($game)->create([
            'bet_amount' => '20.00000000',
            'payout_amount' => '30.00000000',
            'status' => 'settled',
        ]);
        DepositInvoice::factory()->for($user)->create([
            'expected_usd' => '50.00000000',
            'status' => DepositInvoiceStatus::Paid->value,
        ]);
        Transaction::factory()->for($user)->for($wallet)->create([
            'type' => TransactionType::Deposit->value,
            'status' => TransactionStatus::Confirmed->value,
            'amount' => '50.00000000',
        ]);
        Transaction::factory()->for($user)->for($wallet)->withdrawal()->create([
            'status' => TransactionStatus::Pending->value,
            'amount' => '25.00000000',
        ]);

        $response = $this->actingAs($admin)
            ->getJson('/api/v1/admin/finance-overview')
            ->assertOk();

        $response->assertJsonPath('stats.users_count', 2)
            ->assertJsonPath('stats.wallet_balance_usd', '125.00000000')
            ->assertJsonPath('stats.deposits_usd', '50.00000000')
            ->assertJsonPath('stats.withdrawals_usd', '25.00000000')
            ->assertJsonPath('stats.bet_volume_usd', '20.00000000')
            ->assertJsonPath('stats.payouts_usd', '30.00000000')
            ->assertJsonCount(1, 'bets')
            ->assertJsonCount(1, 'deposit_invoices')
            ->assertJsonCount(1, 'withdrawals')
            ->assertJsonCount(2, 'transactions');
    }
}
