<?php

namespace Game;

use App\Models\Bet;
use App\Models\Game;
use App\Modules\Game\Engines\SlotsEngine;
use Tests\TestCase;

class SlotsEngineTest extends TestCase
{
    private SlotsEngine $engine;
    private Game $game;

    protected function setUp(): void
    {
        parent::setUp();
        $this->engine = new SlotsEngine();
        $this->game = new Game();
        $this->game->rtp_percentage = '95.00';
    }

    public function test_returns_three_reel_symbols(): void
    {
        $bet = new Bet();
        $outcome = $this->engine->calculateOutcome($bet, $this->game, 0.5);

        $this->assertCount(3, $outcome['state']['reels']);
        $this->assertCount(3, $outcome['state']['reel_indices']);
        foreach ($outcome['state']['reels'] as $symbol) {
            $this->assertContains($symbol, ['cherry', 'lemon', 'bell', 'star', 'diamond', 'seven', 'crown']);
        }
    }

    public function test_three_of_a_kind_pays_symbol_multiplier(): void
    {
        $bet = new Bet();

        // Find a PRNG that yields three of a kind by scanning a range.
        $found = false;
        for ($prng = 0.0; $prng < 1.0; $prng += 0.0001) {
            $outcome = $this->engine->calculateOutcome($bet, $this->game, $prng);
            if ($outcome['state']['match_type'] === 'three_of_a_kind') {
                $this->assertTrue($outcome['state']['is_win']);
                $this->assertGreaterThan(0.0, $outcome['payout_multiplier']);
                $found = true;
                break;
            }
        }

        $this->assertTrue($found, 'expected to find a three-of-a-kind across the PRNG range');
    }

    public function test_cherry_consolation_pays_partial_multiplier(): void
    {
        $bet = new Bet();

        for ($prng = 0.0; $prng < 1.0; $prng += 0.0001) {
            $outcome = $this->engine->calculateOutcome($bet, $this->game, $prng);
            if ($outcome['state']['match_type'] === 'cherry_consolation') {
                $this->assertTrue($outcome['state']['is_win']);
                $this->assertEqualsWithDelta(0.1 * 0.95, $outcome['payout_multiplier'], 0.0001);
                return;
            }
        }

        $this->fail('expected to find a cherry consolation across the PRNG range');
    }

    public function test_no_match_returns_zero_multiplier(): void
    {
        $bet = new Bet();

        for ($prng = 0.0; $prng < 1.0; $prng += 0.0001) {
            $outcome = $this->engine->calculateOutcome($bet, $this->game, $prng);
            if ($outcome['state']['match_type'] === 'none') {
                $this->assertFalse($outcome['state']['is_win']);
                $this->assertEquals(0.0, $outcome['payout_multiplier']);
                return;
            }
        }

        $this->fail('expected at least one non-matching spin');
    }

    public function test_rtp_factor_scales_payout(): void
    {
        $bet = new Bet();
        $tightGame = new Game();
        $tightGame->rtp_percentage = '80.00';

        // Search for any winning spin
        for ($prng = 0.0; $prng < 1.0; $prng += 0.0001) {
            $outcome95 = $this->engine->calculateOutcome($bet, $this->game, $prng);
            if (! $outcome95['state']['is_win']) {
                continue;
            }
            $outcome80 = $this->engine->calculateOutcome($bet, $tightGame, $prng);

            // Same symbols → same base multiplier, only RTP factor differs
            $expectedRatio = 0.80 / 0.95;
            $this->assertEqualsWithDelta(
                $outcome95['payout_multiplier'] * $expectedRatio,
                $outcome80['payout_multiplier'],
                0.0001,
            );
            return;
        }

        $this->fail('expected at least one winning spin');
    }

    public function test_outcome_is_deterministic_for_same_prng(): void
    {
        $bet = new Bet();
        $a = $this->engine->calculateOutcome($bet, $this->game, 0.42);
        $b = $this->engine->calculateOutcome($bet, $this->game, 0.42);

        $this->assertSame($a['state']['reels'], $b['state']['reels']);
        $this->assertSame($a['payout_multiplier'], $b['payout_multiplier']);
    }

    public function test_animations_are_returned(): void
    {
        $bet = new Bet();
        $outcome = $this->engine->calculateOutcome($bet, $this->game, 0.5);

        $this->assertSame('slots_spin', $outcome['animations']['trigger']);
        $this->assertTrue($outcome['is_finished']);
    }
}
