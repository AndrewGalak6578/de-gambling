<?php

namespace App\Modules\Game\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GameController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(['message' => 'Game list placeholder. Yetkin implements this.']);
    }

    public function bet(Request $request, int $game): JsonResponse
    {
        return response()->json(['message' => 'Bet placeholder. Yetkin calls settlement contract here.']);
    }
}
