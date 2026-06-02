<?php

namespace App\Services\Predictions;

use App\Models\Team;
use Illuminate\Support\Collection;

/**
 * Turns a {@see GroupStandings} into the ranked, frontend-ready row shape shared by the game
 * page (official + the viewer's predicted standings) and the prediction wizard, so the row
 * contract lives in one place across its several producers.
 */
class GroupStandingsPresenter
{
    /**
     * @param  Collection<int, Team>  $teamsById  the group's teams keyed by id
     * @return list<array{rank: int, team: ?array{id: int, name: string, code: ?string, is_placeholder: bool, flag_url: string}, played: int, won: int, drawn: int, lost: int, goals_for: int, goals_against: int, goal_difference: int, points: int, form: list<string>}>
     */
    public static function rows(GroupStandings $standings, Collection $teamsById): array
    {
        return collect($standings->ordered())
            ->values()
            ->map(fn (TeamStanding $standing, int $index): array => [
                'rank' => $index + 1,
                'team' => self::teamRef($teamsById->get($standing->teamId)),
                'played' => $standing->played(),
                'won' => $standing->won,
                'drawn' => $standing->drawn,
                'lost' => $standing->lost,
                'goals_for' => $standing->goalsFor,
                'goals_against' => $standing->goalsAgainst,
                'goal_difference' => $standing->goalDifference(),
                'points' => $standing->points(),
                'form' => $standing->results,
            ])
            ->all();
    }

    /**
     * @return array{id: int, name: string, code: ?string, is_placeholder: bool, flag_url: string}|null
     */
    private static function teamRef(?Team $team): ?array
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
