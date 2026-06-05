<?php

namespace Tests\Feature\Predictions;

use App\Models\Entry;
use App\Models\Pool;
use App\Models\Tournament;
use App\Models\User;
use Database\Seeders\WorldCup2026Seeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\Concerns\InteractsWithPredictions;
use Tests\TestCase;

class PredictControllerImportPropsTest extends TestCase
{
    use InteractsWithPredictions;
    use RefreshDatabase;

    private Tournament $tournament;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(WorldCup2026Seeder::class);
        $this->tournament = Tournament::firstOrFail();
        $this->user = User::factory()->create();
    }

    public function test_edit_exposes_eligible_import_sources(): void
    {
        $destination = $this->upfrontPool(now()->addWeek());
        $source = $this->upfrontPool(now()->addWeek());
        $this->join($destination);
        $sourceEntry = $this->join($source);
        $this->predictGroup($sourceEntry, $this->tournament, 'A', $this->seedOrderScores());

        $groupPhaseName = $this->tournament->phases()->where('key', 'group')->value('name');

        $this->actingAs($this->user)
            ->get(route('pools.predict.edit', $destination->slug))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('pools/predict')
                ->has('import_sources', 1, fn (AssertableInertia $src) => $src
                    ->where('slug', $source->slug)
                    ->where('name', $source->name)
                    ->where('source', $source->source)
                    ->where('scoring_label', 'Upfront Bracket')
                    ->where('phase_labels', [$groupPhaseName])
                    ->where('predictions_count', 6)
                    ->etc()
                )
                ->where('should_suggest_import', true)
            );
    }

    public function test_edit_has_no_import_sources_without_a_predicted_sibling(): void
    {
        $destination = $this->upfrontPool(now()->addWeek());
        $this->join($destination);

        $this->actingAs($this->user)
            ->get(route('pools.predict.edit', $destination->slug))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('import_sources', [])
                ->where('should_suggest_import', false)
            );
    }

    public function test_should_suggest_import_turns_off_once_the_user_predicts(): void
    {
        $destination = $this->upfrontPool(now()->addWeek());
        $source = $this->upfrontPool(now()->addWeek());
        $destinationEntry = $this->join($destination);
        $sourceEntry = $this->join($source);
        $this->predictAllGroups($sourceEntry, $this->tournament, $this->seedOrderScores());

        $this->actingAs($this->user)
            ->get(route('pools.predict.edit', $destination->slug))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('should_suggest_import', true));

        $this->predictGroup($destinationEntry, $this->tournament, 'A', $this->seedOrderScores());

        $this->actingAs($this->user)
            ->get(route('pools.predict.edit', $destination->slug))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('should_suggest_import', false));
    }

    private function upfrontPool(\DateTimeInterface|string|null $lockAt): Pool
    {
        return Pool::factory()->create([
            'tournament_id' => $this->tournament->id,
            'predictions_lock_at' => $lockAt,
        ]);
    }

    private function join(Pool $pool): Entry
    {
        return Entry::factory()->for($pool)->for($this->user)->create();
    }
}
