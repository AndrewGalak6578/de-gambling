<?php

namespace App\Modules\ResponsibleGambling\Controllers;

use App\Http\Controllers\Controller;
use App\Models\AdminAction;
use App\Models\Intervention;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminInterventionController extends Controller
{
    private const ADMIN_TYPES = [
        'admin_bet_block',
        'admin_deposit_block',
        'admin_win_limit',
        'admin_cool_off',
    ];

    public function index(User $user): JsonResponse
    {
        return response()->json([
            'interventions' => Intervention::query()
                ->where('user_id', $user->id)
                ->latest()
                ->get(),
        ]);
    }

    public function store(Request $request, User $user): JsonResponse
    {
        $endsAtRules = ['sometimes', 'nullable', 'date', 'after:now'];

        if ($request->filled('starts_at')) {
            $endsAtRules[] = 'after:starts_at';
        }

        $data = $request->validate([
            'type' => ['required', 'string', Rule::in(self::ADMIN_TYPES)],
            'starts_at' => ['sometimes', 'nullable', 'date'],
            'ends_at' => $endsAtRules,
            'reason' => ['required', 'string', 'max:500'],
            'payload' => ['sometimes', 'array'],
            'payload.max_wins' => ['required_if:type,admin_win_limit', 'integer', 'min:1', 'max:100'],
            'payload.window' => ['sometimes', 'string', Rule::in(['day', '24h'])],
        ]);

        $payload = $data['payload'] ?? [];
        $payload['reason'] = $data['reason'] ?? null;
        $payload['created_by_admin_user_id'] = $request->user()->id;
        $payload['created_by_admin_at'] = now()->toISOString();

        if ($data['type'] === 'admin_win_limit') {
            $payload['window'] = $payload['window'] ?? 'day';
        }

        $intervention = Intervention::create([
            'user_id' => $user->id,
            'type' => $data['type'],
            'status' => 'active',
            'starts_at' => $data['starts_at'] ?? now(),
            'ends_at' => $data['ends_at'] ?? null,
            'payload' => $payload,
        ]);

        AdminAction::create([
            'admin_user_id' => $request->user()->id,
            'target_type' => Intervention::class,
            'target_id' => $intervention->id,
            'action' => 'intervention_created',
            'payload' => [
                'user_id' => $user->id,
                'type' => $intervention->type,
                'reason' => $data['reason'] ?? null,
            ],
        ]);

        return response()->json([
            'intervention' => $intervention,
        ], 201);
    }

    public function revoke(Request $request, Intervention $intervention): JsonResponse
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
        ]);

        if ($intervention->status !== 'active') {
            return response()->json([
                'message' => 'Only active interventions can be revoked.',
                'intervention' => $intervention,
            ], 409);
        }

        $payload = is_array($intervention->payload) ? $intervention->payload : [];
        $payload['revoked_by_admin_user_id'] = $request->user()->id;
        $payload['revoked_at'] = now()->toISOString();
        $payload['revoke_reason'] = $data['reason'] ?? null;
        $payload['previous_ends_at'] = $intervention->ends_at?->toISOString();

        $intervention->update([
            'status' => 'revoked',
            'ends_at' => now(),
            'payload' => $payload,
        ]);

        AdminAction::create([
            'admin_user_id' => $request->user()->id,
            'target_type' => Intervention::class,
            'target_id' => $intervention->id,
            'action' => 'intervention_revoked',
            'payload' => [
                'user_id' => $intervention->user_id,
                'type' => $intervention->type,
                'reason' => $data['reason'] ?? null,
            ],
        ]);

        return response()->json([
            'intervention' => $intervention->fresh(),
        ]);
    }
}
