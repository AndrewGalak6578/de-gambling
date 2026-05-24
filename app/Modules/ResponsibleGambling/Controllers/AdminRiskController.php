<?php

namespace App\Modules\ResponsibleGambling\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Intervention;
use App\Models\RiskEvent;
use Illuminate\Http\JsonResponse;

class AdminRiskController extends Controller
{
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
