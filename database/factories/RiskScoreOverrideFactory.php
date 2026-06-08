<?php

namespace Database\Factories;

use App\Models\RiskScoreOverride;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RiskScoreOverride>
 */
class RiskScoreOverrideFactory extends Factory
{
    protected $model = RiskScoreOverride::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'admin_user_id' => User::factory(),
            'score_adjustment' => -10,
            'disabled' => false,
            'reason' => 'Factory override.',
        ];
    }
}
