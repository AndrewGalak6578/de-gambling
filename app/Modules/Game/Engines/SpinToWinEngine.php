<?php

namespace App\Modules\Game\Engines;

use App\Models\Bet;
use App\Models\Game;

class SpinToWinEngine implements GameEngineInterface
{
    public function calculateOutcome(Bet $bet, Game $game, float $prngResult): array
    {
        $payload = $bet->result['payload'] ?? current($bet->result) ?? [];
        $playerSector = (int) ($payload['sector'] ?? 1);
        $sectors = (int) ($game->config['sectors'] ?? 8);

        $playerSector = max(1, min($sectors, $playerSector));

        $rtp = $game->rtp_percentage ?? 90.0;
        $houseEdge = 100.0 - $rtp;

        $fairWinChance = 1.0 / $sectors;
        $adjustedWinChance = $fairWinChance * ($rtp / 100);
        $effectiveSectors = 1.0 / $adjustedWinChance;

        $landedSector = (int) floor($prngResult * $effectiveSectors) + 1;
        $landedSector = max(1, min($sectors, $landedSector));

        $isWin = ($landedSector === $playerSector);
        $multiplier = $isWin ? ($sectors * ($rtp / 100)) : 0.0;

        return [
            'payout_multiplier' => $multiplier,
            'state' => [
                'player_sector' => $playerSector,
                'landed_sector' => $landedSector,
                'is_win' => $isWin,
            ],
            'animations' => ['trigger' => 'spin_wheel', 'duration' => 3000],
            'is_finished' => true,
        ];
    }
}
