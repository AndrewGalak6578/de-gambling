<?php

namespace Database\Seeders;

use App\Models\Game;
use Illuminate\Database\Seeder;

class GameSeeder extends Seeder
{
    public function run(): void
    {
        Game::updateOrCreate(['slug' => 'dice'], [
            'name' => 'Dice',
            'status' => 'active',
            'rtp_percentage' => '95.00',
            'config' => [
                'description' => 'Classic dice game. Choose under or over a target number and test your luck.',
                'min_bet' => '0.01',
                'max_bet' => '10000.00',
            ],
        ]);

        Game::updateOrCreate(['slug' => 'spin-to-win'], [
            'name' => 'Spin to Win',
            'status' => 'active',
            'rtp_percentage' => '90.00',
            'config' => [
                'description' => 'Pick a number (1-8). If the wheel lands on your number, you win big multipliers!',
                'min_bet' => '0.01',
                'max_bet' => '5000.00',
                'sectors' => 8,
            ],
        ]);

        Game::updateOrCreate(['slug' => 'slots'], [
            'name' => 'Royal Slots',
            'status' => 'active',
            'rtp_percentage' => '95.00',
            'config' => [
                'description' => 'Three-reel VIP slot. Match three symbols to win. Crown jackpot pays 1300x.',
                'min_bet' => '0.10',
                'max_bet' => '5000.00',
                'reels' => 3,
            ],
        ]);
    }
}
