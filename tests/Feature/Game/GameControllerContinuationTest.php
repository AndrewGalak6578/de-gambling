<?php

namespace Tests\Feature\Game;

use App\Models\Bet;
use App\Models\Game;
use App\Models\Intervention;
use App\Models\User;
use App\Models\Wallet;
use App\Modules\Game\Services\ProvablyFairService;
use Database\Seeders\GameSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GameControllerContinuationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Game $blackjack;
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
            'balance' => '500.00000000',
        ]);

        $this->blackjack = Game::where('slug', 'blackjack')->firstOrFail();
        $this->dice = Game::where('slug', 'dice')->firstOrFail();
    }

    public function test_disabled_user_cannot_hit_game_endpoints(): void
    {
        $this->user->update(['status' => 'disabled', 'disabled_reason' => 'test']);

        $response = $this->actingAs($this->user)
            ->postJson("/api/v1/games/{$this->dice->id}/bet", [
                'bet_amount' => '1.00',
                'client_seed' => 'demo',
                'payload' => ['target' => 50, 'condition' => 'under'],
            ]);

        $response->assertStatus(403)
            ->assertJsonPath('reason', 'test');
    }

    public function test_inactive_game_returns_404_on_show(): void
    {
        $this->dice->update(['status' => 'inactive']);

        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/games/{$this->dice->id}");

        $response->assertStatus(404);
    }

    public function test_inactive_game_returns_404_on_bet(): void
    {
        $this->dice->update(['status' => 'retired']);

        $response = $this->actingAs($this->user)
            ->postJson("/api/v1/games/{$this->dice->id}/bet", [
                'bet_amount' => '1.00',
                'client_seed' => 'demo',
                'payload' => ['target' => 50, 'condition' => 'under'],
            ]);

        $response->assertStatus(404);
    }

    public function test_responsible_gambling_intervention_blocks_bet(): void
    {
        Intervention::create([
            'user_id' => $this->user->id,
            'type' => 'self_exclusion',
            'status' => 'active',
            'starts_at' => now()->subHour(),
            'ends_at' => now()->addDay(),
            'payload' => ['source' => 'user'],
        ]);

        $response = $this->actingAs($this->user)
            ->postJson("/api/v1/games/{$this->dice->id}/bet", [
                'bet_amount' => '1.00',
                'client_seed' => 'demo',
                'payload' => ['target' => 50, 'condition' => 'under'],
            ]);

        $response->assertStatus(423)
            ->assertJsonPath('message', 'Betting is paused by an active responsible gambling intervention.');

        $this->assertDatabaseMissing('bets', ['user_id' => $this->user->id]);
    }

    public function test_blackjack_deal_returns_pending_bet_with_scrubbed_secrets(): void
    {
        $this->fixProvablyFairResultToNonBlackjack();

        $response = $this->actingAs($this->user)
            ->postJson("/api/v1/games/{$this->blackjack->id}/bet", [
                'bet_amount' => '10.00',
                'client_seed' => 'demo',
                'payload' => ['action' => 'deal'],
            ]);

        $response->assertOk()
            ->assertJsonPath('bet.status', 'pending')
            ->assertJsonPath('outcome.is_finished', false);

        $result = $response->json('bet.result');
        $this->assertArrayNotHasKey('server_seed', $result);
        $this->assertArrayNotHasKey('dealer_full_cards', $result);
        // prng_result is stored as null while pending, which leaks nothing
        $this->assertNull($result['prng_result'] ?? null);

        // dealer_visible_cards must be present (UI needs them)
        $this->assertArrayHasKey('dealer_visible_cards', $result);
    }

    public function test_blackjack_stand_settles_bet_and_reveals_secrets(): void
    {
        $this->fixProvablyFairResultToNonBlackjack();

        $this->actingAs($this->user)->postJson("/api/v1/games/{$this->blackjack->id}/bet", [
            'bet_amount' => '10.00',
            'client_seed' => 'demo',
            'payload' => ['action' => 'deal'],
        ]);

        $response = $this->actingAs($this->user)
            ->postJson("/api/v1/games/{$this->blackjack->id}/bet", [
                'bet_amount' => '10.00',
                'client_seed' => 'demo',
                'payload' => ['action' => 'stand'],
            ]);

        $response->assertOk()
            ->assertJsonPath('bet.status', 'settled')
            ->assertJsonPath('outcome.is_finished', true);

        $result = $response->json('bet.result');
        $this->assertArrayHasKey('server_seed', $result);
        $this->assertArrayHasKey('dealer_full_cards', $result);
        $this->assertArrayHasKey('prng_result', $result);

        // Verify hash matches the revealed seed (provably-fair guarantee)
        $this->assertSame(
            hash('sha256', $result['server_seed']),
            $response->json('bet.server_seed_hash'),
        );
    }

    public function test_continuation_action_without_pending_bet_returns_400(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson("/api/v1/games/{$this->blackjack->id}/bet", [
                'bet_amount' => '10.00',
                'client_seed' => 'demo',
                'payload' => ['action' => 'hit'],
            ]);

        $response->assertStatus(400)
            ->assertJsonPath('message', 'No active bet to continue.');
    }

    public function test_starting_new_bet_with_pending_blackjack_returns_400(): void
    {
        $this->fixProvablyFairResultToNonBlackjack();

        // First deal creates the pending bet
        $this->actingAs($this->user)->postJson("/api/v1/games/{$this->blackjack->id}/bet", [
            'bet_amount' => '10.00',
            'client_seed' => 'demo',
            'payload' => ['action' => 'deal'],
        ]);

        // Second attempt without an action should be blocked
        $response = $this->actingAs($this->user)
            ->postJson("/api/v1/games/{$this->blackjack->id}/bet", [
                'bet_amount' => '20.00',
                'client_seed' => 'different',
                'payload' => [],
            ]);

        $response->assertStatus(400)
            ->assertJsonPath('message', 'You have an active unfinished bet that you must complete.');

        // The bet returned in the error response must also be scrubbed
        $result = $response->json('bet.result');
        $this->assertArrayNotHasKey('server_seed', $result);
    }

    public function test_show_endpoint_returns_active_bet_for_user(): void
    {
        $this->fixProvablyFairResultToNonBlackjack();

        $this->actingAs($this->user)->postJson("/api/v1/games/{$this->blackjack->id}/bet", [
            'bet_amount' => '10.00',
            'client_seed' => 'demo',
            'payload' => ['action' => 'deal'],
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/games/{$this->blackjack->id}");

        $response->assertOk()
            ->assertJsonPath('active_bet.status', 'pending')
            ->assertJsonPath('game.id', $this->blackjack->id);

        $this->assertArrayNotHasKey('server_seed', $response->json('active_bet.result'));
    }

    public function test_show_endpoint_does_not_leak_other_users_bets(): void
    {
        $this->fixProvablyFairResultToNonBlackjack();

        // Other user creates a pending blackjack hand
        $other = User::factory()->create();
        Wallet::create([
            'user_id' => $other->id, 'currency' => 'USD', 'balance' => '500.00',
        ]);
        $this->actingAs($other)->postJson("/api/v1/games/{$this->blackjack->id}/bet", [
            'bet_amount' => '10.00',
            'client_seed' => 'demo',
            'payload' => ['action' => 'deal'],
        ]);

        // Original user fetches the game — no leakage
        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/games/{$this->blackjack->id}");

        $response->assertOk()->assertJsonPath('active_bet', null);
    }

    public function test_nonce_increments_between_deal_and_continuation(): void
    {
        $this->fixProvablyFairResultToNonBlackjack();

        $this->actingAs($this->user)->postJson("/api/v1/games/{$this->blackjack->id}/bet", [
            'bet_amount' => '10.00',
            'client_seed' => 'demo',
            'payload' => ['action' => 'deal'],
        ]);

        $this->actingAs($this->user)->postJson("/api/v1/games/{$this->blackjack->id}/bet", [
            'bet_amount' => '10.00',
            'client_seed' => 'demo',
            'payload' => ['action' => 'stand'],
        ]);

        $bet = Bet::where('user_id', $this->user->id)->firstOrFail();
        $this->assertSame(2, $bet->result['nonce']);
    }

    /**
     * Force a non-blackjack opening hand so deal stays pending and the
     * continuation flow can be tested deterministically.
     */
    private function fixProvablyFairResultToNonBlackjack(): void
    {
        $this->app->instance(ProvablyFairService::class, new class extends ProvablyFairService
        {
            public function generateServerSeed(): string
            {
                return 'fixed-server-seed-for-tests';
            }

            public function generateResult(string $serverSeed, string $clientSeed, int $nonce): float
            {
                // 0.5 yields a non-21 opening hand and a stand-eligible state.
                return 0.5;
            }
        });
    }
}
