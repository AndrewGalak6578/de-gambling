<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property int|null $wallet_id
 * @property string $type
 * @property string $status
 * @property string $amount
 * @property string $currency
 * @property string|null $tx_hash
 * @property string|null $reason
 * @property array<string, mixed>|null $meta
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 * @property-read Wallet|null $wallet
 * @property-read Collection<int, AdminAction> $adminActions
 */
#[Fillable(['user_id', 'wallet_id', 'type', 'status', 'amount', 'currency', 'tx_hash', 'reason', 'meta'])]
class Transaction extends Model
{
    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Wallet, $this>
     */
    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    /**
     * @return MorphMany<AdminAction, $this>
     */
    public function adminActions(): MorphMany
    {
        return $this->morphMany(AdminAction::class, 'target');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:8',
            'meta' => 'array',
        ];
    }
}
