<?php

namespace App\Modules\Finance\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Finance\Data\CreateDepositInvoiceData;
use App\Modules\Finance\Requests\CreateDepositInvoiceRequest;
use App\Modules\Finance\Services\DepositService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TransactionController extends Controller
{
    public function deposit(CreateDepositInvoiceRequest $request, DepositService $depositService): JsonResponse
    {
        $invoice = $depositService->createInvoice(
            $request->user(),
            CreateDepositInvoiceData::fromArray($request->validated()),
        );

        return response()->json([
            'deposit_invoice' => $invoice,
        ], 201);
    }

    public function withdraw(Request $request): JsonResponse
    {
        return response()->json(['message' => 'Withdrawal request placeholder. Andrew implements settlement checks here.'], 202);
    }
}
