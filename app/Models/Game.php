<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string $status
 * @property string $rtp_percentage
 * @property array<string, mixed>|null $config
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Bet> $bets
 * @property-read Collection<int, AdminAction> $adminActions
 */
#[Fillable(['name', 'slug', 'status', 'rtp_percentage', 'config'])]
class Game extends Model
{
    /**
     * @return HasMany<Bet, $this>
     */
    public function bets(): HasMany
    {
        return $this->hasMany(Bet::class);
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
            'rtp_percentage' => 'decimal:2',
            'config' => 'array',
        ];
    }
}
