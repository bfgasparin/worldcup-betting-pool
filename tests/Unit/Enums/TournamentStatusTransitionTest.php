<?php

namespace Tests\Unit\Enums;

use App\Enums\TournamentStatus;
use PHPUnit\Framework\TestCase;

class TournamentStatusTransitionTest extends TestCase
{
    public function test_upcoming_may_only_advance_to_in_progress(): void
    {
        $this->assertTrue(TournamentStatus::Upcoming->canTransitionTo(TournamentStatus::InProgress));
        $this->assertFalse(TournamentStatus::Upcoming->canTransitionTo(TournamentStatus::Completed));
        $this->assertFalse(TournamentStatus::Upcoming->canTransitionTo(TournamentStatus::Upcoming));
    }

    public function test_in_progress_may_complete_or_step_back_to_upcoming(): void
    {
        $this->assertTrue(TournamentStatus::InProgress->canTransitionTo(TournamentStatus::Completed));
        $this->assertTrue(TournamentStatus::InProgress->canTransitionTo(TournamentStatus::Upcoming));
        $this->assertFalse(TournamentStatus::InProgress->canTransitionTo(TournamentStatus::InProgress));
    }

    public function test_completed_may_only_step_back_to_in_progress(): void
    {
        $this->assertTrue(TournamentStatus::Completed->canTransitionTo(TournamentStatus::InProgress));
        $this->assertFalse(TournamentStatus::Completed->canTransitionTo(TournamentStatus::Upcoming));
        $this->assertFalse(TournamentStatus::Completed->canTransitionTo(TournamentStatus::Completed));
    }

    public function test_labels_are_human_readable(): void
    {
        $this->assertSame('Upcoming', TournamentStatus::Upcoming->label());
        $this->assertSame('In Progress', TournamentStatus::InProgress->label());
        $this->assertSame('Completed', TournamentStatus::Completed->label());
    }
}
