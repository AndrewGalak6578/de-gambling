<?php

namespace App\Modules\Finance\Enums;

enum DepositInvoiceStatus: string
{
    case Pending = 'pending';
    case Fixated = 'fixated';
    case Paid = 'paid';
    case Forwarded = 'forwarded';
    case Expired = 'expired';
    case Failed = 'failed';
}
