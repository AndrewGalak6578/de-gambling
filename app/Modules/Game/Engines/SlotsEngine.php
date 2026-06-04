<?php

namespace App\Modules\Game\Engines;

use App\Models\Bet;
use App\Models\Game;

class SlotsEngine implements GameEngineInterface
{
    /**
     * Reel symbol table. Indexed by reel face.
     * weight: relative frequency on each independent reel.
     * three:  3-of-a-kind base payout multiplier (before RTP calibration).
     */
    private const SYMBOLS = [
        ['key' => 'cherry',  'weight' => 26, 'three' => 10],
        ['key' => 'lemon',   'weight' => 22, 'three' => 16],
        ['key' => 'bell',    'weight' => 18, 'three' => 27],
        ['key' => 'star',    'weight' => 14, 'three' => 55],
        ['key' => 'diamond', 'weight' => 10, 'three' => 110],
        ['key' => 'seven',   'weight' => 7,  'three' => 270],
        ['key' => 'crown',   'weight' => 3,  'three' => 1300],
    ];

    private const CONSOLATION_MULTIPLIER = 0.1;

    public function calculateOutcome(Bet $bet, Game $game, float $prngResult): array
    {
        $rtp = (float) ($game->rtp_percentage ?? 95.0);
        $rtpFactor = $rtp / 100.0;

        $reelIndices = $this->spinReels($prngResult);

        [$matchType, $baseMultiplier] = $this->evaluate($reelIndices);

        $multiplier = $baseMultiplier * $rtpFactor;
        $isWin = $multiplier > 0.0;

        return [
            'payout_multiplier' => $multiplier,
            'state' => [
                'reels' => array_map(fn ($i) => self::SYMBOLS[$i]['key'], $reelIndices),
                'reel_indices' => $reelIndices,
                'match_type' => $matchType,
                'is_win' => $isWin,
            ],
            'animations' => ['trigger' => 'slots_spin', 'duration' => 2600],
            'is_finished' => true,
        ];
    }

    public static function symbolTable(): array
    {
        return self::SYMBOLS;
    }

    /**
     * Split the single PRNG float into three weighted reel picks.
     */
    private function spinReels(float $prng): array
    {
        $clamped = max(0.0, min(0.9999999, $prng));

        $rolls = [
            fmod($clamped * 1.0, 1.0),
            fmod($clamped * 1000.0, 1.0),
            fmod($clamped * 1000000.0, 1.0),
        ];

        $total = array_sum(array_column(self::SYMBOLS, 'weight'));

        $reels = [];
        foreach ($rolls as $r) {
            $target = $r * $total;
            $cumulative = 0;
            $picked = count(self::SYMBOLS) - 1;
            foreach (self::SYMBOLS as $i => $symbol) {
                $cumulative += $symbol['weight'];
                if ($target < $cumulative) {
                    $picked = $i;
                    break;
                }
            }
            $reels[] = $picked;
        }
        return $reels;
    }

    /**
     * @param  int[]  $reels
     * @return array{0:string,1:float}
     */
    private function evaluate(array $reels): array
    {
        if ($reels[0] === $reels[1] && $reels[1] === $reels[2]) {
            return ['three_of_a_kind', (float) self::SYMBOLS[$reels[0]]['three']];
        }

        // Cherry consolation: any cherry visible refunds a fraction of the stake.
        $cherryIndex = 0;
        if (in_array($cherryIndex, $reels, true)) {
            return ['cherry_consolation', self::CONSOLATION_MULTIPLIER];
        }

        return ['none', 0.0];
    }
}
