<?php

namespace App\Services\Predictions\Import;

/**
 * The admin-reviewed values posted back from the review screen, ready to commit. Same row shapes
 * the importer's write helpers consume — group scores, knockout score + advancing pick, and the
 * third-place ordering — after the admin verified them against what the user sent externally.
 */
final class CorrectedImport
{
    /**
     * @param  list<array{fixture_id: int, home_goals: int, away_goals: int}>  $groupRows
     * @param  list<array{fixture_id: int, home_goals: int|null, away_goals: int|null, advancing_pick: int|null}>  $knockoutRows
     * @param  list<int>  $thirdsTeamIds
     * @param  array<string, list<int>>  $groupStandings  uppercased group label => team ids in stated
     *                                                    finishing order, to break within-group ties
     */
    public function __construct(
        public readonly array $groupRows,
        public readonly array $knockoutRows,
        public readonly array $thirdsTeamIds,
        public readonly array $groupStandings = [],
    ) {}
}
