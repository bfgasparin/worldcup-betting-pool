<?php

namespace Tests\Unit\Enums;

use App\Enums\TournamentStatus;
use PHPUnit\Framework\TestCase;

class TournamentStatusTest extends TestCase
{
    public function test_labels_are_human_readable(): void
    {
        $this->assertSame('Upcoming', TournamentStatus::Upcoming->label());
        $this->assertSame('In Progress', TournamentStatus::InProgress->label());
        $this->assertSame('Completed', TournamentStatus::Completed->label());
    }
}
