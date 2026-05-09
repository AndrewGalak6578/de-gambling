<?php

use Illuminate\Support\Facades\Route;
use App\Modules\Finance\Controllers\WalletController;
use App\Modules\Finance\Controllers\TransactionController;
use App\Modules\Game\Controllers\GameController;

Route::prefix('v1')->group(function () {
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/wallet', [WalletController::class, 'show']);
        Route::post('/wallet/deposit', [TransactionController::class, 'deposit']);
        Route::post('/wallet/withdraw', [TransactionController::class, 'withdraw']);

        Route::get('/games', [GameController::class, 'index']);
        Route::post('/games/{game}/bet', [GameController::class, 'bet']);
    });
});
