<?php

namespace App\Models;

use App\Enums\FixtureStatus;
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
     * The lifecycle status implied purely by the current fixture states: Upcoming until a fixture
     * kicks off, InProgress while matches are underway or partly played, Completed once every
     * fixture has finished. A tournament with no fixtures stays Upcoming.
     */
    public function deriveStatus(): TournamentStatus
    {
        if (! $this->fixtures()->exists()) {
            return TournamentStatus::Upcoming;
        }

        if (! $this->fixtures()->where('status', '!=', FixtureStatus::Finished)->exists()) {
            return TournamentStatus::Completed;
        }

        if ($this->fixtures()->whereIn('status', [FixtureStatus::Live, FixtureStatus::Finished])->exists()) {
            return TournamentStatus::InProgress;
        }

        return TournamentStatus::Upcoming;
    }

    /**
     * Recompute the lifecycle status from the current fixtures and persist it when it changes,
     * announcing the change for downstream listeners. Idempotent and bidirectional — rescheduling
     * the only live fixture back into the future reverts the tournament to Upcoming.
     */
    public function syncStatus(): void
    {
        $derived = $this->deriveStatus();

        if ($derived === $this->status) {
            return;
        }

        $from = $this->status;

        $this->update(['status' => $derived]);

        event(new TournamentStatusChanged($this, $from, $derived));
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
     * The playable pools over this competition. Each pool shares the tournament's structure and
     * official results but owns its own scoring strategy, entries and leaderboard.
     *
     * @return HasMany<Pool, $this>
     */
    public function pools(): HasMany
    {
        return $this->hasMany(Pool::class);
    }

    /**
     * @return HasMany<ScoreBatch, $this>
     */
    public function scoreBatches(): HasMany
    {
        return $this->hasMany(ScoreBatch::class);
    }

    /**
     * Admin orderings of ties in the official group results the ranking engine could not resolve.
     *
     * @return HasMany<TournamentGroupOrdering, $this>
     */
    public function groupOrderings(): HasMany
    {
        return $this->hasMany(TournamentGroupOrdering::class);
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
     * fixture has a kickoff yet. Stored UTC; the source of truth for a pool's derived group-stage
     * prediction lock {@see Pool::predictionsLockAt()}.
     */
    public function firstGroupKickoffAt(): ?CarbonInterface
    {
        $earliest = $this->groupFixtures()->whereNotNull('kicks_off_at')->min('kicks_off_at');

        return $earliest !== null ? CarbonImmutable::parse($earliest) : null;
    }

    /**
     * The distinct venues used by this tournament's fixtures, each mapped to its registered IANA
     * timezone. Venues are denormalised onto fixtures (there is no venues table), so this is the
     * authoritative list an admin may reschedule a fixture into — no free-text venues.
     *
     * @return array<string, string>
     */
    public function venueTimezones(): array
    {
        return $this->fixtures()
            ->whereNotNull('venue')
            ->distinct()
            ->orderBy('venue')
            ->pluck('venue_timezone', 'venue')
            ->all();
    }
}
