<?php

namespace App\Events;

use App\Enums\TournamentStatus;
use App\Models\Tournament;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TournamentStatusChanged
{
    use Dispatchable, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public Tournament $tournament,
        public TournamentStatus $from,
        public TournamentStatus $to,
    ) {}
}
