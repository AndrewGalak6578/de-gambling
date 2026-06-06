<?php

namespace Database\Factories;

use App\Models\Bet;
use App\Models\Game;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Bet>
 */
class BetFactory extends Factory
{
    protected $model = Bet::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'game_id' => Game::factory(),
            'bet_amount' => '10.00000000',
            'payout_amount' => '0.00000000',
            'currency' => 'USD',
            'status' => 'settled',
            'server_seed_hash' => hash('sha256', 'server-seed'),
            'client_seed' => 'client-seed',
            'result' => ['roll' => 50.0, 'is_win' => false],
        ];
    }

    public function loss(): static
    {
        return $this->state(fn () => [
            'payout_amount' => '0.00000000',
            'status' => 'settled',
            'result' => ['roll' => 99.0, 'is_win' => false],
        ]);
    }

    public function win(): static
    {
        return $this->state(fn () => [
            'payout_amount' => '20.00000000',
            'status' => 'settled',
            'result' => ['roll' => 10.0, 'is_win' => true],
        ]);
    }
}
