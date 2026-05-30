<?php

namespace App\Models;

use App\Enums\PhaseType;
use App\Enums\ScoringStrategy;
use App\Enums\Sport;
use App\Enums\TournamentStatus;
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
    'scoring_strategy',
    'scoring_config',
    'predictions_lock_at',
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
            'scoring_strategy' => ScoringStrategy::class,
            'scoring_config' => 'array',
            'predictions_lock_at' => 'datetime',
            'starts_on' => 'date',
            'ends_on' => 'date',
        ];
    }

    /**
     * Whether the tournament still accepts prediction edits: it must be open (or a draft
     * not yet published) and the predictions lock time must be in the future.
     */
    public function acceptsPredictions(): bool
    {
        return in_array($this->status, [TournamentStatus::Open, TournamentStatus::Draft], true)
            && $this->predictions_lock_at !== null
            && now()->lessThan($this->predictions_lock_at);
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
     * @return HasMany<Entry, $this>
     */
    public function entries(): HasMany
    {
        return $this->hasMany(Entry::class);
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
}
