<?php

namespace App\Modules\ResponsibleGambling\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Intervention;
use App\Models\RiskEvent;
use App\Models\RiskScoreOverride;
use App\Models\User;
use App\Modules\ResponsibleGambling\Services\RiskScoreService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminRiskController extends Controller
{
    public function __construct(
        private RiskScoreService $riskScoreService,
    ) {}

    public function events(): JsonResponse
    {
        return response()->json([
            'risk_events' => RiskEvent::query()
                ->with('user:id,name,email')
                ->latest()
                ->limit(100)
                ->get(),
        ]);
    }

    public function summaries(): JsonResponse
    {
        $overrides = RiskScoreOverride::query()
            ->get()
            ->keyBy('user_id');

        return response()->json([
            'users' => User::query()
                ->orderBy('id')
                ->get(['id', 'name', 'email', 'status'])
                ->map(function (User $user) use ($overrides) {
                    $override = $overrides->get($user->id);

                    return [
                        'id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                        'status' => $user->status,
                        'raw_score' => $this->riskScoreService->calculateRawForUser($user->id),
                        'effective_score' => $this->riskScoreService->calculateForUser($user->id),
                        'override' => $override ? [
                            'score_adjustment' => $override->score_adjustment,
                            'disabled' => $override->disabled,
                            'reason' => $override->reason,
                            'updated_at' => $override->updated_at,
                        ] : [
                            'score_adjustment' => 0,
                            'disabled' => false,
                            'reason' => null,
                            'updated_at' => null,
                        ],
                    ];
                })
                ->values(),
        ]);
    }

    public function updateOverride(Request $request, User $user): JsonResponse
    {
        $data = $request->validate([
            'score_adjustment' => 'required|integer|min:-100|max:100',
            'disabled' => 'required|boolean',
            'reason' => 'nullable|string|max:500',
        ]);

        $override = RiskScoreOverride::updateOrCreate(
            ['user_id' => $user->id],
            [
                'admin_user_id' => $request->user()?->id,
                'score_adjustment' => $data['score_adjustment'],
                'disabled' => $data['disabled'],
                'reason' => $data['reason'] ?? null,
            ],
        );

        return response()->json([
            'message' => 'Risk override updated.',
            'override' => $override,
            'raw_score' => $this->riskScoreService->calculateRawForUser($user->id),
            'effective_score' => $this->riskScoreService->calculateForUser($user->id),
        ]);
    }

    public function activeInterventions(): JsonResponse
    {
        return response()->json([
            'interventions' => Intervention::query()
                ->with('user:id,name,email')
                ->where('status', 'active')
                ->where(function ($query) {
                    $query->whereNull('starts_at')
                        ->orWhere('starts_at', '<=', now());
                })
                ->where(function ($query) {
                    $query->whereNull('ends_at')
                        ->orWhere('ends_at', '>', now());
                })
                ->latest()
                ->get(),
        ]);
    }
}
