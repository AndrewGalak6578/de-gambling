<?php

namespace App\Modules\User\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Wallet;
use App\Modules\ResponsibleGambling\Services\ResponsibleGamblingService;
use App\Modules\ResponsibleGambling\Services\RiskScoreService;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(
        Request $request,
        RiskScoreService $riskScoreService,
        ResponsibleGamblingService $responsibleGamblingService,
    )
    {
        $user = $request->user();

        $selfExclusion = $responsibleGamblingService
            ->activeBlockingIntervention($user->id, ['self_exclusion']);

        $circuitBreaker = $responsibleGamblingService
            ->activeBlockingIntervention($user->id, ['circuit_breaker']);

        $walletBalance = Wallet::where('user_id', $user->id)
            ->sum('balance');

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],

            'dashboard' => [
                'wallet_balance' => $walletBalance,
                'risk_score' => $riskScoreService->calculateForUser($user->id),
                'self_excluded' => $selfExclusion !== null,
                'cool_off_active' => $circuitBreaker !== null,
                'active_intervention' => $circuitBreaker ?? $selfExclusion,
            ],
        ]);
    }
}