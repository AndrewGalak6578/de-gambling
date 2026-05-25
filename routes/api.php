<?php

use App\Modules\Admin\Controllers\AdminGameController;
use App\Modules\Admin\Controllers\UserManagementController;
use App\Modules\Finance\Controllers\AdminWithdrawalController;
use App\Modules\Finance\Controllers\PaymentWebhookController;
use App\Modules\ResponsibleGambling\Controllers\AdminRiskController;
use App\Modules\User\Controllers\UserRestrictionController;
use App\Modules\User\Controllers\WalletController as UserWalletController;
use App\Modules\User\Controllers\SelfExclusionController;
use App\Modules\User\Controllers\DashboardController;
use App\Modules\Finance\Controllers\TransactionController;
use App\Modules\Finance\Controllers\WalletController;
use App\Modules\Game\Controllers\GameController;
use App\Modules\User\Controllers\AuthController;
use App\Modules\User\Controllers\ProfileController;
use App\Http\Middleware\EnsureUserIsActive;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {

    Route::post('/auth/register', [AuthController::class, 'register']);
    Route::post('/auth/login', [AuthController::class, 'login']);
    Route::post('/payments/webhook', PaymentWebhookController::class);

    Route::middleware(['auth:sanctum', EnsureUserIsActive::class])->group(function () {

        Route::get('/auth/me', [AuthController::class, 'me']);
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::post('/auth/logout-all', [AuthController::class, 'logoutAll']);

        Route::patch('/user/profile', [ProfileController::class, 'update']);
        Route::patch('/user/password', [ProfileController::class, 'updatePassword']);

        Route::get('/user/dashboard', [DashboardController::class, 'index']);

        Route::get('/user/self-exclusion', [SelfExclusionController::class, 'show']);
        Route::post('/user/self-exclusion', [SelfExclusionController::class, 'store']);

        Route::get('/user/restrictions', [UserRestrictionController::class, 'show']);
        Route::patch('/user/restrictions', [UserRestrictionController::class, 'update']);

        Route::get('/wallet', [WalletController::class, 'show']);
        Route::post('/wallet/deposit', [TransactionController::class, 'deposit']);
        Route::post('/wallet/withdraw', [TransactionController::class, 'withdraw']);

        Route::get('/games', [GameController::class, 'index']);
        Route::post('/games/{game}/bet', [GameController::class, 'bet']);

        Route::middleware('admin')->group(function () {

            Route::get('/admin/users', [UserManagementController::class, 'index']);

            Route::patch('/admin/users/{user}/disable', [UserManagementController::class, 'disable']);

            Route::delete('/admin/users/{user}', [UserManagementController::class, 'destroy']);

            Route::get('/admin/withdrawals', [AdminWithdrawalController::class, 'index']);
            Route::patch('/admin/withdrawals/{transaction}/approve', [AdminWithdrawalController::class, 'approve']);
            Route::patch('/admin/withdrawals/{transaction}/reject', [AdminWithdrawalController::class, 'reject']);

            Route::get('/admin/risk-events', [AdminRiskController::class, 'events']);
            Route::get('/admin/interventions', [AdminRiskController::class, 'activeInterventions']);

            Route::get('/admin/games', [AdminGameController::class, 'index']);
            Route::patch('/admin/games/{game}/rtp', [AdminGameController::class, 'updateRtp']);
            Route::patch('/admin/games/{game}/status', [AdminGameController::class, 'updateStatus']);
        });
    });
});
