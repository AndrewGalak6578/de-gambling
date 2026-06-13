<?php

namespace Tests\Feature\Game;

use App\Models\Game;
use App\Models\User;
use Database\Seeders\GameSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminGameControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $player;
    private Game $dice;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(GameSeeder::class);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');

        $this->player = User::factory()->create();
        $this->dice = Game::where('slug', 'dice')->first();
    }

    /* ──────────────────── RBAC ──────────────────── */

    public function test_unauthenticated_user_cannot_list_games(): void
    {
        $this->getJson('/api/v1/admin/games')->assertStatus(401);
    }

    public function test_non_admin_user_is_rejected(): void
    {
        Sanctum::actingAs($this->player);

        $this->getJson('/api/v1/admin/games')->assertStatus(403);
    }

    public function test_admin_can_list_all_games(): void
    {
        Sanctum::actingAs($this->admin);

        $response = $this->getJson('/api/v1/admin/games');

        $response->assertStatus(200)
            ->assertJsonStructure(['games' => [['id', 'name', 'slug', 'status', 'rtp_percentage']]]);
    }

    public function test_admin_index_supports_status_filter(): void
    {
        Game::where('slug', 'slots')->update(['status' => 'inactive']);

        Sanctum::actingAs($this->admin);

        $response = $this->getJson('/api/v1/admin/games?status=inactive');

        $response->assertStatus(200);
        foreach ($response->json('games') as $game) {
            $this->assertSame('inactive', $game['status']);
        }
    }

    public function test_admin_index_supports_search(): void
    {
        Sanctum::actingAs($this->admin);

        $response = $this->getJson('/api/v1/admin/games?search=dice');

        $response->assertStatus(200);
        $slugs = array_column($response->json('games'), 'slug');
        $this->assertContains('dice', $slugs);
    }

    /* ──────────────────── SHOW ──────────────────── */

    public function test_admin_can_show_a_single_game(): void
    {
        Sanctum::actingAs($this->admin);

        $response = $this->getJson("/api/v1/admin/games/{$this->dice->id}");

        $response->assertStatus(200)
            ->assertJsonPath('game.slug', 'dice');
    }

    /* ──────────────────── CREATE ──────────────────── */

    public function test_admin_can_create_a_new_game(): void
    {
        Sanctum::actingAs($this->admin);

        $response = $this->postJson('/api/v1/admin/games', [
            'name' => 'Roulette',
            'slug' => 'roulette',
            'rtp_percentage' => 94.74,
            'config' => ['pockets' => 37],
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('game.slug', 'roulette')
            ->assertJsonPath('game.rtp_percentage', '94.74');

        $this->assertDatabaseHas('games', ['slug' => 'roulette']);
    }

    public function test_create_validates_unique_slug(): void
    {
        Sanctum::actingAs($this->admin);

        $response = $this->postJson('/api/v1/admin/games', [
            'name' => 'Dice clone',
            'slug' => 'dice',
        ]);

        $response->assertStatus(422);
    }

    /* ──────────────────── UPDATE RTP ──────────────────── */

    public function test_admin_can_update_rtp(): void
    {
        Sanctum::actingAs($this->admin);

        $response = $this->patchJson("/api/v1/admin/games/{$this->dice->id}/rtp", [
            'rtp_percentage' => 97.00,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('game.rtp_percentage', '97.00');

        $this->dice->refresh();
        $this->assertSame('97.00', (string) $this->dice->rtp_percentage);
    }

    public function test_rtp_above_99_99_is_rejected(): void
    {
        Sanctum::actingAs($this->admin);

        $response = $this->patchJson("/api/v1/admin/games/{$this->dice->id}/rtp", [
            'rtp_percentage' => 150,
        ]);

        $response->assertStatus(422);
    }

    public function test_rtp_below_1_is_rejected(): void
    {
        Sanctum::actingAs($this->admin);

        $response = $this->patchJson("/api/v1/admin/games/{$this->dice->id}/rtp", [
            'rtp_percentage' => 0.5,
        ]);

        $response->assertStatus(422);
    }

    public function test_non_admin_cannot_update_rtp(): void
    {
        Sanctum::actingAs($this->player);

        $this->patchJson("/api/v1/admin/games/{$this->dice->id}/rtp", [
            'rtp_percentage' => 50,
        ])->assertStatus(403);
    }

    /* ──────────────────── UPDATE STATUS ──────────────────── */

    public function test_admin_can_toggle_status_to_inactive(): void
    {
        Sanctum::actingAs($this->admin);

        $response = $this->patchJson("/api/v1/admin/games/{$this->dice->id}/status", [
            'status' => 'inactive',
        ]);

        $response->assertStatus(200);
        $this->assertSame('inactive', $this->dice->fresh()->status);
    }

    public function test_status_must_be_one_of_active_inactive_retired(): void
    {
        Sanctum::actingAs($this->admin);

        $this->patchJson("/api/v1/admin/games/{$this->dice->id}/status", [
            'status' => 'paused',
        ])->assertStatus(422);
    }

    public function test_inactive_game_is_hidden_from_player_list(): void
    {
        Sanctum::actingAs($this->admin);
        $this->patchJson("/api/v1/admin/games/{$this->dice->id}/status", ['status' => 'inactive']);

        Sanctum::actingAs($this->player);
        $response = $this->getJson('/api/v1/games');

        $slugs = array_column($response->json(), 'slug');
        $this->assertNotContains('dice', $slugs);
    }

    /* ──────────────────── DESTROY ──────────────────── */

    public function test_admin_can_delete_a_game(): void
    {
        Sanctum::actingAs($this->admin);

        $response = $this->deleteJson("/api/v1/admin/games/{$this->dice->id}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('games', ['id' => $this->dice->id]);
    }

    public function test_non_admin_cannot_delete_game(): void
    {
        Sanctum::actingAs($this->player);

        $this->deleteJson("/api/v1/admin/games/{$this->dice->id}")->assertStatus(403);
    }
}
