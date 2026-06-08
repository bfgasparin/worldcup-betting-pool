<?php

namespace App\Models;

use App\Enums\BatchStatus;
use Database\Factories\ScoreBatchFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A round of proposed official scores awaiting admin review. The fetch command (or manual
 * entry) fills an open batch with {@see ScoreProposal} rows; approving the batch writes the
 * scores onto fixtures, projects the official bracket and recomputes points.
 */
#[Fillable([
    'tournament_id',
    'status',
    'source',
    'fetched_at',
    'approved_at',
    'approved_by',
])]
class ScoreBatch extends Model
{
    /** @use HasFactory<ScoreBatchFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => BatchStatus::class,
            'fetched_at' => 'datetime',
            'approved_at' => 'datetime',
        ];
    }

    /**
     * The tournament's single open review batch, creating it with the given source when none
     * exists. There is at most one open batch per tournament; an existing open batch is reused
     * verbatim (its original source is kept), so any contributor — manual entry, the fetch
     * command, or an ended live match — fills the same batch the admin reviews.
     */
    public static function openFor(Tournament $tournament, string $source = 'manual'): self
    {
        return $tournament->scoreBatches()->firstOrCreate(
            ['status' => BatchStatus::Open],
            ['source' => $source, 'fetched_at' => now()],
        );
    }

    /**
     * @return BelongsTo<Tournament, $this>
     */
    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * @return HasMany<ScoreProposal, $this>
     */
    public function proposals(): HasMany
    {
        return $this->hasMany(ScoreProposal::class);
    }
}
