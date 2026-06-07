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
            case 'spin-to-win':
                return new SpinToWinEngine();
            case 'slots':
                return new SlotsEngine();
            case 'blackjack':
                return new BlackjackEngine();
            default:
                throw new Exception("Game engine not found for slug: {$gameSlug}");
        }
    }
}

