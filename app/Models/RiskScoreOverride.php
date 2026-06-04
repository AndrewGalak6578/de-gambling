<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'admin_user_id', 'score_adjustment', 'disabled', 'reason'])]
class RiskScoreOverride extends Model
{
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_user_id');
    }

    protected function casts(): array
    {
        return [
            'score_adjustment' => 'integer',
            'disabled' => 'boolean',
        ];
    }
}
