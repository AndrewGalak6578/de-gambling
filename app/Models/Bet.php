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
 * @property int $game_id
 * @property string $bet_amount
 * @property string $payout_amount
 * @property string $currency
 * @property string $status
 * @property string|null $server_seed_hash
 * @property string|null $client_seed
 * @property array<string, mixed>|null $result
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 * @property-read Game $game
 * @property-read Collection<int, AdminAction> $adminActions
 */
#[Fillable(['user_id', 'game_id', 'bet_amount', 'payout_amount', 'currency', 'status', 'server_seed_hash', 'client_seed', 'result'])]
class Bet extends Model
{
    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Game, $this>
     */
    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
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
            'bet_amount' => 'decimal:8',
            'payout_amount' => 'decimal:8',
            'result' => 'array',
        ];
    }
}
