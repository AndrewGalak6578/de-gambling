<?php

namespace App\Modules\Finance\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Finance\Data\CreateDepositInvoiceData;
use App\Modules\Finance\Requests\CreateDepositInvoiceRequest;
use App\Modules\Finance\Requests\CreateWithdrawalRequest;
use App\Modules\Finance\Services\DepositService;
use App\Modules\Finance\Services\WithdrawalService;
use App\Modules\ResponsibleGambling\Services\ResponsibleGamblingService;
use Illuminate\Http\JsonResponse;

class TransactionController extends Controller
{
    public function deposit(
        CreateDepositInvoiceRequest $request,
        DepositService $depositService,
        ResponsibleGamblingService $responsibleGamblingService,
    ): JsonResponse {
        $responsibleGamblingService->ensureCanDeposit($request->user());

        $invoice = $depositService->createInvoice(
            $request->user(),
            CreateDepositInvoiceData::fromArray($request->validated()),
        );

        return response()->json([
            'deposit_invoice' => $invoice,
        ], 201);
    }

    public function withdraw(CreateWithdrawalRequest $request, WithdrawalService $withdrawalService): JsonResponse
    {
        $data = $request->validated();

        $withdrawal = $withdrawalService->requestWithdrawal(
            user: $request->user(),
            amount: (string) $data['amount'],
            destination: $data['destination'],
            providerMethod: $data['provider_method'] ?? null,
            metadata: $data['metadata'] ?? [],
        );

        return response()->json([
            'withdrawal' => $withdrawal,
        ], 202);
    }
}
