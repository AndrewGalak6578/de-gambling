<?php

namespace Tests\Feature\Game;

use App\Models\Bet;
use App\Models\Game;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BetHistoryControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_load_persisted_bet_history_for_game(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $game = Game::factory()->create(['slug' => 'dice']);
        $otherGame = Game::factory()->create(['slug' => 'slots']);
        $latest = Bet::factory()->for($user)->for($game)->create([
            'bet_amount' => '20.00000000',
            'created_at' => now(),
        ]);
        Bet::factory()->for($user)->for($otherGame)->create();
        Bet::factory()->for($otherUser)->for($game)->create();

        $response = $this->actingAs($user)
            ->getJson("/api/v1/user/bets?game_id={$game->id}&per_page=12&sort=created_at&direction=desc");

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $latest->id)
            ->assertJsonPath('data.0.game.id', $game->id);
    }

    public function test_unauthenticated_user_cannot_load_history(): void
    {
        $response = $this->getJson('/api/v1/user/bets');

        $response->assertStatus(401);
    }

    public function test_history_only_returns_bets_for_authenticated_user(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $game = Game::factory()->create(['slug' => 'dice']);

        Bet::factory()->for($user)->for($game)->count(3)->create();
        Bet::factory()->for($otherUser)->for($game)->count(2)->create();

        $response = $this->actingAs($user)->getJson('/api/v1/user/bets');

        $response->assertOk()->assertJsonCount(3, 'data');
    }

    public function test_status_filter_works(): void
    {
        $user = User::factory()->create();
        $game = Game::factory()->create(['slug' => 'blackjack']);

        Bet::factory()->for($user)->for($game)->create(['status' => 'pending']);
        Bet::factory()->for($user)->for($game)->count(2)->create(['status' => 'settled']);

        $response = $this->actingAs($user)->getJson('/api/v1/user/bets?status=pending');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.status', 'pending');
    }

    public function test_sort_whitelist_rejects_arbitrary_columns(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson('/api/v1/user/bets?sort=user_id');

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['sort']);
    }

    public function test_direction_whitelist_rejects_invalid_values(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson('/api/v1/user/bets?direction=sideways');

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['direction']);
    }

    public function test_status_filter_rejects_unknown_values(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson('/api/v1/user/bets?status=hacked');

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['status']);
    }

    public function test_per_page_must_be_within_one_to_one_hundred(): void
    {
        $user = User::factory()->create();

        $tooLarge = $this->actingAs($user)->getJson('/api/v1/user/bets?per_page=500');
        $tooLarge->assertStatus(422)->assertJsonValidationErrors(['per_page']);

        $tooSmall = $this->actingAs($user)->getJson('/api/v1/user/bets?per_page=0');
        $tooSmall->assertStatus(422)->assertJsonValidationErrors(['per_page']);
    }

    public function test_nonexistent_game_id_filter_returns_validation_error(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson('/api/v1/user/bets?game_id=999999');

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['game_id']);
    }

    public function test_default_sort_is_created_at_descending(): void
    {
        $user = User::factory()->create();
        $game = Game::factory()->create(['slug' => 'dice']);

        $older = Bet::factory()->for($user)->for($game)->create(['created_at' => now()->subDay()]);
        $newer = Bet::factory()->for($user)->for($game)->create(['created_at' => now()]);

        $response = $this->actingAs($user)->getJson('/api/v1/user/bets');

        $response->assertOk()
            ->assertJsonPath('data.0.id', $newer->id)
            ->assertJsonPath('data.1.id', $older->id);
    }

    public function test_response_has_pagination_envelope(): void
    {
        $user = User::factory()->create();
        $game = Game::factory()->create(['slug' => 'dice']);
        Bet::factory()->for($user)->for($game)->count(25)->create();

        $response = $this->actingAs($user)->getJson('/api/v1/user/bets?per_page=10');

        $response->assertOk()
            ->assertJsonStructure([
                'data',
                'current_page',
                'per_page',
                'total',
                'last_page',
            ])
            ->assertJsonPath('per_page', 10)
            ->assertJsonPath('total', 25);
    }

    public function test_game_relation_is_eager_loaded(): void
    {
        $user = User::factory()->create();
        $game = Game::factory()->create(['slug' => 'dice', 'name' => 'Dice']);
        Bet::factory()->for($user)->for($game)->create();

        $response = $this->actingAs($user)->getJson('/api/v1/user/bets');

        $response->assertOk()
            ->assertJsonPath('data.0.game.slug', 'dice')
            ->assertJsonPath('data.0.game.name', 'Dice');
    }
}
