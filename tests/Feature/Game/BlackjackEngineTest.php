<?php

namespace Game;

use App\Models\Bet;
use App\Models\Game;
use App\Modules\Game\Engines\BlackjackEngine;
use InvalidArgumentException;
use Tests\TestCase;

class BlackjackEngineTest extends TestCase
{
    private BlackjackEngine $engine;
    private Game $game;

    protected function setUp(): void
    {
        parent::setUp();
        $this->engine = new BlackjackEngine();
        $this->game = new Game();
        $this->game->rtp_percentage = '99.50';
    }

    public function test_deal_returns_two_player_cards_and_one_visible_dealer_card(): void
    {
        $bet = new Bet();
        $bet->result = ['payload' => ['action' => 'deal']];

        $outcome = $this->engine->calculateOutcome($bet, $this->game, 0.5);
        $state = $outcome['state'];

        $this->assertCount(2, $state['player_cards']);
        $this->assertCount(1, $state['dealer_visible_cards']);
        $this->assertSame(1, $state['dealer_hidden_count']);
        $this->assertArrayHasKey('dealer_full_cards', $state);
        $this->assertCount(2, $state['dealer_full_cards']);
    }

    public function test_deal_without_action_falls_back_to_deal(): void
    {
        $bet = new Bet();
        $bet->result = [];

        $outcome = $this->engine->calculateOutcome($bet, $this->game, 0.5);

        $this->assertCount(2, $outcome['state']['player_cards']);
        $this->assertArrayHasKey('status', $outcome['state']);
    }

    public function test_deal_is_pending_when_no_natural_blackjack(): void
    {
        $bet = new Bet();
        $bet->result = ['payload' => ['action' => 'deal']];

        // Find a PRNG that yields a non-21 opening hand
        for ($prng = 0.0; $prng < 1.0; $prng += 0.0001) {
            $outcome = $this->engine->calculateOutcome($bet, $this->game, $prng);
            if ($outcome['state']['player_score'] !== 21) {
                $this->assertFalse($outcome['is_finished']);
                $this->assertSame(0.0, $outcome['payout_multiplier']);
                $this->assertSame('playing', $outcome['state']['status']);
                return;
            }
        }

        $this->fail('expected a non-21 opening hand somewhere in the PRNG range');
    }

    public function test_natural_blackjack_pays_three_to_two(): void
    {
        $bet = new Bet();
        $bet->result = ['payload' => ['action' => 'deal']];

        for ($prng = 0.0; $prng < 1.0; $prng += 0.0001) {
            $outcome = $this->engine->calculateOutcome($bet, $this->game, $prng);
            if (($outcome['state']['status'] ?? null) === 'blackjack') {
                $this->assertTrue($outcome['is_finished']);
                $this->assertSame(2.5, $outcome['payout_multiplier']);
                $this->assertSame(0, $outcome['state']['dealer_hidden_count']);
                return;
            }
        }

        $this->fail('expected to find a natural blackjack');
    }

    public function test_hit_appends_a_card(): void
    {
        $state = [
            'player_cards' => [
                ['rank' => '5', 'suit' => 'H', 'value' => 5],
                ['rank' => '6', 'suit' => 'D', 'value' => 6],
            ],
            'dealer_visible_cards' => [['rank' => '9', 'suit' => 'S', 'value' => 9]],
            'dealer_full_cards' => [
                ['rank' => '9', 'suit' => 'S', 'value' => 9],
                ['rank' => '7', 'suit' => 'C', 'value' => 7],
            ],
            'dealer_hidden_count' => 1,
            'player_score' => 11,
            'dealer_visible_score' => 9,
            'status' => 'playing',
        ];

        $bet = new Bet();
        $bet->result = array_merge($state, ['payload' => ['action' => 'hit']]);

        $outcome = $this->engine->calculateOutcome($bet, $this->game, 0.1);

        $this->assertCount(3, $outcome['state']['player_cards']);
    }

    public function test_hit_busting_settles_with_zero_payout(): void
    {
        $state = [
            'player_cards' => [
                ['rank' => '10', 'suit' => 'H', 'value' => 10],
                ['rank' => 'K',  'suit' => 'D', 'value' => 10],
            ],
            'dealer_visible_cards' => [['rank' => '9', 'suit' => 'S', 'value' => 9]],
            'dealer_full_cards' => [
                ['rank' => '9', 'suit' => 'S', 'value' => 9],
                ['rank' => '7', 'suit' => 'C', 'value' => 7],
            ],
            'dealer_hidden_count' => 1,
            'player_score' => 20,
            'dealer_visible_score' => 9,
            'status' => 'playing',
        ];

        $bet = new Bet();
        $bet->result = array_merge($state, ['payload' => ['action' => 'hit']]);

        // Any reasonable card will bust a 20
        $outcome = $this->engine->calculateOutcome($bet, $this->game, 0.6);

        $this->assertTrue($outcome['is_finished']);
        $this->assertSame('bust', $outcome['state']['status']);
        $this->assertSame(0.0, $outcome['payout_multiplier']);
        $this->assertSame(0, $outcome['state']['dealer_hidden_count']);
    }

