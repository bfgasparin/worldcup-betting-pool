<?php

namespace App\Services\Predictions;

use App\Enums\PhaseKey;
use App\Enums\PhaseType;
use App\Enums\PredictionWindowStatus;
use App\Models\Phase;
use App\Models\Pool;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;

/**
 * Decides, per phase, whether a pool currently accepts predictions.
 *
 * Upfront-bracket pools have one window: every phase shares the pool's single
 * {@see Pool::acceptsPredictions()} lock. Phased-bracket pools open the group stage on that same
 * lock, then each knockout round on its own: a round is {@see PredictionWindowStatus::Pending}
 * until the official {@see OfficialBracketProjector} has filled in its real participants, then
 * {@see PredictionWindowStatus::Open} until the configured buffer
 * (scoring.prediction_lock_buffer_minutes) before its first kickoff, after which it is
 * {@see PredictionWindowStatus::Locked}.
 */
class PredictionWindowResolver
{
    /**
     * The window status of every phase, keyed by phase key value.
     *
     * @return array<string, PredictionWindowStatus>
     */
    public function windows(Pool $pool): array
    {
        $windows = [];

        foreach ($this->phases($pool) as $phase) {
            $windows[$phase->key->value] = $this->statusFor($pool, $phase);
        }

        return $windows;
    }

    public function isOpen(Pool $pool, PhaseKey $phase): bool
    {
        foreach ($this->phases($pool) as $candidate) {
            if ($candidate->key === $phase) {
                return $this->statusFor($pool, $candidate) === PredictionWindowStatus::Open;
            }
        }

        return false;
    }

    /**
     * The tournament's phases (in progression order) with their fixtures. Reloaded fresh because
     * fixture participants and results mutate as result batches are approved.
     *
     * @return Collection<int, Phase>
     */
    private function phases(Pool $pool): Collection
    {
        $tournament = $pool->tournament;
        $tournament->load([
            'phases' => fn ($query) => $query->orderBy('sort_order'),
            'phases.fixtures',
        ]);

        return $tournament->phases;
    }

    /**
     * The instant a phase's prediction window closes — the deadline shown in the pool reminder and
     * the "window opened" email. The pool-level lock for the group stage and every upfront phase;
     * the configured buffer before a phased knockout round's first kickoff otherwise (null when that
     * round has no scheduled kickoff yet).
     */
    public function lockAtFor(Pool $pool, Phase $phase): ?CarbonInterface
    {
        if (! $pool->usesPhasedPredictionWindows() || $phase->type === PhaseType::Group) {
            return $pool->predictionsLockAt();
        }

        $firstKickoff = $phase->fixtures->whereNotNull('kicks_off_at')->min('kicks_off_at');

        return $firstKickoff?->copy()->subMinutes((int) config('scoring.prediction_lock_buffer_minutes'));
    }

    private function statusFor(Pool $pool, Phase $phase): PredictionWindowStatus
    {
        // Upfront pools, and the group stage of any pool, ride the single pool-level lock.
        if (! $pool->usesPhasedPredictionWindows() || $phase->type === PhaseType::Group) {
            return $pool->acceptsPredictions()
                ? PredictionWindowStatus::Open
                : PredictionWindowStatus::Locked;
        }

        // A phased knockout round stays pending until every one of its fixtures has both real
        // participants resolved (the round is fully set).
        $fixtures = $phase->fixtures;

        $allKnown = $fixtures->isNotEmpty() && $fixtures->every(
            fn ($fixture): bool => $fixture->home_team_id !== null && $fixture->away_team_id !== null,
        );

        if (! $allKnown) {
            return PredictionWindowStatus::Pending;
        }

        $lock = $this->lockAtFor($pool, $phase);

        if ($lock !== null && now()->gte($lock)) {
            return PredictionWindowStatus::Locked;
        }

        return PredictionWindowStatus::Open;
    }
}
