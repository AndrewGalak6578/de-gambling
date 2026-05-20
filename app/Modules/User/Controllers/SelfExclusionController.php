<?php

namespace App\Modules\User\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Intervention;
use Illuminate\Http\Request;

class SelfExclusionController extends Controller
{
    public function show(Request $request)
    {
        $intervention = Intervention::where('user_id', $request->user()->id)
            ->where('type', 'self_exclusion')
            ->where('status', 'active')
            ->where(function ($query) {
                $query->whereNull('ends_at')
                    ->orWhere('ends_at', '>', now());
            })
            ->latest()
            ->first();

        return response()->json([
            'self_excluded' => $intervention !== null,
            'intervention' => $intervention,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'days' => 'required|integer|min:1|max:365',
            'reason' => 'nullable|string|max:500',
        ]);

        $intervention = Intervention::create([
            'user_id' => $request->user()->id,
            'type' => 'self_exclusion',
            'status' => 'active',
            'starts_at' => now(),
            'ends_at' => now()->addDays($data['days']),
            'payload' => [
                'reason' => $data['reason'] ?? null,
                'days' => $data['days'],
            ],
        ]);

        return response()->json([
            'message' => 'Self-exclusion activated successfully',
            'intervention' => $intervention,
        ], 201);
    }
}