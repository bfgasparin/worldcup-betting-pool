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

    /**
     * The statuses this status may transition into. Forward progression plus single-step
     * backward edges so an admin can correct a mistaken advance.
     *
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Upcoming => [self::InProgress],
            self::InProgress => [self::Completed, self::Upcoming],
            self::Completed => [self::InProgress],
        };
    }

    public function canTransitionTo(self $to): bool
    {
        return in_array($to, $this->allowedTransitions(), true);
    }
}
