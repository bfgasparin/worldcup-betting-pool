<?php

namespace App\Enums;

enum TournamentStatus: string
{
    case Draft = 'draft';
    case Open = 'open';
    case Locked = 'locked';
    case InProgress = 'in_progress';
    case Completed = 'completed';
}
