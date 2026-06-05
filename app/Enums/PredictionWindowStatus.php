<?php

namespace App\Enums;

/**
 * The state of a single phase's prediction window for a pool. `Open` accepts edits now; `Locked`
 * was open but has closed (the phase has kicked off, or the pool's single lock has passed);
 * `Pending` is a phased-bracket knockout round whose real participants are not yet known, so it
 * has not opened for prediction yet.
 */
enum PredictionWindowStatus: string
{
    case Open = 'open';
    case Locked = 'locked';
    case Pending = 'pending';
}
