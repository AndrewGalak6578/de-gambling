<?php

namespace App\Modules\Game\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Game;
use App\Models\Bet;
use App\Modules\Game\Engines\GameEngineFactory;
use App\Modules\Game\Services\ProvablyFairService;
use App\Modules\Finance\Contracts\BalanceServiceInterface;
use App\Modules\Finance\Contracts\GameSettlementServiceInterface;
use App\Modules\ResponsibleGambling\Services\ResponsibleGamblingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class GameController extends Controller
{
    private const CURRENCY = 'USD';

    public function __construct(
        private ProvablyFairService $provablyFairService,
        private GameSettlementServiceInterface $settlementService,
        private BalanceServiceInterface $balanceService,
        private ResponsibleGamblingService $responsibleGamblingService,
    ) {}

    public function index(): JsonResponse
    {
        return response()->json(Game::where('status', 'active')->get());
    }

    public function bet(Request $request, int $gameId): JsonResponse
    {
        $game = Game::where('status', 'active')->findOrFail($gameId);
        $user = $request->user();
        $this->responsibleGamblingService->ensureCanBet($user);

        $activeBet = Bet::where('user_id', $user->id)
            ->where('game_id', $game->id)
            ->where('status', 'pending')
            ->first();

        if ($activeBet) {
            return response()->json([
                'message' => 'You have an active unfinished bet that you must complete.',
                'bet' => $activeBet,
            ], 400);
        }

        $request->validate([
            'bet_amount' => 'required|numeric|min:0.01',
            'client_seed' => 'required|string|max:64',
            'payload' => 'nullable|array',
        ]);

        $amount = (string) $request->input('bet_amount');
        $clientSeed = $request->input('client_seed');
        $payload = $request->input('payload', []);

        if ($insufficient = $this->insufficientBalanceResponse($user->id, $amount)) {
            return $insufficient;
        }

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
            DB::transaction(function () use ($user, $game, $amount, $payoutAmount, $isFinished, $serverSeed, $serverSeedHash, $clientSeed, $prngResult, $outcome, &$bet) {
                $bet = new Bet();
                $bet->user_id = $user->id;
                $bet->game_id = $game->id;
                $bet->bet_amount = $amount;
                $bet->payout_amount = $isFinished ? $payoutAmount : '0';
                $bet->currency = self::CURRENCY;
                $bet->status = $isFinished ? 'settled' : 'pending';
                $bet->server_seed_hash = $serverSeedHash;
                $bet->client_seed = $clientSeed;
                $bet->result = array_merge($outcome['state'] ?? [], [
                    'server_seed' => $isFinished ? $serverSeed : null,
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
            // Race condition fallback: balance drained by concurrent activity
            // between pre-check and settlement (or any other runtime error in
            // finance settlement that surfaces as an insufficient funds path).
            if (str_contains(strtolower($e->getMessage()), 'insufficient')) {
                return $this->insufficientBalancePayload($user->id, $amount);
            }
            throw $e;
        }

        return response()->json([
            'bet' => $bet,
            'outcome' => $outcome,
        ]);
    }

    private function insufficientBalanceResponse(int $userId, string $amount): ?JsonResponse
    {
        $balance = $this->balanceService->getBalance($userId, self::CURRENCY);

        if (bccomp($balance, $amount, 8) >= 0) {
            return null;
        }

        return $this->insufficientBalancePayload($userId, $amount, $balance);
    }

    private function insufficientBalancePayload(int $userId, string $amount, ?string $balance = null): JsonResponse
    {
        $balance ??= $this->balanceService->getBalance($userId, self::CURRENCY);

        return response()->json([
            'message' => 'Insufficient balance.',
            'error' => 'insufficient_balance',
            'currency' => self::CURRENCY,
            'requested' => $amount,
            'balance' => $balance,
        ], 402);
    }
}
