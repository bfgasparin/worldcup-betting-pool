<?php

namespace App\Services\Live;

use App\Console\Commands\FetchScores;
use App\Enums\LiveStatus;
use App\Models\Fixture;
use App\Models\FixtureLiveState;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Closes a live scoreboard at full time WITHOUT proposing a result. It only flips the isolated
 * {@see FixtureLiveState} to Ended (so the player Live Center shows "full time"); the complete
 * final — including a knockout's winner and penalties — is proposed separately by the score feed
 * ({@see FetchScores}). That separation is deliberate: deriving the winner
 * from the live goals (as {@see EndLiveMatch} does for the manual flow) would leave a penalty-draw
 * knockout with no winner and stall the bracket, so the automated feed never proposes here.
 */
class CloseLiveScoreboard
{
    /**
     * @throws HttpException 422 when the fixture is not live.
     */
    public function close(Fixture $fixture): FixtureLiveState
    {
        $state = $fixture->liveState;

        abort_unless($state?->isLive() === true, 422, 'This fixture is not live.');

        $state->update([
            'status' => LiveStatus::Ended,
            'ended_at' => now(),
        ]);

        return $state;
    }
}
