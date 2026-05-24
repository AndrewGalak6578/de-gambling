<?php

namespace App\Modules\User\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UserRestrictionController extends Controller
{
    public function show(Request $request)
    {
        $restriction = DB::table('user_restrictions')
            ->where('user_id', $request->user()->id)
            ->first();

        return response()->json([
            'restrictions' => $restriction,
        ]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'daily_deposit_limit' => 'nullable|numeric|min:0',
            'daily_bet_limit' => 'nullable|numeric|min:0',
            'daily_loss_limit' => 'nullable|numeric|min:0',
            'cool_off_until' => 'nullable|date|after:now',
        ]);

        DB::table('user_restrictions')->updateOrInsert(
            ['user_id' => $request->user()->id],
            array_merge($data, [
                'updated_at' => now(),
                'created_at' => now(),
            ])
        );

        return response()->json([
            'message' => 'User restrictions updated successfully',
            'restrictions' => DB::table('user_restrictions')
                ->where('user_id', $request->user()->id)
                ->first(),
        ]);
    }
}