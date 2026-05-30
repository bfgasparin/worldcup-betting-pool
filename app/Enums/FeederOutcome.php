<?php

namespace App\Enums;

enum FeederOutcome: string
{
    case Winner = 'winner';
    case Loser = 'loser';
}
