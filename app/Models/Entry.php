<?php

namespace App\Models;

use App\Enums\EntryStatus;
use Database\Factories\EntryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['tournament_id', 'user_id', 'status', 'submitted_at', 'total_points', 'rank', 'previous_rank'])]
class Entry extends Model
{
    /** @use HasFactory<EntryFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => EntryStatus::class,
            'submitted_at' => 'datetime',
            'total_points' => 'integer',
            'rank' => 'integer',
            'previous_rank' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Tournament, $this>
     */
    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<GroupPrediction, $this>
     */
    public function groupPredictions(): HasMany
    {
        return $this->hasMany(GroupPrediction::class);
    }

    /**
     * @return HasMany<KnockoutPrediction, $this>
     */
    public function knockoutPredictions(): HasMany
    {
        return $this->hasMany(KnockoutPrediction::class);
    }
}
