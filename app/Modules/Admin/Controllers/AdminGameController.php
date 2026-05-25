<?php

namespace App\Modules\Admin\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Game;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminGameController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'games' => Game::all(),
        ]);
    }

    public function updateRtp(Request $request, Game $game): JsonResponse
    {
        $data = $request->validate([
            'rtp_percentage' => 'required|numeric|min:1|max:99.99',
        ]);

        $game->update(['rtp_percentage' => $data['rtp_percentage']]);

        return response()->json([
            'message' => 'RTP updated successfully',
            'game' => $game,
        ]);
    }

    public function updateStatus(Request $request, Game $game): JsonResponse
    {
        $data = $request->validate([
            'status' => 'required|in:active,inactive,retired',
        ]);

        $game->update(['status' => $data['status']]);

        return response()->json([
            'message' => 'Game status updated successfully',
            'game' => $game,
        ]);
    }
}
