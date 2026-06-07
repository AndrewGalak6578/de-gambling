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
}