<?php

namespace App\Models;

use App\Enums\LeaderboardCategory;
use App\Services\Scoring\RankSnapshotter;
use Database\Factories\LeaderboardStandingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One entry's standing on a single leaderboard ({@see LeaderboardCategory}). `value` is the board's
 * primary metric and `tiebreaker` its secondary metric; together with the entry id they give the
 * board its stable order. `rank`/`previous_rank` are written per board by
 * {@see RankSnapshotter} so the UI can show movement.
 */
#[Fillable(['entry_id', 'category', 'value', 'tiebreaker', 'rank', 'previous_rank'])]
class LeaderboardStanding extends Model
{
    /** @use HasFactory<LeaderboardStandingFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'category' => LeaderboardCategory::class,
            'value' => 'integer',
            'tiebreaker' => 'integer',
            'rank' => 'integer',
            'previous_rank' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Entry, $this>
     */
    public function entry(): BelongsTo
    {
        return $this->belongsTo(Entry::class);
    }
}
