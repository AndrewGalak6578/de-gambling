<?php

namespace App\Modules\Finance;

use App\Modules\Finance\Contracts\BalanceServiceInterface;
use App\Modules\Finance\Contracts\GameSettlementServiceInterface;
use App\Modules\Finance\Services\BalanceService;
use App\Modules\Finance\Services\GameSettlementService;
use Illuminate\Support\ServiceProvider;

class FinanceServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(BalanceServiceInterface::class, BalanceService::class);
        $this->app->bind(GameSettlementServiceInterface::class, GameSettlementService::class);
    }
}
