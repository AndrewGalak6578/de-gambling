<?php

namespace App\Modules\Finance\Enums;

enum TransactionStatus: string
{
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case Rejected = 'rejected';
    case Failed = 'failed';
}
