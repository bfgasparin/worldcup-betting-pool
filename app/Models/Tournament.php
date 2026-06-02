<?php

namespace App\Models;

use App\Enums\PhaseType;
use App\Enums\Sport;
use App\Enums\TournamentStatus;
use App\Events\TournamentStatusChanged;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Database\Factories\TournamentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'slug',
    'name',
    'sport',
    'status',
    'starts_on',
    'ends_on',
])]
class Tournament extends Model
{
    /** @use HasFactory<TournamentFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sport' => Sport::class,
            'status' => TournamentStatus::class,
            'starts_on' => 'date',
            'ends_on' => 'date',
        ];
    }

    /**
     * Move the tournament to a new lifecycle status, guarding against illegal
     * transitions and announcing the change for downstream listeners.
     *
     * @throws \InvalidArgumentException when the transition is not allowed
     */
    public function transitionTo(TournamentStatus $to): void
    {
        if (! $this->status->canTransitionTo($to)) {
            throw new \InvalidArgumentException(
                "Cannot transition tournament from [{$this->status->value}] to [{$to->value}].",
            );
        }

        $from = $this->status;

        $this->update(['status' => $to]);

        event(new TournamentStatusChanged($this, $from, $to));
    }

    /**
     * @return HasMany<Phase, $this>
     */
    public function phases(): HasMany
    {
        return $this->hasMany(Phase::class);
    }

    /**
     * @return HasMany<Group, $this>
     */
    public function groups(): HasMany
    {
        return $this->hasMany(Group::class);
    }

    /**
     * @return HasMany<Fixture, $this>
     */
    public function fixtures(): HasMany
    {
        return $this->hasMany(Fixture::class);
    }

    /**
     * The playable games (pools) over this competition. Each game shares the tournament's
     * structure and official results but owns its own scoring strategy, entries and leaderboard.
     *
     * @return HasMany<Game, $this>
     */
    public function games(): HasMany
    {
        return $this->hasMany(Game::class);
    }

    /**
     * @return HasMany<ScoreBatch, $this>
     */
    public function scoreBatches(): HasMany
    {
        return $this->hasMany(ScoreBatch::class);
    }

    /**
     * @return HasMany<Fixture, $this>
     */
    public function groupFixtures(): HasMany
    {
        return $this->fixtures()->whereRelation('phase', 'type', PhaseType::Group->value);
    }

    /**
     * @return HasMany<Fixture, $this>
     */
    public function knockoutFixtures(): HasMany
    {
        return $this->fixtures()->whereRelation('phase', 'type', PhaseType::Knockout->value);
    }

    /**
     * The earliest scheduled kickoff across all group-stage fixtures, or null when no group
     * fixture has a kickoff yet. Stored UTC; the source of truth for a game's derived group-stage
     * prediction lock {@see Game::predictionsLockAt()}.
     */
    public function firstGroupKickoffAt(): ?CarbonInterface
    {
        $earliest = $this->groupFixtures()->whereNotNull('kicks_off_at')->min('kicks_off_at');

        return $earliest !== null ? CarbonImmutable::parse($earliest) : null;
    }
}
