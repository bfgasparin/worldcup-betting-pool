<?php

namespace Tests\Unit\Services\Scoring;

use App\Models\Entry;
use App\Models\Tournament;
use App\Services\Scoring\RankSnapshotter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RankSnapshotterTest extends TestCase
{
    use RefreshDatabase;

    private Tournament $tournament;

    private RankSnapshotter $snapshotter;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tournament = Tournament::factory()->create();
        $this->snapshotter = new RankSnapshotter;
    }

    public function test_it_ranks_by_points_with_unscored_entries_last(): void
    {
        $low = Entry::factory()->for($this->tournament)->create(['total_points' => 50]);
        $high = Entry::factory()->for($this->tournament)->create(['total_points' => 100]);
        $unscored = Entry::factory()->for($this->tournament)->create(['total_points' => null]);

        $this->snapshotter->snapshot($this->tournament);

        $this->assertSame(1, $high->fresh()->rank);
        $this->assertSame(2, $low->fresh()->rank);
        $this->assertSame(3, $unscored->fresh()->rank);
        // First snapshot ever: no prior rank to compare against.
        $this->assertNull($high->fresh()->previous_rank);
    }

    public function test_a_second_snapshot_captures_the_previous_rank_for_movement(): void
    {
        $a = Entry::factory()->for($this->tournament)->create(['total_points' => 50]);
        $b = Entry::factory()->for($this->tournament)->create(['total_points' => 100]);

        $this->snapshotter->snapshot($this->tournament); // b=1, a=2

        $a->update(['total_points' => 200]); // a overtakes b
        $this->snapshotter->snapshot($this->tournament);

        $this->assertSame(1, $a->fresh()->rank);
        $this->assertSame(2, $a->fresh()->previous_rank); // moved up
        $this->assertSame(2, $b->fresh()->rank);
        $this->assertSame(1, $b->fresh()->previous_rank); // moved down
    }

    public function test_a_brand_new_entry_has_no_previous_rank(): void
    {
        Entry::factory()->for($this->tournament)->create(['total_points' => 100]);
        $this->snapshotter->snapshot($this->tournament);

        $newcomer = Entry::factory()->for($this->tournament)->create(['total_points' => 75]);
        $this->snapshotter->snapshot($this->tournament);

        $this->assertSame(2, $newcomer->fresh()->rank);
        $this->assertNull($newcomer->fresh()->previous_rank);
    }

    public function test_ties_are_broken_by_id(): void
    {
        $first = Entry::factory()->for($this->tournament)->create(['total_points' => 80]);
        $second = Entry::factory()->for($this->tournament)->create(['total_points' => 80]);

        $this->snapshotter->snapshot($this->tournament);

        $this->assertSame(1, $first->fresh()->rank);
        $this->assertSame(2, $second->fresh()->rank);
    }
}
