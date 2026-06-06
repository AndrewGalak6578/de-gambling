<?php

namespace Database\Factories;

use App\Models\RiskEvent;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RiskEvent>
 */
class RiskEventFactory extends Factory
{
    protected $model = RiskEvent::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'type' => 'high_risk',
            'score_delta' => 75,
            'payload' => ['risk_score' => 75],
        ];
    }
}
