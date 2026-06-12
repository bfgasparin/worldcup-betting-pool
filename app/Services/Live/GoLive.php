<?php

namespace App\Services\Live;

use App\Enums\FixtureStatus;
use App\Enums\LiveStatus;
use App\Models\Fixture;
use App\Models\FixtureLiveState;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Marks a fixture live in the Live Center. This is the admin-driven replacement for the old
 * time-based auto-advance: it flips the fixture's official status to Live (matching the existing
 * lifecycle, so the review screen and end gate keep working) and opens an isolated live
 * scoreboard ({@see FixtureLiveState}). It never writes an official result.
 */
class GoLive
{
    /**
     * Mark a fixture live, enforcing the go-live eligibility gate.
     *
     * @throws HttpException 422 when the fixture cannot go live yet.
     */
    public function mark(Fixture $fixture): FixtureLiveState
    {
        abort_unless($fixture->canGoLive(), 422, 'This fixture cannot go live yet.');

        return $this->start($fixture);
    }

    /**
     * Mark a fixture live without the eligibility gate — for the simulator and other tooling that
     * deliberately drives the world forward.
     */
    public function force(Fixture $fixture): FixtureLiveState
    {
        return $this->start($fixture);
    }

    private function start(Fixture $fixture): FixtureLiveState
    {
        $state = DB::transaction(function () use ($fixture): FixtureLiveState {
            $fixture->update(['status' => FixtureStatus::Live]);

            // Seed the scoreboard at 0–0 so an untouched match still ends with a concrete score
            // (and the persisted state matches what the Live Center shows). Read the current value
            // first so re-going-live keeps an in-progress score rather than resetting it.
            $existing = $fixture->liveState;

            return $fixture->liveState()->updateOrCreate([], [
                'status' => LiveStatus::Live,
                'home_goals' => $existing?->home_goals ?? 0,
                'away_goals' => $existing?->away_goals ?? 0,
                'started_at' => now(),
                'ended_at' => null,
            ]);
        });

        // Bring the tournament lifecycle in line (Upcoming → InProgress). Kept outside the
        // transaction so the status-changed event can't fire and then roll back.
        $fixture->tournament->syncStatus();

        return $state;
    }
}
