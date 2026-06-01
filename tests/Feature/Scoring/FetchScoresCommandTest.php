<?php

namespace Tests\Feature\Scoring;

use App\Contracts\ScoreProvider;
use App\Enums\BatchStatus;
use App\Enums\ProposalStatus;
use App\Models\Fixture;
use App\Models\ScoreBatch;
use App\Models\ScoreProposal;
use App\Models\Tournament;
use App\Services\Scoring\ProposedScore;
use Database\Seeders\WorldCup2026Seeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithOfficialResults;
use Tests\TestCase;

class FetchScoresCommandTest extends TestCase
{
    use InteractsWithOfficialResults;
    use RefreshDatabase;

    private Tournament $tournament;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(WorldCup2026Seeder::class);
        $this->tournament = Tournament::firstOrFail();
    }

    public function test_the_manual_provider_creates_no_batch(): void
    {
        $this->artisan('scores:fetch', ['tournament' => $this->tournament->slug])
            ->assertSuccessful();

        $this->assertDatabaseCount('score_batches', 0);
        $this->assertDatabaseCount('score_proposals', 0);
    }

    public function test_it_proposes_fetched_scores_only_for_unfilled_ended_fixtures(): void
    {
        $this->markEnded($this->fixture(1));
        $this->markEnded($this->fixture(2));

        // Match 3 already has an official result, so a fetched score for it is ignored.
        $this->fixture(3)->update(['home_goals' => 1, 'away_goals' => 0]);

        $this->fakeProvider([
            new ProposedScore(matchNumber: 1, homeGoals: 2, awayGoals: 1),
            new ProposedScore(matchNumber: 2, homeGoals: 0, awayGoals: 0),
            new ProposedScore(matchNumber: 3, homeGoals: 4, awayGoals: 4),
        ]);

        $this->artisan('scores:fetch', ['tournament' => $this->tournament->slug])->assertSuccessful();

        $batch = ScoreBatch::firstOrFail();
        $this->assertSame(BatchStatus::Open, $batch->status);
        $this->assertSame(2, $batch->proposals()->count());

        $this->assertDatabaseHas('score_proposals', [
            'fixture_id' => $this->fixture(1)->id,
            'home_goals' => 2,
            'away_goals' => 1,
        ]);
    }

    public function test_it_does_not_propose_for_a_match_that_has_not_ended(): void
    {
        // Match 1 keeps its seeded (future) kickoff and scheduled status — it is not over.
        $this->fakeProvider([new ProposedScore(matchNumber: 1, homeGoals: 2, awayGoals: 1)]);

        $this->artisan('scores:fetch', ['tournament' => $this->tournament->slug])->assertSuccessful();

        $this->assertDatabaseCount('score_proposals', 0);
    }

    public function test_it_never_overwrites_an_existing_proposal(): void
    {
        $fixture = $this->markEnded($this->fixture(1));

        // An admin has already entered a proposal for this match.
        $batch = ScoreBatch::create([
            'tournament_id' => $this->tournament->id,
            'status' => BatchStatus::Open,
            'source' => 'manual',
        ]);
        ScoreProposal::create([
            'score_batch_id' => $batch->id,
            'fixture_id' => $fixture->id,
            'home_goals' => 3,
            'away_goals' => 2,
            'status' => ProposalStatus::Edited,
        ]);

        // The fetch offers a different score for the same match.
        $this->fakeProvider([new ProposedScore(matchNumber: 1, homeGoals: 0, awayGoals: 0)]);

        $this->artisan('scores:fetch', ['tournament' => $this->tournament->slug])->assertSuccessful();

        // First in wins: the admin's proposal is untouched.
        $this->assertSame(1, ScoreProposal::count());
        $this->assertDatabaseHas('score_proposals', [
            'fixture_id' => $fixture->id,
            'home_goals' => 3,
            'away_goals' => 2,
        ]);
    }

    public function test_re_running_does_not_duplicate_proposals(): void
    {
        $this->markEnded($this->fixture(1));

        $this->fakeProvider([new ProposedScore(matchNumber: 1, homeGoals: 2, awayGoals: 1)]);

        $this->artisan('scores:fetch', ['tournament' => $this->tournament->slug])->assertSuccessful();
        $this->artisan('scores:fetch', ['tournament' => $this->tournament->slug])->assertSuccessful();

        $this->assertSame(1, ScoreProposal::count());
    }

    private function fixture(int $matchNumber): Fixture
    {
        return $this->tournament->fixtures()->where('match_number', $matchNumber)->firstOrFail();
    }

    /**
     * @param  list<ProposedScore>  $scores
     */
    private function fakeProvider(array $scores): void
    {
        $this->app->bind(ScoreProvider::class, fn (): ScoreProvider => new class($scores) implements ScoreProvider
        {
            /** @param list<ProposedScore> $scores */
            public function __construct(private array $scores) {}

            public function fetch(Tournament $tournament): iterable
            {
                return $this->scores;
            }
        });
    }
}
