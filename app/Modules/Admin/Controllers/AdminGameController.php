<?php

namespace App\Modules\Admin\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Game;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminGameController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Game::query();

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('slug', 'like', "%{$search}%");
            });
        }

        return response()->json([
            'games' => $query->orderBy('created_at', 'desc')->get(),
        ]);
    }

    public function show(Game $game): JsonResponse
    {
        return response()->json(['game' => $game]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:64|unique:games,slug',
            'status' => 'nullable|in:active,inactive,retired',
            'rtp_percentage' => 'nullable|numeric|min:1|max:99.99',
            'config' => 'nullable|array',
        ]);

        $game = Game::create([
            'name' => $data['name'],
            'slug' => $data['slug'],
            'status' => $data['status'] ?? 'active',
            'rtp_percentage' => $data['rtp_percentage'] ?? 95.00,
            'config' => $data['config'] ?? null,
        ]);

        return response()->json([
            'message' => 'Game created successfully',
            'game' => $game,
        ], 201);
    }

    public function update(Request $request, Game $game): JsonResponse
    {
        $data = $request->validate([
            'name' => 'nullable|string|max:255',
            'slug' => 'nullable|string|max:64|unique:games,slug,' . $game->id,
            'status' => 'nullable|in:active,inactive,retired',
            'rtp_percentage' => 'nullable|numeric|min:1|max:99.99',
            'config' => 'nullable|array',
        ]);

        $game->update($data);

        return response()->json([
            'message' => 'Game updated successfully',
            'game' => $game,
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

    public function destroy(Game $game): JsonResponse
    {
        $game->delete();

        return response()->json([
            'message' => 'Game deleted successfully',
        ]);
    }
}
