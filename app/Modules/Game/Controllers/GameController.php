<?php

namespace App\Modules\Game\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Game;
use App\Models\Bet;
use App\Modules\Game\Engines\GameEngineFactory;
use App\Modules\Game\Services\ProvablyFairService;
use App\Modules\Finance\Contracts\GameSettlementServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GameController extends Controller
{
    public function __construct(
        private ProvablyFairService $provablyFairService,
        private GameSettlementServiceInterface $settlementService
    ) {}

    public function index(): JsonResponse
    {
        return response()->json(Game::where('status', 'active')->get());
    }

    public function bet(Request $request, int $gameId): JsonResponse
    {
        $game = Game::where('status', 'active')->findOrFail($gameId);
        $user = $request->user();

        // Check for an active unfinished bet
        $activeBet = Bet::where('user_id', $user->id)
            ->where('game_id', $game->id)
            ->where('status', 'pending')
            ->first();

        if ($activeBet) {
            // Usually, we'd pass the active bet back to the engine with a new payload (e.g. hitting in blackjack)
            // For now, return the active bet to allow the client to resume.
            return response()->json([
                'message' => 'You have an active unfinished bet that you must complete.',
                'bet' => $activeBet
            ], 400);
        }

        $request->validate([
            'bet_amount' => 'required|numeric|min:0.01',
            'client_seed' => 'required|string|max:64',
            'payload' => 'nullable|array'
        ]);

        $amount = (string) $request->input('bet_amount');
        $clientSeed = $request->input('client_seed');
        $payload = $request->input('payload', []);

        // Provably Fair System
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

        $bet = new Bet();
        $bet->user_id = $user->id;
        $bet->game_id = $game->id;
        $bet->bet_amount = $amount;
        $bet->payout_amount = $isFinished ? $payoutAmount : '0';
        $bet->currency = 'USDT';
        $bet->status = $isFinished ? 'settled' : 'pending';
        $bet->server_seed_hash = $serverSeedHash;
        $bet->client_seed = $clientSeed;
        $bet->result = array_merge($outcome['state'] ?? [], [
            'server_seed' => $isFinished ? $serverSeed : null,
            'prng_result' => $isFinished ? $prngResult : null,
            'animations' => $outcome['animations'] ?? []
        ]);
        $bet->save();

        // Only settle if finished
        if ($isFinished) {
            $this->settlementService->settleBet(
                $user->id,
                $game->id,
                $amount,
                $payoutAmount,
                $bet->result
            );
        }

        return response()->json([
            'bet' => $bet,
            'outcome' => $outcome
        ]);
    }
}
