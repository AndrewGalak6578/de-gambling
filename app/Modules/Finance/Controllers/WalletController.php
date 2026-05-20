<?php

namespace App\Modules\Finance\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Finance\Contracts\BalanceServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WalletController extends Controller
{
    public function show(Request $request, BalanceServiceInterface $balanceService): JsonResponse
    {
        return response()->json([
            'currency' => 'USDT',
            'balance' => $balanceService->getBalance($request->user()->id),
        ]);
    }
}
