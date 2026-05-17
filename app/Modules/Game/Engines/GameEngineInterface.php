<?php

namespace App\Modules\Game\Engines;

use App\Models\Bet;
use App\Models\Game;

interface GameEngineInterface
{
    /**
     * Calculate the result of a bet.
     *
     * @param Bet $bet
     * @param Game $game
     * @param float $prngResult Provably fair random float between 0 and 1
     * @return array Contains 'payout_multiplier', 'state', 'animations', and potentially 'is_finished'.
     */
    public function calculateOutcome(Bet $bet, Game $game, float $prngResult): array;
}

