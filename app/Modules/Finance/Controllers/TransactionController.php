<?php

namespace App\Modules\Finance\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TransactionController extends Controller
{
    public function deposit(Request $request): JsonResponse
    {
        return response()->json(['message' => 'Deposit request placeholder. Andrew implements this.'], 202);
    }

    public function withdraw(Request $request): JsonResponse
    {
        return response()->json(['message' => 'Withdrawal request placeholder. Andrew implements settlement checks here.'], 202);
    }
}