    public function test_stand_dealer_bust_pays_one_to_one(): void
    {
        $state = [
            'player_cards' => [
                ['rank' => '10', 'suit' => 'H', 'value' => 10],
                ['rank' => '8',  'suit' => 'D', 'value' => 8],
            ],
            'dealer_visible_cards' => [['rank' => '10', 'suit' => 'S', 'value' => 10]],
            'dealer_full_cards' => [
                ['rank' => '10', 'suit' => 'S', 'value' => 10],
                ['rank' => '6',  'suit' => 'C', 'value' => 6],
            ],
            'dealer_hidden_count' => 1,
            'player_score' => 18,
            'dealer_visible_score' => 10,
            'status' => 'playing',
        ];

        // Find a PRNG that causes the dealer to bust from 16
        for ($prng = 0.0; $prng < 1.0; $prng += 0.0001) {
            $bet = new Bet();
            $bet->result = array_merge($state, ['payload' => ['action' => 'stand']]);

            $outcome = $this->engine->calculateOutcome($bet, $this->game, $prng);

            if (($outcome['state']['status'] ?? null) === 'dealer_bust') {
                $this->assertTrue($outcome['is_finished']);
                $this->assertSame(2.0, $outcome['payout_multiplier']);
                return;
            }
        }

        $this->fail('expected dealer to bust from 16 with some PRNG seed');
    }

    public function test_stand_player_higher_wins(): void
    {
        $state = [
            'player_cards' => [
                ['rank' => '10', 'suit' => 'H', 'value' => 10],
                ['rank' => '10', 'suit' => 'D', 'value' => 10],
            ],
            'dealer_visible_cards' => [['rank' => '9', 'suit' => 'S', 'value' => 9]],
            'dealer_full_cards' => [
                ['rank' => '9', 'suit' => 'S', 'value' => 9],
                ['rank' => '8', 'suit' => 'C', 'value' => 8],
            ],
            'dealer_hidden_count' => 1,
            'player_score' => 20,
            'dealer_visible_score' => 9,
            'status' => 'playing',
        ];

        $bet = new Bet();
        $bet->result = array_merge($state, ['payload' => ['action' => 'stand']]);

        // Dealer has 17 → stands. Player 20 > 17 → win.
        $outcome = $this->engine->calculateOutcome($bet, $this->game, 0.001);

        $this->assertTrue($outcome['is_finished']);
        $this->assertSame('win', $outcome['state']['status']);
        $this->assertSame(2.0, $outcome['payout_multiplier']);
    }

    public function test_stand_player_lower_loses(): void
    {
        $state = [
            'player_cards' => [
                ['rank' => '10', 'suit' => 'H', 'value' => 10],
                ['rank' => '5',  'suit' => 'D', 'value' => 5],
            ],
            'dealer_visible_cards' => [['rank' => '10', 'suit' => 'S', 'value' => 10]],
            'dealer_full_cards' => [
                ['rank' => '10', 'suit' => 'S', 'value' => 10],
                ['rank' => '9',  'suit' => 'C', 'value' => 9],
            ],
            'dealer_hidden_count' => 1,
            'player_score' => 15,
            'dealer_visible_score' => 10,
            'status' => 'playing',
        ];

        $bet = new Bet();
        $bet->result = array_merge($state, ['payload' => ['action' => 'stand']]);

        // Dealer already at 19 → stands. Player 15 < 19 → lose.
        $outcome = $this->engine->calculateOutcome($bet, $this->game, 0.5);

        $this->assertTrue($outcome['is_finished']);
        $this->assertSame('lose', $outcome['state']['status']);
        $this->assertSame(0.0, $outcome['payout_multiplier']);
    }

    public function test_stand_tie_is_push(): void
    {
        $state = [
            'player_cards' => [
                ['rank' => '10', 'suit' => 'H', 'value' => 10],
                ['rank' => '9',  'suit' => 'D', 'value' => 9],
            ],
            'dealer_visible_cards' => [['rank' => '10', 'suit' => 'S', 'value' => 10]],
            'dealer_full_cards' => [
                ['rank' => '10', 'suit' => 'S', 'value' => 10],
                ['rank' => '9',  'suit' => 'C', 'value' => 9],
            ],
            'dealer_hidden_count' => 1,
            'player_score' => 19,
            'dealer_visible_score' => 10,
            'status' => 'playing',
        ];

        $bet = new Bet();
        $bet->result = array_merge($state, ['payload' => ['action' => 'stand']]);

        $outcome = $this->engine->calculateOutcome($bet, $this->game, 0.5);

        $this->assertTrue($outcome['is_finished']);
        $this->assertSame('push', $outcome['state']['status']);
        $this->assertSame(1.0, $outcome['payout_multiplier']);
    }

