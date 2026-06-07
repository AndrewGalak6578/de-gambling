<?php

namespace App\Modules\Game\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Bet;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BetHistoryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'per_page' => 'nullable|integer|min:1|max:100',
            'sort' => 'nullable|in:created_at,bet_amount,payout_amount',
            'direction' => 'nullable|in:asc,desc',
            'game_id' => 'nullable|integer|exists:games,id',
            'status' => 'nullable|in:settled,pending,cancelled',
        ]);

        $query = Bet::with('game:id,name,slug')
            ->where('user_id', $request->user()->id);

        if ($request->filled('game_id')) {
            $query->where('game_id', $request->game_id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $sort = $request->input('sort', 'created_at');
        $direction = $request->input('direction', 'desc');
        $query->orderBy($sort, $direction);

        $perPage = $request->input('per_page', 20);

        $bets = $query->paginate($perPage);

        return response()->json($bets);
    }
}
