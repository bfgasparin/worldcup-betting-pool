<?php

namespace App\Enums;

use App\Models\ScoreBatch;

/**
 * The lifecycle of a {@see ScoreBatch} — a round of proposed official scores
 * awaiting admin review. An open batch collects proposals (from the fetch command or manual
 * entry); approving it writes the scores onto fixtures and runs scoring.
 */
enum BatchStatus: string
{
    case Open = 'open';
    case Approved = 'approved';
    case Discarded = 'discarded';
}
