<?php

namespace App\Enums;

/**
 * Which kind of manual tie ordering a row resolves: the order of teams level within a single
 * group, or the order of the third-placed teams that straddle the cross-group qualifying cut.
 */
enum OrderingScope: string
{
    case WithinGroup = 'within-group';
    case Thirds = 'thirds';
}
