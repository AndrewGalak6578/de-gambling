<?php

namespace App\Modules\Finance\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Finance\Services\WithdrawalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminWithdrawalController extends Controller
{
    public function index(WithdrawalService $withdrawalService): JsonResponse
    {
        return response()->json([
            'withdrawals' => $withdrawalService->pendingQueue(),
        ]);
    }

    public function approve(Request $request, int $transaction, WithdrawalService $withdrawalService): JsonResponse
    {
        $data = $request->validate([
            'tx_hash' => ['sometimes', 'nullable', 'string', 'max:255'],
        ]);

        $withdrawal = $withdrawalService->approve(
            $transaction,
            $request->user(),
            $data['tx_hash'] ?? null,
        );

        return response()->json([
            'withdrawal' => $withdrawal,
        ]);
    }

    public function reject(Request $request, int $transaction, WithdrawalService $withdrawalService): JsonResponse
    {
        $data = $request->validate([
            'reason' => ['sometimes', 'nullable', 'string', 'max:500'],
        ]);

        $withdrawal = $withdrawalService->reject(
            $transaction,
            $request->user(),
            $data['reason'] ?? null,
        );

        return response()->json([
            'withdrawal' => $withdrawal,
        ]);
    }
}