    public function test_dealer_must_stand_on_seventeen(): void
    {
        $state = [
            'player_cards' => [
                ['rank' => '10', 'suit' => 'H', 'value' => 10],
                ['rank' => '8',  'suit' => 'D', 'value' => 8],
            ],
            'dealer_visible_cards' => [['rank' => '10', 'suit' => 'S', 'value' => 10]],
            'dealer_full_cards' => [
                ['rank' => '10', 'suit' => 'S', 'value' => 10],
                ['rank' => '7',  'suit' => 'C', 'value' => 7],
            ],
            'dealer_hidden_count' => 1,
            'player_score' => 18,
            'dealer_visible_score' => 10,
            'status' => 'playing',
        ];

        $bet = new Bet();
        $bet->result = array_merge($state, ['payload' => ['action' => 'stand']]);

        $outcome = $this->engine->calculateOutcome($bet, $this->game, 0.5);

        // Dealer already at 17 → must stand → no extra cards drawn
        $this->assertCount(2, $outcome['state']['dealer_full_cards']);
        $this->assertSame(17, $outcome['state']['dealer_score']);
    }

    public function test_player_21_auto_stands_on_hit(): void
    {
        $state = [
            'player_cards' => [
                ['rank' => '10', 'suit' => 'H', 'value' => 10],
                ['rank' => '6',  'suit' => 'D', 'value' => 6],
            ],
            'dealer_visible_cards' => [['rank' => '5', 'suit' => 'S', 'value' => 5]],
            'dealer_full_cards' => [
                ['rank' => '5', 'suit' => 'S', 'value' => 5],
                ['rank' => '5', 'suit' => 'C', 'value' => 5],
            ],
            'dealer_hidden_count' => 1,
            'player_score' => 16,
            'dealer_visible_score' => 5,
            'status' => 'playing',
        ];

        // Find a PRNG that draws a 5 (so player hits 21)
        for ($prng = 0.0; $prng < 1.0; $prng += 0.0001) {
            $bet = new Bet();
            $bet->result = array_merge($state, ['payload' => ['action' => 'hit']]);

            $outcome = $this->engine->calculateOutcome($bet, $this->game, $prng);
            if ($outcome['state']['player_score'] === 21 && $outcome['is_finished']) {
                $this->assertSame(0, $outcome['state']['dealer_hidden_count']);
                return;
            }
        }

        $this->fail('expected a hit-to-21 auto-stand within the PRNG range');
    }

    public function test_aces_flex_from_eleven_to_one_to_avoid_bust(): void
    {
        // Hand: A + A + 9 → if both aces were 11, score = 21. If one demotes, 21. Good.
        // Better: A + 9 + 5 = 11 + 9 + 5 = 25 busts; ace demotes to 1 → 15.
        $state = [
            'player_cards' => [
                ['rank' => 'A', 'suit' => 'H', 'value' => 11],
                ['rank' => '9', 'suit' => 'D', 'value' => 9],
            ],
            'dealer_visible_cards' => [['rank' => '5', 'suit' => 'S', 'value' => 5]],
            'dealer_full_cards' => [
                ['rank' => '5', 'suit' => 'S', 'value' => 5],
                ['rank' => '5', 'suit' => 'C', 'value' => 5],
            ],
            'dealer_hidden_count' => 1,
            'player_score' => 20, // A=11 + 9
            'dealer_visible_score' => 5,
            'status' => 'playing',
        ];

        // Draw something that would bust if ace stayed at 11
        for ($prng = 0.0; $prng < 1.0; $prng += 0.0001) {
            $bet = new Bet();
            $bet->result = array_merge($state, ['payload' => ['action' => 'hit']]);

            $outcome = $this->engine->calculateOutcome($bet, $this->game, $prng);
            $newCard = end($outcome['state']['player_cards']);
            if ($newCard['value'] >= 5) {
                // Would have been 25+ without flex; with flex it's ≤21
                $this->assertLessThanOrEqual(21, $outcome['state']['player_score']);
                return;
            }
        }

        $this->fail('expected to find a card that would force ace demotion');
    }

    public function test_unknown_action_throws_invalid_argument(): void
    {
        $bet = new Bet();
        $bet->result = [
            'player_cards' => [['rank' => '10', 'suit' => 'H', 'value' => 10]],
            'payload' => ['action' => 'fold'],
        ];

        $this->expectException(InvalidArgumentException::class);
        $this->engine->calculateOutcome($bet, $this->game, 0.5);
    }

    public function test_double_action_is_not_implemented(): void
    {
        // Documents the known bug: 'double' is whitelisted by the controller
        // but unsupported here.
        $bet = new Bet();
        $bet->result = [
            'player_cards' => [['rank' => '10', 'suit' => 'H', 'value' => 10]],
            'payload' => ['action' => 'double'],
        ];

        $this->expectException(InvalidArgumentException::class);
        $this->engine->calculateOutcome($bet, $this->game, 0.5);
    }
}
