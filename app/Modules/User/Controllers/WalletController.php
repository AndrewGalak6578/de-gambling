<?php

namespace App\Modules\User\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class WalletController extends Controller
{
    public function link(Request $request)
    {
        $request->validate([
            'wallet_address' => 'required|string|unique:users,wallet_address',
        ]);

        $user = $request->user();

        $user->wallet_address = $request->wallet_address;
        $user->save();

        return response()->json([
            'message' => 'Wallet linked successfully',
            'wallet_address' => $user->wallet_address,
        ]);
    }
}