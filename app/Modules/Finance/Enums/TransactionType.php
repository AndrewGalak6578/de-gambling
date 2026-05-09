<?php

namespace App\Modules\Finance\Enums;

enum TransactionType: string
{
    case Deposit = 'deposit';
    case Withdrawal = 'withdrawal';
    case BetDebit = 'bet_debit';
    case BetPayout = 'bet_payout';
    case SettlementCorrection = 'settlement_correction';
}
