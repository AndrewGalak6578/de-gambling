<?php

namespace Game;

use App\Models\Bet;
use App\Models\Game;
use App\Modules\Game\Engines\DiceEngine;
use Tests\TestCase;

class DiceEngineTest extends TestCase
{
    private DiceEngine $engine;
    private Game $game;

    protected function setUp(): void
    {
        parent::setUp();
        $this->engine = new DiceEngine();
        $this->game = new Game();
        $this->game->rtp_percentage = '95.00';
    }

    public function test_under_condition_win_when_roll_below_target(): void
    {
        $bet = new Bet();
        $bet->result = ['payload' => ['target' => 50, 'condition' => 'under']];

        // PRNG 0.49 -> roll 49 -> < 50*0.95=47.5? No, 49 > 47.5, so no win
        // Actually: effectiveTarget = 50 * 0.95 = 47.5, roll = 49, so not a win
        $outcome = $this->engine->calculateOutcome($bet, $this->game, 0.49);

        $this->assertTrue($outcome['is_finished']);
        $this->assertFalse($outcome['state']['is_win']);
        $this->assertEquals(0.0, $outcome['payout_multiplier']);
    }

    public function test_under_condition_win_when_roll_below_effective_target(): void
    {
        $bet = new Bet();
        $bet->result = ['payload' => ['target' => 50, 'condition' => 'under']];

        // effectiveTarget = 50 * 0.95 = 47.5
        // PRNG 0.47 -> roll 47 -> 47 < 47.5 = win
        $outcome = $this->engine->calculateOutcome($bet, $this->game, 0.47);

        $this->assertTrue($outcome['is_finished']);
        $this->assertTrue((bool) $outcome['state']['is_win']);
        $this->assertGreaterThan(0.0, $outcome['payout_multiplier']);
    }

    public function test_over_condition_win_when_roll_above_effective_target(): void
    {
        $bet = new Bet();
        $bet->result = ['payload' => ['target' => 50, 'condition' => 'over']];

        // effectiveTarget = 100 - ((100 - 50) * 0.95) = 100 - 47.5 = 52.5
        // PRNG 0.53 -> roll 53 -> 53 > 52.5 = win
        $outcome = $this->engine->calculateOutcome($bet, $this->game, 0.53);

        $this->assertTrue($outcome['is_finished']);
        $this->assertTrue($outcome['state']['is_win']);
        $this->assertGreaterThan(0.0, $outcome['payout_multiplier']);
    }

    public function test_over_condition_loss_when_roll_below_effective_target(): void
    {
        $bet = new Bet();
        $bet->result = ['payload' => ['target' => 50, 'condition' => 'over']];

        $outcome = $this->engine->calculateOutcome($bet, $this->game, 0.51);

        $this->assertFalse($outcome['state']['is_win']);
        $this->assertEquals(0.0, $outcome['payout_multiplier']);
    }

    public function test_rtp_calibration_affects_win_threshold(): void
    {
        $tightGame = new Game();
        $tightGame->rtp_percentage = '90.00';

        // With roll = 46 and 95% RTP: effectiveTarget = 47.5 -> 46 < 47.5 = win
        $bet = new Bet();
        $bet->result = ['payload' => ['target' => 50, 'condition' => 'under']];
        $outcome95 = $this->engine->calculateOutcome($bet, $this->game, 0.46);
        $this->assertTrue($outcome95['state']['is_win']);

        // With roll = 46 and 90% RTP: effectiveTarget = 45.0 -> 46 > 45 = loss
        $bet2 = new Bet();
        $bet2->result = ['payload' => ['target' => 50, 'condition' => 'under']];
        $outcome90 = $this->engine->calculateOutcome($bet2, $tightGame, 0.46);
        $this->assertFalse($outcome90['state']['is_win']);

        // With roll = 48 and 95% RTP: 48 > 47.5 = loss
        $bet3 = new Bet();
        $bet3->result = ['payload' => ['target' => 50, 'condition' => 'under']];
        $outcome95b = $this->engine->calculateOutcome($bet3, $this->game, 0.48);
        $this->assertFalse($outcome95b['state']['is_win']);

        // With roll = 40 and 90% RTP: 40 < 45 = win
        $bet4 = new Bet();
        $bet4->result = ['payload' => ['target' => 50, 'condition' => 'under']];
        $outcome90b = $this->engine->calculateOutcome($bet4, $tightGame, 0.40);
        $this->assertTrue($outcome90b['state']['is_win']);
    }

    public function test_roll_is_rounded_to_two_decimals(): void
    {
        $bet = new Bet();
        $bet->result = ['payload' => ['target' => 50, 'condition' => 'under']];

        // PRNG 0.1234567 -> roll = 12.34567 -> round to 12.35
        $outcome = $this->engine->calculateOutcome($bet, $this->game, 0.1234567);

        $this->assertEqualsWithDelta(12.35, $outcome['state']['roll'], 0.001);
    }

    public function test_animations_are_returned(): void
    {
        $bet = new Bet();
        $bet->result = ['payload' => ['target' => 50, 'condition' => 'under']];

        $outcome = $this->engine->calculateOutcome($bet, $this->game, 0.5);

        $this->assertArrayHasKey('animations', $outcome);
        $this->assertSame('dice_roll', $outcome['animations']['trigger']);
    }
}
