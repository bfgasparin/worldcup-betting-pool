<?php

namespace App\Models;

use App\Enums\OrderingScope;
use App\Services\Predictions\ManualTieOrdering;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An admin's manual ordering of one tie in the official group results that the ranking engine
 * could not separate — the tournament-wide mirror of {@see EntryGroupOrdering}. Consumed by
 * {@see ManualTieOrdering}.
 */
#[Fillable(['tournament_id', 'group_id', 'scope', 'tied_team_ids', 'ordered_team_ids'])]
class TournamentGroupOrdering extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'scope' => OrderingScope::class,
            'tied_team_ids' => 'array',
            'ordered_team_ids' => 'array',
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
     * @return BelongsTo<Group, $this>
     */
    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }
}
