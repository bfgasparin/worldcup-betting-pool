<?php

namespace App\Http\Controllers;

use App\Models\Fixture;
use App\Models\Group;
use App\Models\Team;
use App\Models\Tournament;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class GameController extends Controller
{
    /**
     * List the available games (tournaments).
     */
    public function index(): Response
    {
        $games = Tournament::query()
            ->withCount(['groups', 'fixtures'])
            ->orderByDesc('starts_on')
            ->get()
            ->map(fn (Tournament $tournament): array => [
                'slug' => $tournament->slug,
                'name' => $tournament->name,
                'sport' => $tournament->sport->value,
                'status' => $tournament->status->value,
                'starts_on' => $tournament->starts_on?->toDateString(),
                'ends_on' => $tournament->ends_on?->toDateString(),
                'groups_count' => $tournament->groups_count,
                'fixtures_count' => $tournament->fixtures_count,
            ]);

        return Inertia::render('games/index', ['games' => $games]);
    }

    /**
     * Show a single game's structure: groups, fixtures and the knockout bracket.
     */
    public function show(Tournament $tournament): Response
    {
        $tournament->load([
            'groups.teams',
            'groups.fixtures' => fn ($query) => $query->orderBy('match_number'),
            'groups.fixtures.homeTeam',
            'groups.fixtures.awayTeam',
            'knockoutFixtures' => fn ($query) => $query->orderBy('match_number'),
            'knockoutFixtures.phase',
            'knockoutFixtures.homeTeam',
            'knockoutFixtures.awayTeam',
        ]);

        return Inertia::render('games/show', [
            'game' => [
                'slug' => $tournament->slug,
                'name' => $tournament->name,
                'sport' => $tournament->sport->value,
                'status' => $tournament->status->value,
                'starts_on' => $tournament->starts_on?->toDateString(),
                'ends_on' => $tournament->ends_on?->toDateString(),
                'scoring_config' => $tournament->scoring_config,
            ],
            'groups' => $tournament->groups->map(fn (Group $group): array => $this->mapGroup($group)),
            'bracket' => $this->mapBracket($tournament->knockoutFixtures),
        ]);
    }

    /**
     * @return array{name: string, teams: list<array<string, mixed>>, fixtures: list<array<string, mixed>>}
     */
    private function mapGroup(Group $group): array
    {
        return [
            'name' => $group->name,
            'teams' => $group->teams
                ->map(fn (Team $team): array => [
                    ...$this->teamRef($team),
                    'position' => $team->pivot->position,
                ])
                ->all(),
            'fixtures' => $group->fixtures->map(fn (Fixture $fixture): array => [
                'match_number' => $fixture->match_number,
                'home' => $this->teamRef($fixture->homeTeam),
                'away' => $this->teamRef($fixture->awayTeam),
                'home_goals' => $fixture->home_goals,
                'away_goals' => $fixture->away_goals,
                'kicks_off_at' => $fixture->kicks_off_at?->toIso8601String(),
            ])->all(),
        ];
    }

    /**
     * Group the knockout fixtures into bracket columns, ordered by phase progression.
     *
     * @param  Collection<int, Fixture>  $fixtures
     * @return list<array<string, mixed>>
     */
    private function mapBracket($fixtures): array
    {
        return $fixtures
            ->groupBy(fn (Fixture $fixture): string => $fixture->phase->key->value)
            ->map(fn ($phaseFixtures): array => [
                'phase_key' => $phaseFixtures->first()->phase->key->value,
                'phase_name' => $phaseFixtures->first()->phase->name,
                'sort_order' => $phaseFixtures->first()->phase->sort_order,
                'fixtures' => $phaseFixtures->map(fn (Fixture $fixture): array => [
                    'match_number' => $fixture->match_number,
                    'bracket_slot' => $fixture->bracket_slot,
                    'home' => $this->teamRef($fixture->homeTeam),
                    'away' => $this->teamRef($fixture->awayTeam),
                    'home_label' => $fixture->homeTeam?->name ?? $fixture->home_placeholder_label,
                    'away_label' => $fixture->awayTeam?->name ?? $fixture->away_placeholder_label,
                    'home_goals' => $fixture->home_goals,
                    'away_goals' => $fixture->away_goals,
                ])->all(),
            ])
            ->sortBy('sort_order')
            ->values()
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
