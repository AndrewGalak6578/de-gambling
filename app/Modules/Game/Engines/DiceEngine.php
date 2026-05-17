<?php

namespace App\Modules\Game\Engines;

use App\Models\Bet;
use App\Models\Game;

class DiceEngine implements GameEngineInterface
{
    public function calculateOutcome(Bet $bet, Game $game, float $prngResult): array
    {
        // Decode player payload
        // Example: ['target' => 50, 'condition' => 'under']
        $payload = $bet->result['payload'] ?? current($bet->result) ?? [];
        $target = $payload['target'] ?? 50;
        $condition = $payload['condition'] ?? 'under';

        // Apply RTP calibration (Admin house edge manipulation)
        // If RTP is 95%, the House Edge is 5%
        // Normally for dice, under 50 with 1% house edge means you win if < 49.5
        $rtp = $game->rtp_percentage ?? 95.0;
        $houseEdge = 100.0 - $rtp;

        // Ensure roll is between 0.00 and 100.00
        $roll = $prngResult * 100;
        $roll = round($roll, 2);

        $isWin = false;
        $multiplier = 0.0;

        // Modify simple logic for RTP
        // Base mechanics calculation
        if ($condition === 'under') {
            $effectiveTarget = $target * ($rtp / 100);
            if ($roll < $effectiveTarget) {
                $isWin = true;
                $multiplier = 100 / $target * ($rtp / 100);
            }
        } else {
            $effectiveTarget = 100 - ((100 - $target) * ($rtp / 100));
            if ($roll > $effectiveTarget) {
                $isWin = true;
                $multiplier = 100 / (100 - $target) * ($rtp / 100);
            }
        }

        return [
            'payout_multiplier' => $isWin ? $multiplier : 0,
            'state' => ['roll' => $roll, 'is_win' => $isWin],
            'animations' => ['trigger' => 'dice_roll', 'duration' => 2000],
            'is_finished' => true
        ];
    }
}
