<?php

namespace App\Services\Live;

use App\Models\Fixture;
use App\Models\FixtureLiveState;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Updates the live scoreline an admin is keeping during a match. It only touches the isolated
 * {@see FixtureLiveState} — never the official fixture result — and bumps the row's updated_at,
 * which the projection cache uses as its version so all viewers refresh after each change.
 */
class UpdateLiveScore
{
    /**
     * @throws HttpException 422 when the fixture is not live.
     */
    public function update(Fixture $fixture, ?int $home, ?int $away): FixtureLiveState
    {
        $state = $fixture->liveState;

        abort_unless($state?->isLive() === true, 422, 'This fixture is not live.');

        $state->update([
            'home_goals' => $home,
            'away_goals' => $away,
        ]);

        return $state;
    }
}
