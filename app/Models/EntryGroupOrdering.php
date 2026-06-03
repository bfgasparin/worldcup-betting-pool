<?php

namespace App\Models;

use App\Enums\OrderingScope;
use App\Services\Predictions\ManualTieOrdering;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A player's manual ordering of one tie the ranking engine could not separate (a within-group
 * cluster, or the cross-group thirds cut). Consumed by {@see ManualTieOrdering}.
 */
#[Fillable(['entry_id', 'group_id', 'scope', 'tied_team_ids', 'ordered_team_ids'])]
class EntryGroupOrdering extends Model
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
     * @return BelongsTo<Entry, $this>
     */
    public function entry(): BelongsTo
    {
        return $this->belongsTo(Entry::class);
    }

    /**
     * @return BelongsTo<Group, $this>
     */
    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }
}
