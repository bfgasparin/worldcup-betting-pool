<?php

namespace Tests\Unit\Services\Scoring;

use App\Enums\LeaderboardCategory;
use App\Models\Entry;
use App\Models\LeaderboardStanding;
use App\Models\Pool;
use App\Services\Scoring\RankSnapshotter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RankSnapshotterTest extends TestCase
{
    use RefreshDatabase;

    private Pool $pool;

    private RankSnapshotter $snapshotter;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pool = Pool::factory()->create();
        $this->snapshotter = new RankSnapshotter;
    }

    public function test_it_ranks_by_points_with_unscored_entries_last(): void
    {
        $low = Entry::factory()->for($this->pool)->create(['total_points' => 50]);
        $high = Entry::factory()->for($this->pool)->create(['total_points' => 100]);
        $unscored = Entry::factory()->for($this->pool)->create(['total_points' => null]);

        $this->snapshotter->snapshot($this->pool);

        $this->assertSame(1, $high->fresh()->rank);
        $this->assertSame(2, $low->fresh()->rank);
        $this->assertSame(3, $unscored->fresh()->rank);
        // First snapshot ever: no prior rank to compare against.
        $this->assertNull($high->fresh()->previous_rank);
    }

    public function test_a_second_snapshot_captures_the_previous_rank_for_movement(): void
    {
        $a = Entry::factory()->for($this->pool)->create(['total_points' => 50]);
        $b = Entry::factory()->for($this->pool)->create(['total_points' => 100]);

        $this->snapshotter->snapshot($this->pool); // b=1, a=2

        $a->update(['total_points' => 200]); // a overtakes b
        $this->snapshotter->snapshot($this->pool);

        $this->assertSame(1, $a->fresh()->rank);
        $this->assertSame(2, $a->fresh()->previous_rank); // moved up
        $this->assertSame(2, $b->fresh()->rank);
        $this->assertSame(1, $b->fresh()->previous_rank); // moved down
    }

    public function test_a_brand_new_entry_has_no_previous_rank(): void
    {
        Entry::factory()->for($this->pool)->create(['total_points' => 100]);
        $this->snapshotter->snapshot($this->pool);

        $newcomer = Entry::factory()->for($this->pool)->create(['total_points' => 75]);
        $this->snapshotter->snapshot($this->pool);

        $this->assertSame(2, $newcomer->fresh()->rank);
        $this->assertNull($newcomer->fresh()->previous_rank);
    }

    public function test_ties_are_broken_by_id(): void
    {
        $first = Entry::factory()->for($this->pool)->create(['total_points' => 80]);
        $second = Entry::factory()->for($this->pool)->create(['total_points' => 80]);

        $this->snapshotter->snapshot($this->pool);

        $this->assertSame(1, $first->fresh()->rank);
        $this->assertSame(2, $second->fresh()->rank);
    }

    public function test_each_board_is_ranked_independently(): void
    {
        $a = Entry::factory()->for($this->pool)->create(['total_points' => 100]);
        $b = Entry::factory()->for($this->pool)->create(['total_points' => 50]);

        // On Overall, A leads; on Goal Sniper, B leads.
        $this->standing($a, LeaderboardCategory::Overall, 100);
        $this->standing($b, LeaderboardCategory::Overall, 50);
        $this->standing($a, LeaderboardCategory::GoalSniper, 2);
        $this->standing($b, LeaderboardCategory::GoalSniper, 9);

        $this->snapshotter->snapshot($this->pool);

        $this->assertSame(1, $this->rankOf($a, LeaderboardCategory::Overall));
        $this->assertSame(2, $this->rankOf($b, LeaderboardCategory::Overall));
        $this->assertSame(2, $this->rankOf($a, LeaderboardCategory::GoalSniper));
        $this->assertSame(1, $this->rankOf($b, LeaderboardCategory::GoalSniper));

        // The Overall board mirrors onto the entry itself.
        $this->assertSame(1, $a->fresh()->rank);
        $this->assertSame(2, $b->fresh()->rank);
    }

    public function test_a_board_breaks_ties_by_tiebreaker_then_id(): void
    {
        $a = Entry::factory()->for($this->pool)->create();
        $b = Entry::factory()->for($this->pool)->create();

        // Equal value; B has the higher tie-break, so B leads despite the later id.
        $this->standing($a, LeaderboardCategory::GoalSniper, 5, 3);
        $this->standing($b, LeaderboardCategory::GoalSniper, 5, 10);

        $this->snapshotter->snapshot($this->pool);

        $this->assertSame(1, $this->rankOf($b, LeaderboardCategory::GoalSniper));
        $this->assertSame(2, $this->rankOf($a, LeaderboardCategory::GoalSniper));
    }

    public function test_a_second_snapshot_captures_the_previous_rank_per_board(): void
    {
        $a = Entry::factory()->for($this->pool)->create();
        $b = Entry::factory()->for($this->pool)->create();

        $this->standing($a, LeaderboardCategory::GoalSniper, 5);
        $this->standing($b, LeaderboardCategory::GoalSniper, 9); // b=1, a=2
        $this->snapshotter->snapshot($this->pool);

        // a overtakes b on Goal Sniper.
        $this->standingFor($a)->update(['value' => 12]);
        $this->snapshotter->snapshot($this->pool);

        $this->assertSame(1, $this->rankOf($a, LeaderboardCategory::GoalSniper));
        $this->assertSame(2, $this->standingFor($a)->previous_rank);
        $this->assertSame(2, $this->rankOf($b, LeaderboardCategory::GoalSniper));
        $this->assertSame(1, $this->standingFor($b)->previous_rank);
    }

    private function standing(Entry $entry, LeaderboardCategory $category, int $value, int $tiebreaker = 0): LeaderboardStanding
    {
        return LeaderboardStanding::factory()->for($entry)->create([
            'category' => $category,
            'value' => $value,
            'tiebreaker' => $tiebreaker,
        ]);
    }

    private function rankOf(Entry $entry, LeaderboardCategory $category): ?int
    {
        return LeaderboardStanding::query()
            ->where('entry_id', $entry->id)
            ->where('category', $category)
            ->firstOrFail()
            ->rank;
    }

    private function standingFor(Entry $entry): LeaderboardStanding
    {
        return LeaderboardStanding::query()
            ->where('entry_id', $entry->id)
            ->where('category', LeaderboardCategory::GoalSniper)
            ->firstOrFail();
    }
}
