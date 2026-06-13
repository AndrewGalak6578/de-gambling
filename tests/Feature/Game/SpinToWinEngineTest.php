<?php

namespace Game;

use App\Models\Bet;
use App\Models\Game;
use App\Modules\Game\Engines\SpinToWinEngine;
use Tests\TestCase;

class SpinToWinEngineTest extends TestCase
{
    private SpinToWinEngine $engine;
    private Game $game;

    protected function setUp(): void
    {
        parent::setUp();
        $this->engine = new SpinToWinEngine();
        $this->game = new Game();
        $this->game->rtp_percentage = '90.00';
        $this->game->config = ['sectors' => 8];
    }

    public function test_player_wins_when_landed_sector_matches(): void
    {
        // adjustedWinChance = (1/8) * 0.9 = 0.1125 → effectiveSectors ≈ 8.888
        // floor(prng * 8.888) + 1 = sector 1 for prng < ~0.1125
        $bet = new Bet();
        $bet->result = ['payload' => ['sector' => 1]];

        $outcome = $this->engine->calculateOutcome($bet, $this->game, 0.05);

        $this->assertTrue($outcome['state']['is_win']);
        $this->assertSame(1, $outcome['state']['landed_sector']);
        $this->assertGreaterThan(0.0, $outcome['payout_multiplier']);
    }

    public function test_player_loses_when_landed_sector_does_not_match(): void
    {
        $bet = new Bet();
        $bet->result = ['payload' => ['sector' => 5]];

        $outcome = $this->engine->calculateOutcome($bet, $this->game, 0.05);

        $this->assertFalse($outcome['state']['is_win']);
        $this->assertSame(0.0, $outcome['payout_multiplier']);
    }

    public function test_out_of_range_sector_is_clamped_high(): void
    {
        $bet = new Bet();
        $bet->result = ['payload' => ['sector' => 9999]];

        $outcome = $this->engine->calculateOutcome($bet, $this->game, 0.99);

        // 9999 clamps to 8 (the max for an 8-sector wheel)
        $this->assertSame(8, $outcome['state']['player_sector']);
    }

    public function test_out_of_range_sector_is_clamped_low(): void
    {
        $bet = new Bet();
        $bet->result = ['payload' => ['sector' => -5]];

        $outcome = $this->engine->calculateOutcome($bet, $this->game, 0.0);

        $this->assertSame(1, $outcome['state']['player_sector']);
    }

    public function test_winning_multiplier_includes_rtp_factor(): void
    {
        $bet = new Bet();
        $bet->result = ['payload' => ['sector' => 1]];

        $outcome = $this->engine->calculateOutcome($bet, $this->game, 0.05);

        // Multiplier = sectors * (rtp/100) = 8 * 0.9 = 7.2
        $this->assertEqualsWithDelta(7.2, $outcome['payout_multiplier'], 0.0001);
    }

    public function test_uses_default_sectors_when_config_missing(): void
    {
        $bareGame = new Game();
        $bareGame->rtp_percentage = '90.00';
        $bareGame->config = null;

        $bet = new Bet();
        $bet->result = ['payload' => ['sector' => 1]];

        $outcome = $this->engine->calculateOutcome($bet, $bareGame, 0.05);

        // Default = 8 sectors
        $this->assertEqualsWithDelta(7.2, $outcome['payout_multiplier'], 0.0001);
        $this->assertGreaterThanOrEqual(1, $outcome['state']['landed_sector']);
        $this->assertLessThanOrEqual(8, $outcome['state']['landed_sector']);
    }

    public function test_landed_sector_never_exceeds_configured_sectors(): void
    {
        $bet = new Bet();
        $bet->result = ['payload' => ['sector' => 1]];

        // Sweep many PRNGs; landed sector must always be within [1, 8]
        for ($prng = 0.0; $prng < 1.0; $prng += 0.01) {
            $outcome = $this->engine->calculateOutcome($bet, $this->game, $prng);
            $this->assertGreaterThanOrEqual(1, $outcome['state']['landed_sector']);
            $this->assertLessThanOrEqual(8, $outcome['state']['landed_sector']);
        }
    }

    public function test_animation_trigger_is_returned(): void
    {
        $bet = new Bet();
        $bet->result = ['payload' => ['sector' => 1]];

        $outcome = $this->engine->calculateOutcome($bet, $this->game, 0.5);

        $this->assertSame('spin_wheel', $outcome['animations']['trigger']);
        $this->assertTrue($outcome['is_finished']);
    }
}
