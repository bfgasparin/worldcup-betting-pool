<?php

namespace App\Models;

use App\Enums\FeederOutcome;
use App\Enums\FixtureStatus;
use App\Enums\PhaseType;
use Database\Factories\FixtureFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'tournament_id',
    'phase_id',
    'group_id',
    'match_number',
    'bracket_slot',
    'home_team_id',
    'away_team_id',
    'home_placeholder_label',
    'away_placeholder_label',
    'home_feeder_fixture_id',
    'away_feeder_fixture_id',
    'home_feeder_outcome',
    'away_feeder_outcome',
    'home_goals',
    'away_goals',
    'winner_team_id',
    'kicks_off_at',
    'status',
])]
class Fixture extends Model
{
    /** @use HasFactory<FixtureFactory> */
    use HasFactory;

    protected $table = 'fixtures';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'home_feeder_outcome' => FeederOutcome::class,
            'away_feeder_outcome' => FeederOutcome::class,
            'home_goals' => 'integer',
            'away_goals' => 'integer',
            'kicks_off_at' => 'datetime',
            'status' => FixtureStatus::class,
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
     * @return BelongsTo<Phase, $this>
     */
    public function phase(): BelongsTo
    {
        return $this->belongsTo(Phase::class);
    }

    /**
     * @return BelongsTo<Group, $this>
     */
    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    /**
     * @return BelongsTo<Team, $this>
     */
    public function homeTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'home_team_id');
    }

    /**
     * @return BelongsTo<Team, $this>
     */
    public function awayTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'away_team_id');
    }

    /**
     * @return BelongsTo<Team, $this>
     */
    public function winner(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'winner_team_id');
    }

    /**
     * @return BelongsTo<Fixture, $this>
     */
    public function homeFeeder(): BelongsTo
    {
        return $this->belongsTo(Fixture::class, 'home_feeder_fixture_id');
    }

    /**
     * @return BelongsTo<Fixture, $this>
     */
    public function awayFeeder(): BelongsTo
    {
        return $this->belongsTo(Fixture::class, 'away_feeder_fixture_id');
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

    public function isGroup(): bool
    {
        return $this->phase?->type === PhaseType::Group;
    }

    public function isKnockout(): bool
    {
        return $this->phase?->type === PhaseType::Knockout;
    }
}
