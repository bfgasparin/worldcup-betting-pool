<?php

namespace App\Enums;

enum TournamentStatus: string
{
    case Upcoming = 'upcoming';
    case InProgress = 'in_progress';
    case Completed = 'completed';

    /**
     * A human-readable label for the lifecycle status.
     */
    public function label(): string
    {
        return match ($this) {
            self::Upcoming => 'Upcoming',
            self::InProgress => 'In Progress',
            self::Completed => 'Completed',
        };
    }
}
