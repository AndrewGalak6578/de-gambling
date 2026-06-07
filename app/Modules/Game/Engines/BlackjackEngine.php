<?php

namespace App\Modules\Game\Engines;

use App\Models\Bet;
use App\Models\Game;
use InvalidArgumentException;

class BlackjackEngine implements GameEngineInterface
{
    private const SUITS = ['S', 'H', 'D', 'C']; // spade, heart, diamond, club
    private const RANKS = ['A', '2', '3', '4', '5', '6', '7', '8', '9', '10', 'J', 'Q', 'K'];
    private const DEALER_STAND_ON = 17;
    private const BLACKJACK_PAYOUT = 2.5; // 3:2 (stake returned + 1.5x win)
    private const WIN_PAYOUT = 2.0;       // 1:1 (stake returned + 1x win)
    private const PUSH_PAYOUT = 1.0;      // stake returned

    public function calculateOutcome(Bet $bet, Game $game, float $prngResult): array
    {
        $payload = $bet->result['payload'] ?? [];
        $action = $payload['action'] ?? 'deal';

        $state = $bet->result ?? [];

        if ($action === 'deal' || empty($state['player_cards'] ?? null)) {
            return $this->deal($prngResult);
        }

        if ($action === 'hit') {
            return $this->hit($state, $prngResult);
        }

        if ($action === 'stand') {
            return $this->stand($state, $prngResult);
        }

        throw new InvalidArgumentException("Unknown blackjack action: {$action}");
    }

    /* ──────────────────── DEAL ──────────────────── */

    private function deal(float $prng): array
    {
        $cards = $this->drawCards($prng, 4);

        $playerCards = [$cards[0], $cards[1]];
        $dealerFullCards = [$cards[2], $cards[3]];

        $playerScore = $this->score($playerCards);
        $dealerVisibleScore = $this->cardValue($dealerFullCards[0]);

        $state = [
            'player_cards' => $playerCards,
            'dealer_visible_cards' => [$dealerFullCards[0]],
            'dealer_hidden_count' => 1,
            'dealer_full_cards' => $dealerFullCards,
            'player_score' => $playerScore,
            'dealer_visible_score' => $dealerVisibleScore,
            'status' => 'playing',
        ];

        // Natural blackjack on opening hand
        if ($playerScore === 21) {
            $dealerScore = $this->score($dealerFullCards);
            $state['dealer_visible_cards'] = $dealerFullCards;
            $state['dealer_hidden_count'] = 0;
            $state['dealer_score'] = $dealerScore;

            if ($dealerScore === 21) {
                $state['status'] = 'push';
                return $this->settledOutcome($state, self::PUSH_PAYOUT, 'blackjack_deal');
            }
            $state['status'] = 'blackjack';
            return $this->settledOutcome($state, self::BLACKJACK_PAYOUT, 'blackjack_deal');
        }

        return [
            'payout_multiplier' => 0.0,
            'state' => $state,
            'animations' => ['trigger' => 'blackjack_deal', 'duration' => 1200],
            'is_finished' => false,
        ];
    }

    /* ──────────────────── HIT ──────────────────── */

    private function hit(array $state, float $prng): array
    {
        $newCard = $this->drawCards($prng, 1)[0];
        $state['player_cards'][] = $newCard;
        $state['player_score'] = $this->score($state['player_cards']);

        if ($state['player_score'] > 21) {
            $state['status'] = 'bust';
            // Reveal dealer's hand (good practice even on bust)
            $state['dealer_visible_cards'] = $state['dealer_full_cards'];
            $state['dealer_hidden_count'] = 0;
            $state['dealer_score'] = $this->score($state['dealer_full_cards']);
            return $this->settledOutcome($state, 0.0, 'blackjack_bust');
        }

        if ($state['player_score'] === 21) {
            // Auto-stand on 21 (no need to keep hitting)
            return $this->stand($state, $prng);
        }

        return [
            'payout_multiplier' => 0.0,
            'state' => $state,
            'animations' => ['trigger' => 'blackjack_hit', 'duration' => 600],
            'is_finished' => false,
        ];
    }

    /* ──────────────────── STAND ──────────────────── */

    private function stand(array $state, float $prng): array
    {
        $dealerCards = $state['dealer_full_cards'];
        $dealerScore = $this->score($dealerCards);

        // Dealer draws until 17+
        $cursor = $prng;
        $safety = 0;
        while ($dealerScore < self::DEALER_STAND_ON && $safety++ < 12) {
            $cursor = fmod($cursor * 1000, 1.0);
            $dealerCards[] = $this->drawCards($cursor, 1)[0];
            $dealerScore = $this->score($dealerCards);
        }

        $state['dealer_visible_cards'] = $dealerCards;
        $state['dealer_full_cards'] = $dealerCards;
        $state['dealer_hidden_count'] = 0;
        $state['dealer_score'] = $dealerScore;

        $playerScore = $state['player_score'];

        if ($dealerScore > 21) {
            $state['status'] = 'dealer_bust';
            return $this->settledOutcome($state, self::WIN_PAYOUT, 'blackjack_reveal');
        }
        if ($playerScore > $dealerScore) {
            $state['status'] = 'win';
            return $this->settledOutcome($state, self::WIN_PAYOUT, 'blackjack_reveal');
        }
        if ($playerScore < $dealerScore) {
            $state['status'] = 'lose';
            return $this->settledOutcome($state, 0.0, 'blackjack_reveal');
        }
        $state['status'] = 'push';
        return $this->settledOutcome($state, self::PUSH_PAYOUT, 'blackjack_reveal');
    }

    private function settledOutcome(array $state, float $multiplier, string $animation): array
    {
        return [
            'payout_multiplier' => $multiplier,
            'state' => $state,
            'animations' => ['trigger' => $animation, 'duration' => 1500],
            'is_finished' => true,
        ];
    }

    /* ──────────────────── CARDS ──────────────────── */

    /**
     * Draw N cards deterministically from a single PRNG float by splitting
     * its decimal expansion. Multi-deck shoe assumption — duplicates allowed.
     *
     * @return array<int, array{rank: string, suit: string, value: int}>
     */
    private function drawCards(float $prng, int $count): array
    {
        $clamped = max(0.0, min(0.9999999, $prng));
        $cards = [];
        for ($i = 0; $i < $count; $i++) {
            $rankRoll = fmod($clamped * pow(13, $i + 1), 1.0);
            $suitRoll = fmod($clamped * pow(13, $i + 1) * 4, 1.0);
            $rank = self::RANKS[(int) floor($rankRoll * 13)] ?? 'A';
            $suit = self::SUITS[(int) floor($suitRoll * 4)] ?? 'S';
            $cards[] = [
                'rank' => $rank,
                'suit' => $suit,
                'value' => $this->rankValue($rank),
            ];
        }
        return $cards;
    }

    private function rankValue(string $rank): int
    {
        return match (true) {
            $rank === 'A' => 11,
            in_array($rank, ['J', 'Q', 'K'], true) => 10,
            default => (int) $rank,
        };
    }

    private function cardValue(array $card): int
    {
        return $card['value'] ?? $this->rankValue($card['rank'] ?? '2');
    }

    /**
     * Score a hand, treating aces as 11 unless that busts the hand.
     */
    private function score(array $cards): int
    {
        $score = 0;
        $aceCount = 0;
        foreach ($cards as $card) {
            $score += $this->cardValue($card);
            if (($card['rank'] ?? null) === 'A') {
                $aceCount++;
            }
        }
        while ($score > 21 && $aceCount > 0) {
            $score -= 10;
            $aceCount--;
        }
        return $score;
    }
}
