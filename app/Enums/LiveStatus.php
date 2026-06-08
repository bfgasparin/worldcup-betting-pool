<?php

namespace App\Enums;

use App\Models\FixtureLiveState;

/**
 * The lifecycle of a {@see FixtureLiveState} — the live scoreboard an admin drives during a
 * match, kept entirely separate from the official fixture result. Scheduled before an admin
 * goes live, Live while the score is being updated, Ended once the admin hands the final score
 * off to the score-proposal pipeline for approval.
 */
enum LiveStatus: string
{
    case Scheduled = 'scheduled';
    case Live = 'live';
    case Ended = 'ended';
}
