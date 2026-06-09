<?php

namespace App\Modules\Finance\Controllers;

use App\Http\Controllers\Controller;
use App\Models\DepositInvoice;
use App\Modules\Finance\Contracts\BalanceServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WalletController extends Controller
{
    public function show(Request $request, BalanceServiceInterface $balanceService): JsonResponse
    {
        $lastDepositInvoice = DepositInvoice::query()
            ->where('user_id', $request->user()->id)
            ->latest()
            ->first();

        return response()->json([
            'currency' => 'USD',
            'balance' => $balanceService->getBalance($request->user()->id, 'USD'),
            'last_deposit_invoice' => $lastDepositInvoice,
        ]);
    }
}
