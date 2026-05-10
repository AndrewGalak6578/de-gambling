<?php

use App\Modules\Finance\Controllers\TransactionController;
use App\Modules\Finance\Controllers\WalletController;
use App\Modules\Game\Controllers\GameController;
use App\Modules\User\Controllers\AuthController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::post('/auth/register', [AuthController::class, 'register']);
    Route::post('/auth/login', [AuthController::class, 'login']);
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/auth/me', [AuthController::class, 'me']);
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::post('/auth/logout-all', [AuthController::class, 'logoutAll']);

        Route::get('/wallet', [WalletController::class, 'show']);
        Route::post('/wallet/deposit', [TransactionController::class, 'deposit']);
        Route::post('/wallet/withdraw', [TransactionController::class, 'withdraw']);

        Route::get('/games', [GameController::class, 'index']);
        Route::post('/games/{game}/bet', [GameController::class, 'bet']);
    });
});
