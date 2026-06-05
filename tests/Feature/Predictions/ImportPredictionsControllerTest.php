<?php

namespace Tests\Feature\Predictions;

use App\Enums\PhaseKey;
use App\Models\Entry;
use App\Models\KnockoutPrediction;
use App\Models\Pool;
use App\Models\Tournament;
use App\Models\User;
use App\Services\Predictions\OfficialBracketProjector;
use Database\Seeders\WorldCup2026Seeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithOfficialResults;
use Tests\Concerns\InteractsWithPredictions;
use Tests\TestCase;

class ImportPredictionsControllerTest extends TestCase
{
    use InteractsWithOfficialResults;
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

    public function test_importing_from_an_eligible_source_overwrites_and_redirects(): void
    {
        $source = $this->upfrontPool(now()->addWeek());
        $destination = $this->upfrontPool(now()->addWeek());
        $sourceEntry = $this->join($source);
        $destinationEntry = $this->join($destination);
        $this->predictGroup($sourceEntry, $this->tournament, 'A', $this->seedOrderScores());

        $this->actingAs($this->user)
            ->post(route('pools.predict.import', $destination->slug), ['source_pool' => $source->slug])
            ->assertRedirect(route('pools.predict.edit', $destination->slug));

        $this->assertSame(6, $destinationEntry->groupPredictions()->count());
    }

    public function test_import_rejects_a_source_from_a_different_tournament(): void
    {
        $destination = $this->upfrontPool(now()->addWeek());
        $this->join($destination);

        // A pool over a different tournament is never a candidate, so it can't be a source.
        $foreign = Pool::factory()->create();
        Entry::factory()->for($foreign)->for($this->user)->create();

        $this->actingAs($this->user)
            ->post(route('pools.predict.import', $destination->slug), ['source_pool' => $foreign->slug])
            ->assertSessionHasErrors('source_pool');
    }

    public function test_import_rejects_a_sibling_with_no_importable_predictions(): void
    {
        $destination = $this->upfrontPool(now()->addWeek());
        $emptySibling = $this->upfrontPool(now()->addWeek());
        $this->join($destination);
        $this->join($emptySibling); // joined, but never predicted

        $this->actingAs($this->user)
            ->post(route('pools.predict.import', $destination->slug), ['source_pool' => $emptySibling->slug])
            ->assertSessionHasErrors('source_pool');
    }

    public function test_a_non_member_cannot_import(): void
    {
        $source = $this->upfrontPool(now()->addWeek());
        $destination = $this->upfrontPool(now()->addWeek());
        $sourceEntry = $this->join($source);
        $this->predictGroup($sourceEntry, $this->tournament, 'A', $this->seedOrderScores());
        // The acting user has NOT joined the destination.

        $this->actingAs($this->user)
            ->post(route('pools.predict.import', $destination->slug), ['source_pool' => $source->slug])
            ->assertForbidden();
    }

    public function test_a_guest_is_redirected_to_login(): void
    {
        $destination = $this->upfrontPool(now()->addWeek());

        $this->post(route('pools.predict.import', $destination->slug), ['source_pool' => 'whatever'])
            ->assertRedirect(route('login'));
    }

    public function test_importing_an_open_phased_knockout_round_is_allowed_though_the_group_is_locked(): void
    {
        $this->projectRoundOf32();
        $this->setPhaseKickoff(PhaseKey::RoundOf32, now()->addDay());

        $source = $this->phasedPool(now()->subDay());       // group locked, R32 open
        $destination = $this->phasedPool(now()->subDay());
        $sourceEntry = $this->join($source);
        $destinationEntry = $this->join($destination);
        $this->fillRoundOf32($sourceEntry);

        $this->actingAs($this->user)
            ->post(route('pools.predict.import', $destination->slug), ['source_pool' => $source->slug])
            ->assertRedirect(route('pools.predict.edit', $destination->slug));

        $this->assertSame(16, $destinationEntry->knockoutPredictions()->count());
    }

    private function upfrontPool(\DateTimeInterface|string|null $lockAt): Pool
    {
        return Pool::factory()->create([
            'tournament_id' => $this->tournament->id,
            'predictions_lock_at' => $lockAt,
        ]);
    }

    private function phasedPool(\DateTimeInterface|string|null $lockAt): Pool
    {
        return Pool::factory()->phasedBracket()->create([
            'tournament_id' => $this->tournament->id,
            'predictions_lock_at' => $lockAt,
        ]);
    }

    private function join(Pool $pool): Entry
    {
        return Entry::factory()->for($pool)->for($this->user)->create();
    }

    private function projectRoundOf32(): void
    {
        $this->recordOfficialGroupResults($this->tournament, $this->seedOrderScores());
        (new OfficialBracketProjector)->project($this->tournament);
    }

    private function setPhaseKickoff(PhaseKey $key, \DateTimeInterface $when): void
    {
        $this->tournament->phases()->where('key', $key->value)->firstOrFail()
            ->fixtures()->update(['kicks_off_at' => $when]);
    }

    private function fillRoundOf32(Entry $entry): void
    {
        $fixtures = $this->tournament->knockoutFixtures()
            ->whereRelation('phase', 'key', PhaseKey::RoundOf32->value)->get();

        foreach ($fixtures as $fixture) {
            KnockoutPrediction::create([
                'entry_id' => $entry->id,
                'fixture_id' => $fixture->id,
                'home_goals' => 2,
                'away_goals' => 1,
                'advancing_team_id' => $fixture->home_team_id,
                'predicted_home_team_id' => $fixture->home_team_id,
                'predicted_away_team_id' => $fixture->away_team_id,
            ]);
        }
    }
}
