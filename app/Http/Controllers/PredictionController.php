<?php

namespace App\Http\Controllers;

use App\Http\Requests\Predictions\UpdateGroupPredictionsRequest;
use App\Http\Requests\Predictions\UpdateKnockoutPredictionsRequest;
use App\Models\Entry;
use App\Models\Fixture;
use App\Models\Game;
use App\Models\Group;
use App\Models\GroupPrediction;
use App\Models\KnockoutPrediction;
use App\Models\Team;
use App\Models\Tournament;
use App\Services\Predictions\BracketResolver;
use App\Services\Predictions\GroupStandings;
use App\Services\Predictions\GroupStandingsPresenter;
use App\Services\Predictions\ResolvedBracket;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class PredictionController extends Controller
{
    public function __construct(private readonly BracketResolver $resolver) {}

    /**
     * Show the prediction wizard for a game, prefilled with the user's saved picks
     * and the bracket teams resolved from their group-stage scores.
     */
    public function edit(Request $request, Game $game): Response
    {
        $canEdit = $game->acceptsPredictions();

        $entry = $game->entries()->where('user_id', $request->user()->id)->first();

        if ($entry === null && $canEdit) {
            $entry = $game->entries()->create([
                'user_id' => $request->user()->id,
            ]);
        }

        if ($entry !== null) {
            // Make sure every knockout fixture has a (re)resolved prediction row to bind to.
            $this->resolver->persist($entry);
        }

        $tournament = $game->tournament;
        $tournament->load([
            'groups.teams',
            'groups.fixtures' => fn ($query) => $query->orderBy('match_number'),
            'knockoutFixtures' => fn ($query) => $query->orderBy('match_number'),
            'knockoutFixtures.phase',
        ]);

        $bracket = $this->resolveBracket($tournament, $entry);
        $teamsById = $tournament->groups->flatMap->teams->keyBy('id');

        $groupPredictions = $entry?->groupPredictions->keyBy('fixture_id') ?? collect();
        $knockoutPredictions = $entry?->knockoutPredictions->keyBy('fixture_id') ?? collect();

        return Inertia::render('games/predict', [
            'game' => [
                'slug' => $game->slug,
                'name' => $game->name,
                'sport' => $tournament->sport->value,
                'status' => $tournament->status->value,
                'starts_on' => $tournament->starts_on?->toDateString(),
                'ends_on' => $tournament->ends_on?->toDateString(),
                'predictions_lock_at' => $game->predictions_lock_at?->toIso8601String(),
                'can_edit' => $canEdit,
                'scoring_config' => $game->scoring_config,
            ],
            'groups' => $tournament->groups->map(
                fn (Group $group): array => $this->mapGroup($group, $bracket, $groupPredictions, $teamsById),
            )->all(),
            'bracket' => $this->mapBracket($tournament->knockoutFixtures, $bracket, $knockoutPredictions, $teamsById),
            'thirds' => $this->mapThirds($bracket, $teamsById),
        ]);
    }

    /**
     * Save the user's group-stage scores, then recompute the resolved bracket.
     */
    public function updateGroupStage(UpdateGroupPredictionsRequest $request, Game $game): RedirectResponse
    {
        $entry = $request->entry();

        DB::transaction(function () use ($entry, $request): void {
            foreach ($request->validated('predictions') as $prediction) {
                GroupPrediction::updateOrCreate(
                    ['entry_id' => $entry->id, 'fixture_id' => $prediction['fixture_id']],
                    ['home_goals' => $prediction['home_goals'], 'away_goals' => $prediction['away_goals']],
                );
            }
        });

        $this->resolver->persist($entry);

        return to_route('games.predict.edit', $game);
    }

    /**
     * Save the user's knockout scores and advancing picks, then cascade the bracket.
     */
    public function updateKnockout(UpdateKnockoutPredictionsRequest $request, Game $game): RedirectResponse
    {
        $entry = $request->entry();

        DB::transaction(function () use ($entry, $request): void {
            foreach ($request->predictionsForPersistence() as $prediction) {
                KnockoutPrediction::updateOrCreate(
                    ['entry_id' => $entry->id, 'fixture_id' => $prediction['fixture_id']],
                    [
                        'home_goals' => $prediction['home_goals'],
                        'away_goals' => $prediction['away_goals'],
                        'advancing_team_id' => $prediction['advancing_team_id'],
                    ],
                );
            }
        });

        $this->resolver->persist($entry);

        return to_route('games.predict.edit', $game);
    }

    /**
     * Resolve the bracket for the entry, or an empty read-only bracket when the user has
     * no entry (e.g. a locked game they never entered).
     */
    private function resolveBracket(Tournament $tournament, ?Entry $entry): ResolvedBracket
    {
        if ($entry !== null) {
            return $this->resolver->resolve($entry);
        }

        $standings = [];
        foreach ($tournament->groups as $group) {
            $standings[$group->name] = new GroupStandings($group, []);
        }

        return new ResolvedBracket($standings, null, []);
    }

    /**
     * @param  Collection<int, GroupPrediction>  $predictions
     * @param  Collection<int, Team>  $teamsById
     * @return array<string, mixed>
     */
    private function mapGroup(Group $group, ResolvedBracket $bracket, Collection $predictions, Collection $teamsById): array
    {
        $standings = $bracket->standings[$group->name];

        return [
            'name' => $group->name,
            'teams' => $group->teams->map(fn (Team $team): array => [
                ...$this->teamRef($team),
                'position' => $team->pivot->position,
            ])->all(),
            'fixtures' => $group->fixtures->map(function (Fixture $fixture) use ($predictions, $teamsById): array {
                $prediction = $predictions->get($fixture->id);

                return [
                    'fixture_id' => $fixture->id,
                    'match_number' => $fixture->match_number,
                    'home' => $this->teamRef($teamsById->get($fixture->home_team_id)),
                    'away' => $this->teamRef($teamsById->get($fixture->away_team_id)),
                    'home_goals' => $prediction?->home_goals,
                    'away_goals' => $prediction?->away_goals,
                    'kicks_off_at' => $fixture->kicks_off_at?->toIso8601String(),
                    'venue' => $fixture->venue,
                    'venue_timezone' => $fixture->venue_timezone,
                ];
            })->all(),
            'standings' => GroupStandingsPresenter::rows($standings, $teamsById),
        ];
    }

    /**
     * Group knockout fixtures into bracket columns ordered by phase progression.
     *
     * @param  Collection<int, Fixture>  $fixtures
     * @param  Collection<int, KnockoutPrediction>  $predictions
     * @param  Collection<int, Team>  $teamsById
     * @return list<array<string, mixed>>
     */
    private function mapBracket(Collection $fixtures, ResolvedBracket $bracket, Collection $predictions, Collection $teamsById): array
    {
        return $fixtures
            ->groupBy(fn (Fixture $fixture): string => $fixture->phase->key->value)
            ->map(fn (Collection $phaseFixtures): array => [
                'phase_key' => $phaseFixtures->first()->phase->key->value,
                'phase_name' => $phaseFixtures->first()->phase->name,
                'sort_order' => $phaseFixtures->first()->phase->sort_order,
                'fixtures' => $phaseFixtures->map(function (Fixture $fixture) use ($bracket, $predictions, $teamsById): array {
                    $slot = $bracket->fixture($fixture->id);
                    $prediction = $predictions->get($fixture->id);

                    $home = $slot['home'] !== null ? $teamsById->get($slot['home']) : null;
                    $away = $slot['away'] !== null ? $teamsById->get($slot['away']) : null;

                    return [
                        'fixture_id' => $fixture->id,
                        'match_number' => $fixture->match_number,
                        'bracket_slot' => $fixture->bracket_slot,
                        'phase_key' => $fixture->phase->key->value,
                        'home' => $this->teamRef($home),
                        'away' => $this->teamRef($away),
                        'home_label' => $home?->name ?? $fixture->home_placeholder_label,
                        'away_label' => $away?->name ?? $fixture->away_placeholder_label,
                        'home_goals' => $prediction?->home_goals,
                        'away_goals' => $prediction?->away_goals,
                        'advancing_team_id' => $prediction?->advancing_team_id,
                    ];
                })->values()->all(),
            ])
            ->sortBy('sort_order')
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, Team>  $teamsById
     * @return list<array<string, mixed>>|null
     */
    private function mapThirds(ResolvedBracket $bracket, Collection $teamsById): ?array
    {
        if ($bracket->rankedThirds === null) {
            return null;
        }

        return collect($bracket->rankedThirds)
            ->map(fn (int $teamId, int $index): array => [
                'rank' => $index + 1,
                'team' => $this->teamRef($teamsById->get($teamId)),
            ])
            ->all();
    }

    /**
     * @return array{id: int, name: string, code: ?string, is_placeholder: bool, flag_url: string}|null
     */
    private function teamRef(?Team $team): ?array
    {
        if ($team === null) {
            return null;
        }

        return [
            'id' => $team->id,
            'name' => $team->name,
            'code' => $team->code,
            'is_placeholder' => $team->is_placeholder,
            'flag_url' => $team->flag_url,
        ];
    }
}
