<?php

namespace App\Models;

use App\Enums\ScoringStrategy;
use App\Enums\TournamentStatus;
use Database\Factories\GameFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'tournament_id',
    'slug',
    'name',
    'source',
    'scoring_strategy',
    'scoring_config',
    'predictions_lock_at',
])]
class Game extends Model
{
    /** @use HasFactory<GameFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'scoring_strategy' => ScoringStrategy::class,
            'scoring_config' => 'array',
            'predictions_lock_at' => 'datetime',
        ];
    }

    /**
     * Whether the game still accepts prediction edits. This is driven by the prediction
     * window alone and is intentionally independent of the underlying tournament's lifecycle
     * {@see TournamentStatus}, which describes where the competition is in its life.
     */
    public function acceptsPredictions(): bool
    {
        return $this->predictions_lock_at !== null && now()->lessThan($this->predictions_lock_at);
    }

    /**
     * Whether players predict the whole knockout bracket upfront (teams included), as opposed to
     * a future between-phases model where knockout teams are known before they are predicted.
     * When true, a player's predicted knockout teams are worth surfacing so the per-team
     * placement points (10 per team correctly placed in a match, +5 goal-count bonus) can be
     * audited; otherwise the predicted teams would just equal the official ones.
     */
    public function predictsKnockoutBracket(): bool
    {
        return $this->scoring_strategy === ScoringStrategy::UpfrontBracket;
    }

    /**
     * The competition this game is played over. The tournament owns the shared structure
     * (phases, groups, fixtures) and official results; the game owns the scoring and entries.
     *
     * @return BelongsTo<Tournament, $this>
     */
    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class);
    }

    /**
     * @return HasMany<Entry, $this>
     */
    public function entries(): HasMany
    {
        return $this->hasMany(Entry::class);
    }
}
