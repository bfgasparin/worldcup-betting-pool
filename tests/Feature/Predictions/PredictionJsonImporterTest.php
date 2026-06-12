<?php

namespace Tests\Feature\Predictions;

use App\Enums\LeaderboardCategory;
use App\Enums\PhaseKey;
use App\Models\Entry;
use App\Models\Pool;
use App\Models\Team;
use App\Models\Tournament;
use App\Models\User;
use App\Services\Predictions\BracketResolver;
use App\Services\Predictions\Import\CorrectedImport;
use App\Services\Predictions\Import\ParsedImport;
use App\Services\Predictions\Import\PredictionJsonImporter;
use App\Services\Predictions\OfficialBracketProjector;
use Database\Seeders\WorldCup2026Seeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithOfficialResults;
use Tests\Concerns\InteractsWithPredictions;
use Tests\TestCase;

class PredictionJsonImporterTest extends TestCase
{
    use InteractsWithOfficialResults;
    use InteractsWithPredictions;
    use RefreshDatabase;

    private Tournament $tournament;

    private Pool $pool;

    private PredictionJsonImporter $importer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(WorldCup2026Seeder::class);
        $this->tournament = Tournament::firstOrFail();
        $this->pool = $this->tournament->pools()->where('slug', 'world-cup-2026-ffa')->firstOrFail();
        $this->importer = new PredictionJsonImporter;
    }

    public function test_it_imports_a_full_bracket_from_json_matching_the_source_entry(): void
    {
        $reference = $this->buildReferenceEntry();
        $json = $this->jsonFromEntry($reference);

        $target = $this->entryFor(User::factory()->create());
        $parsed = $this->importer->parse($this->pool, $json);
        $preview = $this->importer->preview($target, $parsed);

        $this->assertFalse($preview['has_errors'], 'A self-consistent blob should raise no errors.');
        $this->assertSame([], $this->errorRows($preview));

        // Preview stores nothing.
        $this->assertSame(0, $target->groupPredictions()->count());
        $this->assertSame(0, $target->knockoutPredictions()->count());

        $this->importer->commit($target, $this->accept($parsed));

        // Group scores match the source exactly.
        foreach ($reference->groupPredictions()->get() as $source) {
            $this->assertDatabaseHas('group_predictions', [
                'entry_id' => $target->id,
                'fixture_id' => $source->fixture_id,
                'home_goals' => $source->home_goals,
                'away_goals' => $source->away_goals,
            ]);
        }

        // Every resolved knockout match-up + advancing team matches the source bracket.
        $targetKnockouts = $target->knockoutPredictions()->get()->keyBy('fixture_id');
        foreach ($reference->knockoutPredictions()->get() as $source) {
            if ($source->predicted_home_team_id === null) {
                continue;
            }

            $imported = $targetKnockouts->get($source->fixture_id);
            $this->assertNotNull($imported);
            $this->assertSame($source->predicted_home_team_id, $imported->predicted_home_team_id);
            $this->assertSame($source->predicted_away_team_id, $imported->predicted_away_team_id);
            $this->assertSame($source->advancing_team_id, $imported->advancing_team_id);
        }
    }

    public function test_commit_re_scores_the_pool_and_updates_the_board(): void
    {
        // Official group results in seed order: a seed-order prediction scores well.
        $this->recordOfficialGroupResults($this->tournament, $this->seedOrderScores());

        $reference = $this->buildReferenceEntry();
        $json = $this->jsonFromEntry($reference);

        $target = $this->entryFor(User::factory()->create());
        $this->importer->commit($target, $this->accept($this->importer->parse($this->pool, $json)));

        $target->refresh();
        $this->assertNotNull($target->total_points);
        $this->assertGreaterThan(0, $target->total_points);
        $this->assertGreaterThan(0, $this->standingFor($target, LeaderboardCategory::Overall)->value);
    }

    public function test_a_decisive_advances_pick_is_overridden_by_the_score(): void
    {
        $reference = $this->buildReferenceEntry();
        $json = $this->jsonFromEntry($reference);

        // Find a resolved knockout match (home won 1-0) and flip its "advances" to the loser while
        // keeping the decisive score — the importer must follow the score, not the contradictory pick.
        $index = $this->firstKnockoutMatchIndex($json);
        $json['matches'][$index]['advances'] = $json['matches'][$index]['away_team'];

        $target = $this->entryFor(User::factory()->create());
        $parsed = $this->importer->parse($this->pool, $json);
        $preview = $this->importer->preview($target, $parsed);

        $row = collect($preview['rows'])->firstWhere('match_number', $json['matches'][$index]['match_number']);
        $this->assertContains('advances_contradicts_score', $row['flags']);

        $this->importer->commit($target, $this->accept($parsed));

        $fixtureId = $row['fixture_id'];
        $imported = $target->knockoutPredictions()->where('fixture_id', $fixtureId)->firstOrFail();
        // Advancing follows the 1-0 score: the home (higher-scoring) team, not the JSON pick.
        $this->assertSame($imported->predicted_home_team_id, $imported->advancing_team_id);
    }

    public function test_unknown_match_numbers_and_team_codes_are_flagged_as_errors(): void
    {
        $json = [
            'matches' => [
                ['match_number' => 9999, 'home_team' => 'MEX', 'away_team' => 'CAN', 'home_goals' => 1, 'away_goals' => 0],
                ['match_number' => 1, 'home_team' => 'ZZZ', 'away_team' => 'XYZ', 'home_goals' => 2, 'away_goals' => 0],
            ],
        ];

        $parsed = $this->importer->parse($this->pool, $json);

        $this->assertSame([9999], $parsed->unknownMatchNumbers);
        $this->assertEqualsCanonicalizing(['ZZZ', 'XYZ'], $parsed->unknownTeamCodes);

        $preview = $this->importer->preview($this->entryFor(User::factory()->create()), $parsed);

        $this->assertTrue($preview['has_errors']);
        $this->assertSame([9999], $preview['banner']['unknown_match_numbers']);
        $this->assertEqualsCanonicalizing(['ZZZ', 'XYZ'], $preview['banner']['unknown_team_codes']);
    }

    public function test_has_existing_predictions_detects_a_real_pick(): void
    {
        $entry = $this->entryFor(User::factory()->create());
        $this->assertFalse($this->importer->hasExistingPredictions($entry));

        $this->predictGroup($entry, $this->tournament, 'A', $this->seedOrderScores());
        $this->assertTrue($this->importer->hasExistingPredictions($entry));
    }

    public function test_a_partial_blob_flags_missing_group_matches(): void
    {
        // Only the first group fixture, nothing else.
        $fixture = $this->tournament->groupFixtures()->with(['homeTeam', 'awayTeam'])->orderBy('match_number')->firstOrFail();
        $json = [
            'matches' => [[
                'match_number' => $fixture->match_number,
                'home_team' => $fixture->homeTeam->code,
                'away_team' => $fixture->awayTeam->code,
                'home_goals' => 1,
                'away_goals' => 0,
            ]],
        ];

        $preview = $this->importer->preview($this->entryFor(User::factory()->create()), $this->importer->parse($this->pool, $json));

        $this->assertNotContains($fixture->match_number, $preview['banner']['missing_match_numbers']);
        $this->assertCount(71, $preview['banner']['missing_match_numbers']);
    }

    public function test_it_imports_a_phased_pool_against_the_official_match_ups(): void
    {
        $phased = $this->tournament->pools()->where('slug', 'world-cup-2026-brothers')->firstOrFail();

        // Official group results + the projected bracket so the knockout fixtures carry real
        // participants — what a phased pool predicts against (no self-derived bracket).
        $this->recordOfficialGroupResults($this->tournament, $this->seedOrderScores());
        (new OfficialBracketProjector)->project($this->tournament);

        $target = $phased->entries()->create(['user_id' => User::factory()->create()->id]);
        $parsed = $this->importer->parse($phased, $this->phasedJson());
        $preview = $this->importer->preview($target, $parsed);

        $this->assertFalse($preview['has_errors']);
        $this->assertSame([], $this->errorRows($preview));
        // Preview persists nothing; no derived thirds for a phased pool.
        $this->assertSame(0, $target->knockoutPredictions()->count());
        $this->assertSame([], $preview['thirds']['derived']);

        $this->importer->commit($target, $this->accept($parsed));

        // Round-of-32 predictions are stamped against the OFFICIAL participants, advancing derived
        // from the 2-1 score (home), and no tie ordering is ever written for a phased pool.
        $r32 = $this->tournament->knockoutFixtures()
            ->whereRelation('phase', 'key', PhaseKey::RoundOf32->value)
            ->whereNotNull('home_team_id')
            ->get();
        $this->assertGreaterThan(0, $r32->count());
        foreach ($r32 as $fixture) {
            $this->assertDatabaseHas('knockout_predictions', [
                'entry_id' => $target->id,
                'fixture_id' => $fixture->id,
                'predicted_home_team_id' => $fixture->home_team_id,
                'predicted_away_team_id' => $fixture->away_team_id,
                'advancing_team_id' => $fixture->home_team_id,
                'home_goals' => 2,
                'away_goals' => 1,
            ]);
        }

        $this->assertSame(0, $target->groupOrderings()->count());
        $this->assertSame(72, $target->groupPredictions()->count());
        // Group predictions match the official scores, so re-scoring lights up the board.
        $this->assertGreaterThan(0, $target->refresh()->total_points);
    }

    /**
     * A phased-pool blob: group scores equal to the official results, plus a Round-of-32 prediction
     * for every fixture whose official participants are known (home wins 2-1).
     *
     * @return array<string, mixed>
     */
    private function phasedJson(): array
    {
        $matches = [];

        foreach ($this->tournament->groupFixtures()->with(['homeTeam', 'awayTeam'])->get() as $fixture) {
            $matches[] = [
                'match_number' => $fixture->match_number,
                'home_team' => $fixture->homeTeam->code,
                'away_team' => $fixture->awayTeam->code,
                'home_goals' => $fixture->home_goals,
                'away_goals' => $fixture->away_goals,
            ];
        }

        $r32 = $this->tournament->knockoutFixtures()
            ->whereRelation('phase', 'key', PhaseKey::RoundOf32->value)
            ->whereNotNull('home_team_id')
            ->with(['homeTeam', 'awayTeam'])
            ->get();

        foreach ($r32 as $fixture) {
            $matches[] = [
                'match_number' => $fixture->match_number,
                'home_team' => $fixture->homeTeam->code,
                'away_team' => $fixture->awayTeam->code,
                'home_goals' => 2,
                'away_goals' => 1,
                'advances' => $fixture->homeTeam->code,
            ];
        }

        return ['matches' => $matches];
    }

    private function buildReferenceEntry(): Entry
    {
        $entry = $this->entryFor(User::factory()->create());
        $this->predictAllGroups($entry, $this->tournament, $this->seedOrderScores());
        $this->advanceAllHome($entry, new BracketResolver);

        return $entry->refresh();
    }

    private function entryFor(User $user): Entry
    {
        return $this->pool->entries()->create(['user_id' => $user->id]);
    }

    /**
     * Serialise an entry's predictions into the backfill JSON shape.
     *
     * @return array<string, mixed>
     */
    private function jsonFromEntry(Entry $entry): array
    {
        $fixtures = $this->tournament->fixtures()->with(['homeTeam', 'awayTeam'])->get()->keyBy('id');
        $teams = Team::all()->keyBy('id');

        $matches = [];

        foreach ($entry->groupPredictions()->get() as $prediction) {
            $fixture = $fixtures->get($prediction->fixture_id);
            $matches[] = [
                'match_number' => $fixture->match_number,
                'home_team' => $fixture->homeTeam->code,
                'away_team' => $fixture->awayTeam->code,
                'home_goals' => $prediction->home_goals,
                'away_goals' => $prediction->away_goals,
            ];
        }

        foreach ($entry->knockoutPredictions()->get() as $prediction) {
            if ($prediction->predicted_home_team_id === null || $prediction->predicted_away_team_id === null) {
                continue;
            }

            $match = [
                'match_number' => $fixtures->get($prediction->fixture_id)->match_number,
                'home_team' => $teams->get($prediction->predicted_home_team_id)->code,
                'away_team' => $teams->get($prediction->predicted_away_team_id)->code,
                'home_goals' => $prediction->home_goals,
                'away_goals' => $prediction->away_goals,
            ];

            if ($prediction->advancing_team_id !== null) {
                $match['advances'] = $teams->get($prediction->advancing_team_id)->code;
            }

            $matches[] = $match;
        }

        $resolved = (new BracketResolver)->resolve($entry);
        $thirds = array_map(
            fn (int $id): array => ['team' => $teams->get($id)->code],
            $resolved->rankedThirds ?? [],
        );

        return ['matches' => $matches, 'third_places_classification' => $thirds];
    }

    private function accept(ParsedImport $parsed): CorrectedImport
    {
        return new CorrectedImport($parsed->groupRows(), $parsed->knockoutRows(), $parsed->thirdsTeamIds);
    }

    /**
     * @param  array<string, mixed>  $preview
     * @return list<int>
     */
    private function errorRows(array $preview): array
    {
        return collect($preview['rows'])
            ->where('severity', 'error')
            ->pluck('match_number')
            ->all();
    }

    /**
     * @param  array<string, mixed>  $json
     */
    private function firstKnockoutMatchIndex(array $json): int
    {
        $knockoutNumbers = $this->tournament->knockoutFixtures()->pluck('match_number')->all();

        foreach ($json['matches'] as $index => $match) {
            if (in_array($match['match_number'], $knockoutNumbers, true) && isset($match['advances'])) {
                return $index;
            }
        }

        $this->fail('No knockout match with an advancing team in the blob.');
    }
}
