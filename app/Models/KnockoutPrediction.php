<?php

namespace App\Models;

use Database\Factories\KnockoutPredictionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'entry_id',
    'fixture_id',
    'predicted_home_team_id',
    'predicted_away_team_id',
    'home_goals',
    'away_goals',
    'advancing_team_id',
    'points_awarded',
])]
class KnockoutPrediction extends Model
{
    /** @use HasFactory<KnockoutPredictionFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'predicted_home_team_id' => 'integer',
            'predicted_away_team_id' => 'integer',
            'home_goals' => 'integer',
            'away_goals' => 'integer',
            'advancing_team_id' => 'integer',
            'points_awarded' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Entry, $this>
     */
    public function entry(): BelongsTo
    {
        return $this->belongsTo(Entry::class);
    }

    /**
     * @return BelongsTo<Fixture, $this>
     */
    public function fixture(): BelongsTo
    {
        return $this->belongsTo(Fixture::class);
    }

    /**
     * @return BelongsTo<Team, $this>
     */
    public function predictedHomeTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'predicted_home_team_id');
    }

    /**
     * @return BelongsTo<Team, $this>
     */
    public function predictedAwayTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'predicted_away_team_id');
    }

    /**
     * @return BelongsTo<Team, $this>
     */
    public function advancingTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'advancing_team_id');
    }
}
