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
