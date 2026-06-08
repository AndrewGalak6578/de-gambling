<?php

namespace Database\Factories;

use App\Models\Intervention;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Intervention>
 */
class InterventionFactory extends Factory
{
    protected $model = Intervention::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'type' => 'circuit_breaker',
            'status' => 'active',
            'starts_at' => now(),
            'ends_at' => now()->addHours(24),
            'payload' => ['reason' => 'Factory intervention.'],
        ];
    }

    public function revoked(): static
    {
        return $this->state(fn () => [
            'status' => 'revoked',
            'ends_at' => now(),
            'payload' => [
                'reason' => 'Factory intervention.',
                'revoked_at' => now()->toISOString(),
            ],
        ]);
    }
}
