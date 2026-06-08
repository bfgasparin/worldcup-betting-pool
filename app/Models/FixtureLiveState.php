<?php

namespace App\Models;

use App\Enums\LiveStatus;
use Database\Factories\FixtureLiveStateFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The live scoreboard for a single {@see Fixture}, kept entirely separate from the official
 * result columns. An admin marks a fixture live and edits these goals during the match; the
 * official result is only ever written through the score-proposal/approval pipeline, so live
 * data never affects the official leaderboard until a batch is approved.
 */
#[Fillable([
    'fixture_id',
    'status',
    'home_goals',
    'away_goals',
    'started_at',
    'ended_at',
])]
class FixtureLiveState extends Model
{
    /** @use HasFactory<FixtureLiveStateFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => LiveStatus::class,
            'home_goals' => 'integer',
            'away_goals' => 'integer',
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Fixture, $this>
     */
    public function fixture(): BelongsTo
    {
        return $this->belongsTo(Fixture::class);
    }

    public function isLive(): bool
    {
        return $this->status === LiveStatus::Live;
    }

    public function isEnded(): bool
    {
        return $this->status === LiveStatus::Ended;
    }
}
