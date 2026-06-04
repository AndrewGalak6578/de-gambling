<?php

namespace App\Modules\Finance\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Finance\Enums\TransactionStatus;
use App\Modules\Finance\Enums\TransactionType;
use App\Modules\Finance\Services\TransactionLogService;
use App\Modules\Finance\Services\WalletBalanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminWalletController extends Controller
{
    public function credit(
        Request $request,
        User $user,
        WalletBalanceService $walletBalanceService,
        TransactionLogService $transactionLogService,
    ): JsonResponse {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01', 'max:1000000'],
            'currency' => ['sometimes', 'string', 'in:USD'],
            'reason' => ['required', 'string', 'max:500'],
        ]);

        $currency = $data['currency'] ?? 'USD';
        $amount = number_format((float) $data['amount'], 8, '.', '');

        $result = DB::transaction(function () use ($user, $request, $walletBalanceService, $transactionLogService, $amount, $currency, $data) {
            $walletId = $walletBalanceService->increaseBalance($user->id, $amount, $currency);

            $transactionId = $transactionLogService->record(
                $user->id,
                $walletId,
                TransactionType::AdminCredit,
                TransactionStatus::Confirmed,
                $amount,
                $currency,
                $data['reason'],
                [
                    'admin_user_id' => $request->user()?->id,
                    'source' => 'admin_wallet_credit',
                ],
            );

            return [
                'wallet_id' => $walletId,
                'transaction_id' => $transactionId,
                'balance' => $walletBalanceService->getBalance($user->id, $currency),
            ];
        });

        return response()->json([
            'message' => 'Balance credited.',
            'user_id' => $user->id,
            'currency' => $currency,
            'amount' => $amount,
            ...$result,
        ]);
    }
}
