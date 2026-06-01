<?php

namespace App\Models;

use App\Enums\ProposalStatus;
use Database\Factories\ScoreProposalFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A single proposed official result for one fixture inside a {@see ScoreBatch}. Admins may edit
 * the goals/winner/penalties before approving; on approval the values are copied onto the
 * fixture and the proposal is marked Applied.
 */
#[Fillable([
    'score_batch_id',
    'fixture_id',
    'home_goals',
    'away_goals',
    'winner_team_id',
    'home_penalties',
    'away_penalties',
    'status',
])]
class ScoreProposal extends Model
{
    /** @use HasFactory<ScoreProposalFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'home_goals' => 'integer',
            'away_goals' => 'integer',
            'winner_team_id' => 'integer',
            'home_penalties' => 'integer',
            'away_penalties' => 'integer',
            'status' => ProposalStatus::class,
        ];
    }

    /**
     * @return BelongsTo<ScoreBatch, $this>
     */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(ScoreBatch::class, 'score_batch_id');
    }

    /**
     * @return BelongsTo<Fixture, $this>
     */
    public function fixture(): BelongsTo
    {
        return $this->belongsTo(Fixture::class);
    }
}
