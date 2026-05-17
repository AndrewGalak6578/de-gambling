<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property string $provider
 * @property string $provider_invoice_id
 * @property string $provider_public_id
 * @property string|null $external_id
 * @property string $status
 * @property string $asset_key
 * @property string $coin
 * @property string $network_key
 * @property string $pay_address
 * @property string $amount_coin
 * @property string $expected_usd
 * @property string $rate_usd
 * @property string|null $hosted_url
 * @property Carbon|null $expires_at
 * @property Carbon|null $credited_at
 * @property array<string, mixed>|null $payload
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 */
#[Fillable([
    'user_id',
    'provider',
    'provider_invoice_id',
    'provider_public_id',
    'external_id',
    'status',
    'asset_key',
    'coin',
    'network_key',
    'pay_address',
    'amount_coin',
    'expected_usd',
    'rate_usd',
    'hosted_url',
    'expires_at',
    'credited_at',
    'payload',
])]
#[Hidden(['payload'])]
class DepositInvoice extends Model
{
    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount_coin' => 'decimal:8',
            'expected_usd' => 'decimal:8',
            'rate_usd' => 'decimal:8',
            'expires_at' => 'datetime',
            'credited_at' => 'datetime',
            'payload' => 'array',
        ];
    }
}
