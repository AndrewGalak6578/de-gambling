<?php

namespace Tests\Feature\ResponsibleGambling;

use App\Models\Intervention;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\CreatesFinanceFixtures;
use Tests\TestCase;

class AdminInterventionControllerTest extends TestCase
{
    use CreatesFinanceFixtures;
    use RefreshDatabase;

    public function test_admin_can_list_user_interventions(): void
    {
        $admin = $this->adminUser();
        $user = User::factory()->create();
        $intervention = Intervention::factory()->for($user)->create(['type' => 'admin_bet_block']);
        Intervention::factory()->create(['type' => 'admin_deposit_block']);

        $response = $this->actingAs($admin)->getJson("/api/v1/admin/users/{$user->id}/interventions");

        $response->assertOk()
            ->assertJsonCount(1, 'interventions')
            ->assertJsonPath('interventions.0.id', $intervention->id);
    }

    public function test_non_admin_is_forbidden_from_user_interventions(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson("/api/v1/admin/users/{$user->id}/interventions")
            ->assertForbidden();
    }

    public function test_admin_can_create_intervention(): void
    {
        $admin = $this->adminUser();
        $user = User::factory()->create();

        $response = $this->actingAs($admin)->postJson("/api/v1/admin/users/{$user->id}/interventions", [
            'type' => 'admin_win_limit',
            'ends_at' => now()->addDay()->toISOString(),
            'reason' => 'Temporary limit.',
            'payload' => [
                'max_wins' => 2,
                'window' => '24h',
            ],
        ]);

        $response->assertCreated()
            ->assertJsonPath('intervention.type', 'admin_win_limit')
            ->assertJsonPath('intervention.status', 'active')
            ->assertJsonPath('intervention.payload.max_wins', 2);

        $this->assertDatabaseHas('admin_actions', [
            'admin_user_id' => $admin->id,
            'action' => 'intervention_created',
        ]);
    }

    #[DataProvider('invalidInterventionPayloads')]
    public function test_invalid_intervention_payload_is_rejected(array $payload): void
    {
        $admin = $this->adminUser();
        $user = User::factory()->create();

        $this->actingAs($admin)
            ->postJson("/api/v1/admin/users/{$user->id}/interventions", $payload)
            ->assertUnprocessable();
    }

    public static function invalidInterventionPayloads(): array
    {
        return [
            'missing type' => [['reason' => 'Missing type.']],
            'invalid type' => [['type' => 'self_exclusion', 'reason' => 'Not admin type.']],
            'missing reason' => [['type' => 'admin_bet_block']],
            'reason too long' => [['type' => 'admin_bet_block', 'reason' => str_repeat('x', 501)]],
            'past end date' => [['type' => 'admin_cool_off', 'reason' => 'Past.', 'ends_at' => '2000-01-01T00:00:00Z']],
            'win limit missing max wins' => [['type' => 'admin_win_limit', 'reason' => 'Missing payload.', 'payload' => ['window' => 'day']]],
            'win limit max below min' => [['type' => 'admin_win_limit', 'reason' => 'Bad min.', 'payload' => ['max_wins' => 0]]],
            'win limit max above max' => [['type' => 'admin_win_limit', 'reason' => 'Bad max.', 'payload' => ['max_wins' => 101]]],
            'invalid window' => [['type' => 'admin_win_limit', 'reason' => 'Bad window.', 'payload' => ['max_wins' => 1, 'window' => 'week']]],
        ];
    }

    public function test_admin_can_revoke_active_intervention(): void
    {
        $admin = $this->adminUser();
        $intervention = Intervention::factory()->create(['type' => 'admin_bet_block']);

        $response = $this->actingAs($admin)->patchJson("/api/v1/admin/interventions/{$intervention->id}/revoke", [
            'reason' => 'Issue resolved.',
        ]);

        $response->assertOk()
            ->assertJsonPath('intervention.status', 'revoked')
            ->assertJsonPath('intervention.payload.revoke_reason', 'Issue resolved.');

        $this->assertDatabaseHas('admin_actions', [
            'admin_user_id' => $admin->id,
            'action' => 'intervention_revoked',
        ]);
    }

    public function test_revoked_intervention_cannot_be_revoked_twice(): void
    {
        $admin = $this->adminUser();
        $intervention = Intervention::factory()->revoked()->create();

        $this->actingAs($admin)
            ->patchJson("/api/v1/admin/interventions/{$intervention->id}/revoke", [
                'reason' => 'Second revoke.',
            ])
            ->assertStatus(409);
    }
}
