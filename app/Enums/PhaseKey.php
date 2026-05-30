<?php

namespace App\Enums;

enum PhaseKey: string
{
    case Group = 'group';
    case RoundOf32 = 'round_of_32';
    case RoundOf16 = 'round_of_16';
    case QuarterFinals = 'quarter_finals';
    case SemiFinals = 'semi_finals';
    case ThirdPlace = 'third_place';
    case Final = 'final';
}
