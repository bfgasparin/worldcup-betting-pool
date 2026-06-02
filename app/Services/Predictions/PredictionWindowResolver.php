<?php

namespace App\Services\Predictions;

use App\Enums\PhaseKey;
use App\Enums\PhaseType;
use App\Enums\PredictionWindowStatus;
use App\Models\Game;
use App\Models\Phase;
use Illuminate\Database\Eloquent\Collection;

/**
 * Decides, per phase, whether a game currently accepts predictions.
 *
 * Upfront-bracket games have one window: every phase shares the game's single
 * {@see Game::acceptsPredictions()} lock. Phased-bracket games open the group stage on that same
 * lock, then each knockout round on its own: a round is {@see PredictionWindowStatus::Pending}
 * until the official {@see OfficialBracketProjector} has filled in its real participants, then
 * {@see PredictionWindowStatus::Open} until its first kickoff, after which it is
 * {@see PredictionWindowStatus::Locked}.
 */
class PredictionWindowResolver
{
    /**
     * The window status of every phase, keyed by phase key value.
     *
     * @return array<string, PredictionWindowStatus>
     */
    public function windows(Game $game): array
    {
        $windows = [];

        foreach ($this->phases($game) as $phase) {
            $windows[$phase->key->value] = $this->statusFor($game, $phase);
        }

        return $windows;
    }

    public function isOpen(Game $game, PhaseKey $phase): bool
    {
        foreach ($this->phases($game) as $candidate) {
            if ($candidate->key === $phase) {
                return $this->statusFor($game, $candidate) === PredictionWindowStatus::Open;
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
    private function phases(Game $game): Collection
    {
        $tournament = $game->tournament;
        $tournament->load([
            'phases' => fn ($query) => $query->orderBy('sort_order'),
            'phases.fixtures',
        ]);

        return $tournament->phases;
    }

    private function statusFor(Game $game, Phase $phase): PredictionWindowStatus
    {
        // Upfront games, and the group stage of any game, ride the single game-level lock.
        if (! $game->usesPhasedPredictionWindows() || $phase->type === PhaseType::Group) {
            return $game->acceptsPredictions()
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

        $firstKickoff = $fixtures->whereNotNull('kicks_off_at')->min('kicks_off_at');

        if ($firstKickoff !== null && now()->gte($firstKickoff)) {
            return PredictionWindowStatus::Locked;
        }

        return PredictionWindowStatus::Open;
    }
}
