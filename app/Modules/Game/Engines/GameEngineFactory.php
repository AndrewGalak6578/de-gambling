<?php

namespace App\Modules\Game\Engines;

use Exception;

class GameEngineFactory
{
    /**
     * @throws Exception
     */
    public static function make(string $gameSlug): GameEngineInterface
    {
        switch ($gameSlug) {
            case 'dice':
                return new DiceEngine();
            // Extend with other games like SpinToWinEngine, PokerEngine, etc.
            default:
                throw new Exception("Game engine not found for slug: {$gameSlug}");
        }
    }
}

