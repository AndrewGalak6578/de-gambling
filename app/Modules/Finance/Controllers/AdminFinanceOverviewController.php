<?php

namespace App\Modules\Finance\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Bet;
use App\Models\DepositInvoice;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use App\Modules\Finance\Enums\DepositInvoiceStatus;
use App\Modules\Finance\Enums\TransactionStatus;
use App\Modules\Finance\Enums\TransactionType;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class AdminFinanceOverviewController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $walletBalance = Wallet::query()
            ->where('currency', 'USD')
            ->selectRaw('COALESCE(SUM(balance), 0) as total')
            ->value('total');

        $totalDeposits = Transaction::query()
            ->where('type', TransactionType::Deposit->value)
            ->where('status', TransactionStatus::Confirmed->value)
            ->selectRaw('COALESCE(SUM(amount), 0) as total')
            ->value('total');

        $totalWithdrawals = Transaction::query()
            ->where('type', TransactionType::Withdrawal->value)
            ->whereIn('status', [
                TransactionStatus::Pending->value,
                TransactionStatus::Confirmed->value,
            ])
            ->selectRaw('COALESCE(SUM(amount), 0) as total')
            ->value('total');

        $totalBetVolume = Bet::query()
            ->where('status', 'settled')
            ->selectRaw('COALESCE(SUM(bet_amount), 0) as total')
            ->value('total');

        $totalPayouts = Bet::query()
            ->where('status', 'settled')
            ->selectRaw('COALESCE(SUM(payout_amount), 0) as total')
            ->value('total');

        return response()->json([
            'stats' => [
                'users_count' => User::query()->count(),
                'wallet_balance_usd' => $this->decimal($walletBalance),
                'deposits_usd' => $this->decimal($totalDeposits),
                'withdrawals_usd' => $this->decimal($totalWithdrawals),
                'bet_volume_usd' => $this->decimal($totalBetVolume),
                'payouts_usd' => $this->decimal($totalPayouts),
                'pending_withdrawals_count' => Transaction::query()
                    ->where('type', TransactionType::Withdrawal->value)
                    ->where('status', TransactionStatus::Pending->value)
                    ->count(),
                'pending_deposit_invoices_count' => DepositInvoice::query()
                    ->whereIn('status', [
                        DepositInvoiceStatus::Pending->value,
                        DepositInvoiceStatus::Fixated->value,
                    ])
                    ->count(),
            ],
            'bets' => Bet::query()
                ->with(['user:id,name,email', 'game:id,name,slug'])
                ->latest()
                ->limit(100)
                ->get(),
            'deposit_invoices' => DepositInvoice::query()
                ->with('user:id,name,email')
                ->latest()
                ->limit(100)
                ->get(),
            'withdrawals' => Transaction::query()
                ->with('user:id,name,email')
                ->where('type', TransactionType::Withdrawal->value)
                ->latest()
                ->limit(100)
                ->get(),
            'transactions' => Transaction::query()
                ->with('user:id,name,email')
                ->latest()
                ->limit(100)
                ->get(),
            'users' => User::query()
                ->leftJoin('wallets', function ($join) {
                    $join->on('users.id', '=', 'wallets.user_id')
                        ->where('wallets.currency', '=', 'USD');
                })
                ->leftJoin('bets', 'users.id', '=', 'bets.user_id')
                ->leftJoin('transactions', 'users.id', '=', 'transactions.user_id')
                ->groupBy('users.id', 'users.name', 'users.email', 'users.status', 'wallets.balance')
                ->orderBy('users.id')
                ->get([
                    'users.id',
                    'users.name',
                    'users.email',
                    'users.status',
                    DB::raw('COALESCE(wallets.balance, 0) as wallet_balance'),
                    DB::raw('COUNT(DISTINCT bets.id) as bets_count'),
                    DB::raw('COUNT(DISTINCT transactions.id) as transactions_count'),
                ]),
        ]);
    }

    private function decimal(mixed $value): string
    {
        return number_format((float) ($value ?? 0), 8, '.', '');
    }
}
