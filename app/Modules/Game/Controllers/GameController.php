<?php

namespace App\Modules\Game\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Game;
use App\Models\Bet;
use App\Models\Wallet;
use App\Modules\Game\Engines\GameEngineFactory;
use App\Modules\Game\Services\ProvablyFairService;
use App\Modules\Finance\Contracts\GameSettlementServiceInterface;
use App\Modules\ResponsibleGambling\Services\ResponsibleGamblingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class GameController extends Controller
{
    private const CONTINUATION_ACTIONS = ['hit', 'stand', 'double', 'split'];

    public function __construct(
        private ProvablyFairService $provablyFairService,
        private GameSettlementServiceInterface $settlementService,
        private ResponsibleGamblingService $responsibleGamblingService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = Game::where('status', 'active');

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('slug', 'like', "%{$search}%");
            });
        }

        return response()->json($query->get());
    }

    public function show(Request $request, Game $game): JsonResponse
    {
        if ($game->status !== 'active') {
            return response()->json(['message' => 'Game not found.'], 404);
        }

        $user = $request->user();
        $activeBet = null;
        if ($user) {
            $activeBet = Bet::where('user_id', $user->id)
                ->where('game_id', $game->id)
                ->where('status', 'pending')
                ->first();
        }

        return response()->json([
            'game' => $game,
            'active_bet' => $activeBet ? $this->scrubBet($activeBet) : null,
        ]);
    }

    public function bet(Request $request, int $gameId): JsonResponse
    {
        $game = Game::where('status', 'active')->findOrFail($gameId);
        $user = $request->user();
        $this->responsibleGamblingService->ensureCanBet($user);

        $payload = $request->input('payload', []);
        $action = is_array($payload) ? ($payload['action'] ?? null) : null;

        $activeBet = Bet::where('user_id', $user->id)
            ->where('game_id', $game->id)
            ->where('status', 'pending')
            ->first();

        // Continuation: advance an existing pending hand (blackjack hit/stand etc.)
        if ($activeBet && in_array($action, self::CONTINUATION_ACTIONS, true)) {
            return $this->continueBet($activeBet, $game, $user, $action);
        }

        if ($activeBet) {
            return response()->json([
                'message' => 'You have an active unfinished bet that you must complete.',
                'bet' => $this->scrubBet($activeBet),
            ], 400);
        }

        if (in_array($action, self::CONTINUATION_ACTIONS, true)) {
            return response()->json([
                'message' => 'No active bet to continue.',
            ], 400);
        }

        return $this->newBet($request, $game, $user, $payload);
    }

    /* ──────────────────── NEW BET ──────────────────── */

    private function newBet(Request $request, Game $game, $user, array $payload): JsonResponse
    {
        $request->validate([
            'bet_amount' => 'required|numeric|min:0.01',
            'client_seed' => 'required|string|max:64',
            'payload' => 'nullable|array',
        ]);

        $amount = (string) $request->input('bet_amount');
        $clientSeed = $request->input('client_seed');

        // Pre-check balance so we don't waste a server seed on a dead bet
        $balance = (string) (Wallet::where('user_id', $user->id)
            ->where('currency', 'USD')
            ->value('balance') ?? '0.00');
        if (bccomp($balance, $amount, 8) < 0) {
            return response()->json([
                'message' => 'Insufficient balance.',
                'error' => 'insufficient_balance',
                'currency' => 'USD',
                'requested' => $amount,
                'balance' => $balance,
            ], 402);
        }

        // Provably-fair seed pair
        $serverSeed = $this->provablyFairService->generateServerSeed();
        $serverSeedHash = $this->provablyFairService->hashServerSeed($serverSeed);
        $nonce = 1;
        $prngResult = $this->provablyFairService->generateResult($serverSeed, $clientSeed, $nonce);

        $engine = GameEngineFactory::make($game->slug);

        $betDto = new Bet();
        $betDto->bet_amount = $amount;
        $betDto->client_seed = $clientSeed;
        $betDto->server_seed_hash = $serverSeedHash;
        $betDto->result = ['payload' => $payload];

        $outcome = $engine->calculateOutcome($betDto, $game, $prngResult);

        $isFinished = $outcome['is_finished'] ?? true;
        $payoutMultiplier = $outcome['payout_multiplier'];
        $payoutAmount = (string) ($amount * $payoutMultiplier);

        try {
            $bet = null;
            DB::transaction(function () use (
                $user, $game, $amount, $payoutAmount, $isFinished,
                $serverSeed, $serverSeedHash, $clientSeed, $prngResult,
                $outcome, $nonce, &$bet
            ) {
                $bet = new Bet();
                $bet->user_id = $user->id;
                $bet->game_id = $game->id;
                $bet->bet_amount = $amount;
                $bet->payout_amount = $isFinished ? $payoutAmount : '0';
                $bet->currency = 'USD';
                $bet->status = $isFinished ? 'settled' : 'pending';
                $bet->server_seed_hash = $serverSeedHash;
                $bet->client_seed = $clientSeed;
                $bet->result = array_merge($outcome['state'] ?? [], [
                    'server_seed' => $serverSeed, // scrubbed from response while pending
                    'nonce' => $nonce,
                    'prng_result' => $isFinished ? $prngResult : null,
                    'animations' => $outcome['animations'] ?? [],
                ]);
                $bet->save();

                if ($isFinished) {
                    $this->settlementService->settleBet(
                        $user->id,
                        $game->id,
                        $amount,
                        $payoutAmount,
                        $bet->result
                    );
                    $this->responsibleGamblingService->analyzeSettledBet($bet);
                }
            });
        } catch (RuntimeException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'balance' => Wallet::where('user_id', $user->id)
                    ->where('currency', 'USD')
                    ->value('balance') ?? '0.00',
            ], 402);
        }

        return response()->json([
            'bet' => $this->scrubBet($bet),
            'outcome' => $outcome,
        ]);
    }

    /* ──────────────────── CONTINUE BET ──────────────────── */

    private function continueBet(Bet $bet, Game $game, $user, string $action): JsonResponse
    {
        $state = $bet->result ?? [];
        $serverSeed = $state['server_seed'] ?? null;
        $nonce = (int) ($state['nonce'] ?? 1) + 1;

        if (! $serverSeed) {
            return response()->json([
                'message' => 'Bet state is corrupted — server seed missing.',
            ], 500);
        }

        $prngResult = $this->provablyFairService->generateResult(
            $serverSeed,
            $bet->client_seed,
            $nonce,
        );

        $engine = GameEngineFactory::make($game->slug);

        // Merge action into result so engine can see it via $bet->result['payload']
        $bet->result = array_merge($state, ['payload' => ['action' => $action]]);

        $outcome = $engine->calculateOutcome($bet, $game, $prngResult);

        $isFinished = $outcome['is_finished'] ?? true;
        $payoutMultiplier = $outcome['payout_multiplier'];
        $amount = (string) $bet->bet_amount;
        $payoutAmount = (string) ($amount * $payoutMultiplier);

        try {
            DB::transaction(function () use (
                $bet, $user, $game, $amount, $payoutAmount,
                $isFinished, $serverSeed, $prngResult, $outcome, $nonce
            ) {
                $bet->payout_amount = $isFinished ? $payoutAmount : '0';
                $bet->status = $isFinished ? 'settled' : 'pending';
                $bet->result = array_merge($bet->result ?? [], $outcome['state'] ?? [], [
                    'server_seed' => $serverSeed,
                    'nonce' => $nonce,
                    'prng_result' => $isFinished ? $prngResult : null,
                    'animations' => $outcome['animations'] ?? [],
                ]);
                $bet->save();

                if ($isFinished) {
                    $this->settlementService->settleBet(
                        $user->id,
                        $game->id,
                        $amount,
                        $payoutAmount,
                        $bet->result
                    );
                    $this->responsibleGamblingService->analyzeSettledBet($bet);
                }
            });
        } catch (RuntimeException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'balance' => Wallet::where('user_id', $user->id)
                    ->where('currency', 'USD')
                    ->value('balance') ?? '0.00',
            ], 402);
        }

        return response()->json([
            'bet' => $this->scrubBet($bet),
            'outcome' => $outcome,
        ]);
    }

    /**
     * Strip secrets from a bet before sending it to the client.
     *  - While pending: hide the server seed and any hidden dealer cards.
     *  - When settled: leave everything so the player can verify provable fairness.
     */
    private function scrubBet(Bet $bet): array
    {
        $array = $bet->toArray();
        if ($bet->status !== 'settled') {
            if (isset($array['result']['server_seed'])) {
                unset($array['result']['server_seed']);
            }
            if (isset($array['result']['dealer_full_cards'])) {
                unset($array['result']['dealer_full_cards']);
            }
            if (isset($array['result']['prng_result'])) {
                unset($array['result']['prng_result']);
            }
        }
        return $array;
    }
}
