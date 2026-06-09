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
}
