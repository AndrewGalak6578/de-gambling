<?php

namespace App\Modules\Admin\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;

class UserManagementController extends Controller
{
    public function index()
    {
        return response()->json([
            'users' => User::all(),
        ]);
    }

    public function disable(Request $request, User $user)
    {
        $data = $request->validate([
            'reason' => 'nullable|string|max:500',
        ]);

        $user->update([
            'status' => 'disabled',
            'disabled_at' => now(),
            'disabled_reason' => $data['reason'] ?? null,
        ]);

        return response()->json([
            'message' => 'User disabled successfully',
            'user' => $user,
        ]);
    }

    public function destroy(User $user)
    {
        $user->delete();

        return response()->json([
            'message' => 'User deleted successfully',
        ]);
    }
}