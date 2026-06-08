<?php

namespace Tests\Feature\ResponsibleGambling;

use App\Models\Intervention;
use App\Models\RiskEvent;
use App\Models\RiskScoreOverride;
use App\Models\Transaction;
use App\Models\User;
use App\Modules\Finance\Enums\TransactionStatus;
use App\Modules\Finance\Enums\TransactionType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\CreatesFinanceFixtures;
use Tests\TestCase;

class AdminRiskControllerTest extends TestCase
{
    use CreatesFinanceFixtures;
    use RefreshDatabase;

    public function test_admin_can_list_risk_events(): void
    {
        $admin = $this->adminUser();
        $user = User::factory()->create();
        $event = RiskEvent::factory()->for($user)->create(['type' => 'deposit_spike']);

        $response = $this->actingAs($admin)->getJson('/api/v1/admin/risk-events');

        $response->assertOk()
            ->assertJsonPath('risk_events.0.id', $event->id)
            ->assertJsonPath('risk_events.0.type', 'deposit_spike');
    }

    public function test_non_admin_is_forbidden_from_risk_events(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson('/api/v1/admin/risk-events')
            ->assertForbidden();
    }

    public function test_admin_can_view_risk_summaries_with_scores_and_override(): void
    {
        $admin = $this->adminUser();
        $user = User::factory()->create();
        RiskScoreOverride::factory()->for($user)->create(['score_adjustment' => 10]);

        Transaction::factory()->count(3)->for($user)->create([
            'type' => TransactionType::Deposit->value,
            'status' => TransactionStatus::Confirmed->value,
            'created_at' => now(),
        ]);

        $response = $this->actingAs($admin)->getJson('/api/v1/admin/risk-summaries');

        $response->assertOk();
        $summary = collect($response->json('users'))->firstWhere('id', $user->id);

        $this->assertSame(30, $summary['raw_score']);
        $this->assertSame(40, $summary['effective_score']);
        $this->assertSame(10, $summary['override']['score_adjustment']);
    }

    public function test_admin_can_set_risk_override(): void
    {
        $admin = $this->adminUser();
        $user = User::factory()->create();

        $response = $this->actingAs($admin)->patchJson("/api/v1/admin/users/{$user->id}/risk-override", [
            'score_adjustment' => -25,
            'disabled' => true,
            'reason' => 'Manual review completed.',
        ]);

        $response->assertOk()
            ->assertJsonPath('override.score_adjustment', -25)
            ->assertJsonPath('override.disabled', true)
            ->assertJsonPath('effective_score', 0);

        $this->assertDatabaseHas('risk_score_overrides', [
            'user_id' => $user->id,
            'admin_user_id' => $admin->id,
            'score_adjustment' => -25,
            'disabled' => true,
        ]);
    }

    #[DataProvider('validRiskOverrideBoundaryScores')]
    public function test_valid_risk_override_boundary_scores_are_accepted(int $score): void
    {
        $admin = $this->adminUser();
        $user = User::factory()->create();

        $this->actingAs($admin)
            ->patchJson("/api/v1/admin/users/{$user->id}/risk-override", [
                'score_adjustment' => $score,
                'disabled' => false,
                'reason' => 'Boundary score.',
            ])
            ->assertOk()
            ->assertJsonPath('override.score_adjustment', $score);

        $this->assertDatabaseHas('risk_score_overrides', [
            'user_id' => $user->id,
            'score_adjustment' => $score,
            'disabled' => false,
        ]);
    }

    public static function validRiskOverrideBoundaryScores(): array
    {
        return [
            'min boundary' => [-100],
            'min plus one' => [-99],
            'max minus one' => [99],
            'max boundary' => [100],
        ];
    }

    #[DataProvider('invalidRiskOverridePayloads')]
    public function test_invalid_risk_override_payload_is_rejected(array $payload): void
    {
        $admin = $this->adminUser();
        $user = User::factory()->create();

        $this->actingAs($admin)
            ->patchJson("/api/v1/admin/users/{$user->id}/risk-override", $payload)
            ->assertUnprocessable();
    }

    public static function invalidRiskOverridePayloads(): array
    {
        return [
            'missing score' => [['disabled' => false]],
            'extreme min' => [['score_adjustment' => -1000, 'disabled' => false]],
            'below min' => [['score_adjustment' => -101, 'disabled' => false]],
            'above max' => [['score_adjustment' => 101, 'disabled' => false]],
            'extreme max' => [['score_adjustment' => 1000, 'disabled' => false]],
            'non integer score' => [['score_adjustment' => '10.5', 'disabled' => false]],
            'missing disabled' => [['score_adjustment' => 0]],
            'invalid disabled' => [['score_adjustment' => 0, 'disabled' => 'not_bool']],
            'reason too long' => [['score_adjustment' => 0, 'disabled' => false, 'reason' => str_repeat('x', 501)]],
        ];
    }

    public function test_admin_can_list_active_interventions(): void
    {
        $admin = $this->adminUser();
        $user = User::factory()->create();
        $active = Intervention::factory()->for($user)->create(['type' => 'circuit_breaker']);
        Intervention::factory()->for($user)->revoked()->create(['type' => 'admin_cool_off']);

        $response = $this->actingAs($admin)->getJson('/api/v1/admin/interventions');

        $response->assertOk()
            ->assertJsonCount(1, 'interventions')
            ->assertJsonPath('interventions.0.id', $active->id);
    }
}
