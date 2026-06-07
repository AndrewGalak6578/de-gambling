<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_register(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Pranav',
            'email' => 'pranav@test.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('users', [
            'email' => 'pranav@test.com',
        ]);
    }

    public function test_user_can_login(): void
    {
        $user = User::factory()->create([
            'email' => 'login@test.com',
            'password' => bcrypt('password123'),
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'login@test.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(200);
    }

    public function test_dashboard_requires_login(): void
    {
        $response = $this->getJson('/api/v1/user/dashboard');

        $response->assertStatus(401);
    }

    public function test_logged_in_user_can_view_dashboard(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->getJson('/api/v1/user/dashboard');

        $response->assertStatus(200);
    }

    public function test_profile_can_be_updated(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patchJson('/api/v1/user/profile', [
                'name' => 'Pranav Singh',
                'email' => 'updated@test.com',
            ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('users', [
            'email' => 'updated@test.com',
        ]);
    }

    public function test_user_can_create_self_exclusion(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->postJson('/api/v1/user/self-exclusion', [
                'days' => 30,
                'reason' => 'Taking a break from gambling.',
            ]);

        $response->assertStatus(201);
    }

    public function test_self_exclusion_rejects_zero_days(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->postJson('/api/v1/user/self-exclusion', [
                'days' => 0,
            ]);

        $response->assertStatus(422);
    }

    public function test_self_exclusion_rejects_more_than_365_days(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->postJson('/api/v1/user/self-exclusion', [
                'days' => 366,
            ]);

        $response->assertStatus(422);
    }

    public function test_user_can_update_restrictions(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patchJson('/api/v1/user/restrictions', [
                'daily_deposit_limit' => 100,
                'daily_bet_limit' => 50,
                'daily_loss_limit' => 25,
            ]);

        $response->assertStatus(200);
    }
}