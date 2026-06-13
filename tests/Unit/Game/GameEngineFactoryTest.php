<?php

namespace Tests\Unit\Game;

use App\Modules\Game\Engines\BlackjackEngine;
use App\Modules\Game\Engines\DiceEngine;
use App\Modules\Game\Engines\GameEngineFactory;
use App\Modules\Game\Engines\GameEngineInterface;
use App\Modules\Game\Engines\SlotsEngine;
use App\Modules\Game\Engines\SpinToWinEngine;
use Exception;
use PHPUnit\Framework\TestCase;

class GameEngineFactoryTest extends TestCase
{
    public function test_dice_slug_returns_dice_engine(): void
    {
        $engine = GameEngineFactory::make('dice');

        $this->assertInstanceOf(DiceEngine::class, $engine);
        $this->assertInstanceOf(GameEngineInterface::class, $engine);
    }

    public function test_spin_to_win_slug_returns_spin_to_win_engine(): void
    {
        $engine = GameEngineFactory::make('spin-to-win');

        $this->assertInstanceOf(SpinToWinEngine::class, $engine);
    }

    public function test_slots_slug_returns_slots_engine(): void
    {
        $engine = GameEngineFactory::make('slots');

        $this->assertInstanceOf(SlotsEngine::class, $engine);
    }

    public function test_blackjack_slug_returns_blackjack_engine(): void
    {
        $engine = GameEngineFactory::make('blackjack');

        $this->assertInstanceOf(BlackjackEngine::class, $engine);
    }

    public function test_unknown_slug_throws_exception(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Game engine not found for slug: roulette');

        GameEngineFactory::make('roulette');
    }

    public function test_empty_slug_throws_exception(): void
    {
        $this->expectException(Exception::class);

        GameEngineFactory::make('');
    }
}
