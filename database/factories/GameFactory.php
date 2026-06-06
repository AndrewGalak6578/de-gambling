<?php

namespace Database\Factories;

use App\Models\Game;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Game>
 */
class GameFactory extends Factory
{
    protected $model = Game::class;

    public function definition(): array
    {
        return [
            'name' => 'Dice',
            'slug' => 'dice',
            'status' => 'active',
            'rtp_percentage' => '95.00',
            'config' => ['min_bet' => '0.01', 'max_bet' => '10000.00'],
        ];
    }
}
