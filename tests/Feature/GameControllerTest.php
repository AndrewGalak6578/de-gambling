<?php

namespace Tests\Feature;

use App\Models\Game;
use App\Models\User;
use App\Models\Wallet;
use Database\Seeders\GameSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class GameControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Game $dice;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(GameSeeder::class);

        $this->user = User::factory()->create();
        Wallet::create([
            'user_id' => $this->user->id,
            'currency' => 'USD',
            'balance' => '100.00000000',
        ]);

        $this->dice = Game::where('slug', 'dice')->first();
    }

    public function test_unauthenticated_user_cannot_bet(): void
    {
        $response = $this->postJson("/api/v1/games/{$this->dice->id}/bet", [
            'bet_amount' => '1.00',
            'client_seed' => 'test-seed',
        ]);

        $response->assertStatus(401);
    }

    public function test_can_place_bet_and_get_result(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson("/api/v1/games/{$this->dice->id}/bet", [
            'bet_amount' => '1.00',
            'client_seed' => 'test-client-seed',
            'payload' => ['target' => 50, 'condition' => 'under'],
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'bet' => [
                    'id', 'user_id', 'game_id', 'bet_amount', 'payout_amount',
                    'currency', 'status', 'server_seed_hash', 'client_seed', 'result',
                ],
                'outcome' => [
                    'payout_multiplier', 'state' => ['roll', 'is_win'], 'is_finished',
                ],
            ]);

        $this->assertSame('settled', $response->json('bet.status'));
        $this->assertSame('USD', $response->json('bet.currency'));
    }

    public function test_bet_creates_transaction_ledger(): void
    {
        Sanctum::actingAs($this->user);

        $this->postJson("/api/v1/games/{$this->dice->id}/bet", [
            'bet_amount' => '10.00',
            'client_seed' => 'test-seed',
            'payload' => ['target' => 50, 'condition' => 'under'],
        ]);

        // A bet_debit transaction should exist
        $this->assertDatabaseHas('transactions', [
            'user_id' => $this->user->id,
            'type' => 'bet_debit',
            'amount' => '10.00000000',
        ]);
    }

    public function test_insufficient_balance_returns_clean_error(): void
    {
        Sanctum::actingAs($this->user);

        // Try betting more than the 100 balance
        $response = $this->postJson("/api/v1/games/{$this->dice->id}/bet", [
            'bet_amount' => '999.00',
            'client_seed' => 'test-seed',
            'payload' => ['target' => 50, 'condition' => 'under'],
        ]);

        $response->assertStatus(402)
            ->assertJson([
                'message' => 'Insufficient balance.',
            ]);
    }

    public function test_validation_requires_bet_amount_and_client_seed(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson("/api/v1/games/{$this->dice->id}/bet", []);

        $response->assertStatus(422);
    }

    public function test_bet_on_nonexistent_game_returns_404(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/v1/games/999/bet', [
            'bet_amount' => '1.00',
            'client_seed' => 'test-seed',
        ]);

        $response->assertStatus(404);
    }

    public function test_cannot_bet_twice_with_pending_bet(): void
    {
        Sanctum::actingAs($this->user);

        $this->postJson("/api/v1/games/{$this->dice->id}/bet", [
            'bet_amount' => '1.00',
            'client_seed' => 'test-seed',
            'payload' => ['target' => 50, 'condition' => 'under'],
        ]);

        // Second bet should work since dice finishes immediately
        $response = $this->postJson("/api/v1/games/{$this->dice->id}/bet", [
            'bet_amount' => '1.00',
            'client_seed' => 'test-seed-2',
            'payload' => ['target' => 50, 'condition' => 'under'],
        ]);

        $response->assertStatus(200);
    }

    public function test_games_index_returns_active_games(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->getJson('/api/v1/games');

        $response->assertStatus(200);
        $response->assertJsonCount(4); // dice + spin-to-win + slots + blackjack from GameSeeder
    }

    public function test_server_seed_is_revealed_in_result(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson("/api/v1/games/{$this->dice->id}/bet", [
            'bet_amount' => '1.00',
            'client_seed' => 'verify-seed',
            'payload' => ['target' => 50, 'condition' => 'under'],
        ]);

        $result = $response->json('bet.result');
        $this->assertNotNull($result['server_seed']);
        $this->assertNotNull($response->json('bet.server_seed_hash'));

        // Verify: hash(server_seed) === server_seed_hash
        $this->assertSame(
            hash('sha256', $result['server_seed']),
            $response->json('bet.server_seed_hash')
        );
    }
}
