<?php

namespace App\Models;

use App\Enums\FeederOutcome;
use App\Enums\FixtureStatus;
use App\Enums\PhaseType;
use Database\Factories\FixtureFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
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
    'home_penalties',
    'away_penalties',
    'winner_team_id',
    'kicks_off_at',
    'venue',
    'venue_timezone',
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
            'home_penalties' => 'integer',
            'away_penalties' => 'integer',
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

    /**
     * Whether the match has reached its scheduled kickoff.
     */
    public function hasKickedOff(): bool
    {
        return $this->kicks_off_at !== null && now()->gte($this->kicks_off_at);
    }

    /**
     * Whether the match is over and therefore eligible for an official score.
     *
     * A fixture has ended once it is live and enough time has passed since
     * kickoff to cover regulation, extra time and penalties (see
     * config('scoring.match_duration_minutes')). This is the single gate both
     * the admin review screen and the scheduled fetch honour — a score can
     * only ever be entered for a match that is truly finished.
     */
    public function hasEnded(): bool
    {
        return $this->status === FixtureStatus::Live
            && $this->kicks_off_at !== null
            && now()->gte($this->kicks_off_at->addMinutes($this->matchDurationMinutes()));
    }

    /**
     * Limit the query to fixtures that have ended (live and past full time).
     *
     * @param  Builder<Fixture>  $query
     */
    public function scopeEnded(Builder $query): void
    {
        $query->where('status', FixtureStatus::Live)
            ->whereNotNull('kicks_off_at')
            ->where('kicks_off_at', '<=', now()->subMinutes($this->matchDurationMinutes()));
    }

    private function matchDurationMinutes(): int
    {
        return (int) config('scoring.match_duration_minutes');
    }
}
