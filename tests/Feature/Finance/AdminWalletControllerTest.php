<?php

namespace Tests\Feature\Finance;

use App\Models\User;
use App\Models\Wallet;
use App\Modules\Finance\Enums\TransactionType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\CreatesFinanceFixtures;
use Tests\TestCase;

class AdminWalletControllerTest extends TestCase
{
    use CreatesFinanceFixtures;
    use RefreshDatabase;

    public function test_non_admin_is_forbidden(): void
    {
        $user = User::factory()->create();

        $this->actingAs(User::factory()->create())
            ->postJson("/api/v1/admin/users/{$user->id}/wallet/credit", [
                'amount' => '10.00',
                'reason' => 'test credit',
            ])
            ->assertForbidden();
    }

    public function test_admin_credit_creates_transaction_and_updates_wallet_balance(): void
    {
        $user = User::factory()->create();
        Wallet::factory()->for($user)->create(['balance' => '25.00000000']);

        $this->actingAs($this->adminUser())
            ->postJson("/api/v1/admin/users/{$user->id}/wallet/credit", [
                'amount' => '40.50',
                'reason' => 'manual top-up',
            ])
            ->assertOk()
            ->assertJsonPath('balance', '65.5');

        $this->assertDatabaseHas('wallets', ['user_id' => $user->id, 'balance' => '65.50000000']);
        $this->assertDatabaseHas('transactions', [
            'user_id' => $user->id,
            'type' => TransactionType::AdminCredit->value,
            'amount' => '40.50000000',
            'reason' => 'manual top-up',
        ]);
    }

    #[DataProvider('validCreditBoundaryAmounts')]
    public function test_valid_admin_credit_boundary_amounts_are_accepted(string $amount, string $expectedBalance): void
    {
        $user = User::factory()->create();
        Wallet::factory()->for($user)->create(['balance' => '0.00000000']);

        $this->actingAs($this->adminUser())
            ->postJson("/api/v1/admin/users/{$user->id}/wallet/credit", [
                'amount' => $amount,
                'reason' => 'boundary credit',
            ])
            ->assertOk();

        $this->assertDatabaseHas('wallets', [
            'user_id' => $user->id,
            'balance' => $expectedBalance,
        ]);
        $this->assertDatabaseHas('transactions', [
            'user_id' => $user->id,
            'type' => TransactionType::AdminCredit->value,
            'amount' => $expectedBalance,
            'reason' => 'boundary credit',
        ]);
    }

    public static function validCreditBoundaryAmounts(): array
    {
        return [
            'min boundary' => ['0.01', '0.01000000'],
            'min plus one' => ['1.01', '1.01000000'],
            'max minus one' => ['999999.99', '999999.99000000'],
            'max boundary' => ['1000000', '1000000.00000000'],
        ];
    }

    #[DataProvider('invalidCreditPayloads')]
    public function test_invalid_admin_credit_payload_is_rejected(array $payload): void
    {
        $user = User::factory()->create();

        $this->actingAs($this->adminUser())
            ->postJson("/api/v1/admin/users/{$user->id}/wallet/credit", $payload)
            ->assertUnprocessable();
    }

    public static function invalidCreditPayloads(): array
    {
        return [
            'zero' => [['amount' => '0', 'reason' => 'bad']],
            'negative' => [['amount' => '-1', 'reason' => 'bad']],
            'non_numeric' => [['amount' => 'abc', 'reason' => 'bad']],
            'above_max' => [['amount' => '1000000.01', 'reason' => 'bad']],
            'missing_reason' => [['amount' => '10.00']],
        ];
    }
}
