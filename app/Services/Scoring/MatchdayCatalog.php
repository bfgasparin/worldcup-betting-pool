<?php

namespace App\Services\Scoring;

use App\Enums\PhaseKey;
use App\Enums\PhaseType;
use App\Models\Phase;
use App\Models\Tournament;

/**
 * Derives a tournament's ordered list of {@see Matchday}s — the timeline a player travels along on
 * the leaderboard. There is no stored "matchday" column: the group stage is split into three rounds
 * (each group plays exactly two matches per round, sometimes on different days, so the chronological
 * fixtures are chunked into consecutive pairs), and each knockout phase is one matchday.
 */
class MatchdayCatalog
{
    /**
     * Memoised {@see forTournament} results, keyed by tournament id, so a single request that needs
     * the matchdays more than once (e.g. both {@see fixtureIndex} and {@see descriptors}) runs the
     * underlying queries only once.
     *
     * @var array<int, list<Matchday>>
     */
    private array $cache = [];

    /**
     * @return list<Matchday>
     */
    public function forTournament(Tournament $tournament): array
    {
        return $this->cache[$tournament->id] ??= [
            ...$this->groupMatchdays($tournament),
            ...$this->knockoutMatchdays($tournament),
        ];
    }

    /**
     * Maps every fixture id to the descriptor of the matchday that owns it, so a page can stamp each
     * fixture with its matchday without re-deriving the chunking.
     *
     * @return array<int, array{key: string, label: string, short_label: string, kind: string}>
     */
    public function fixtureIndex(Tournament $tournament): array
    {
        $index = [];

        foreach ($this->forTournament($tournament) as $matchday) {
            foreach ($matchday->fixtureIds as $fixtureId) {
                $index[$fixtureId] = [
                    'key' => $matchday->key,
                    'label' => $matchday->label,
                    'short_label' => $matchday->shortLabel,
                    'kind' => $matchday->kind,
                ];
            }
        }

        return $index;
    }

    /**
     * The ordered matchday timeline as lightweight display descriptors (no fixture ids, no leaderboard
     * status) — enough to label and order the pool page's matchday sections and view switcher.
     *
     * @return list<array{key: string, label: string, short_label: string, kind: string}>
     */
    public function descriptors(Tournament $tournament): array
    {
        return array_map(fn (Matchday $matchday): array => [
            'key' => $matchday->key,
            'label' => $matchday->label,
            'short_label' => $matchday->shortLabel,
            'kind' => $matchday->kind,
        ], $this->forTournament($tournament));
    }

    /**
     * The group stage as three matchdays. Within each group the six fixtures are ordered by kickoff
     * and chunked into pairs (round 1 = the earliest pair, etc.); the same-indexed pairs across all
     * groups form one matchday.
     *
     * @return list<Matchday>
     */
    private function groupMatchdays(Tournament $tournament): array
    {
        /** @var array<int, list<int>> $rounds */
        $rounds = [];

        $groups = $tournament->groups()
            ->with(['fixtures' => fn ($query) => $query->orderBy('kicks_off_at')->orderBy('match_number')])
            ->orderBy('sort_order')
            ->get();

        foreach ($groups as $group) {
            foreach ($group->fixtures->chunk(2)->values() as $roundIndex => $pair) {
                foreach ($pair as $fixture) {
                    $rounds[$roundIndex][] = $fixture->id;
                }
            }
        }

        ksort($rounds);

        $matchdays = [];
        foreach ($rounds as $roundIndex => $fixtureIds) {
            $number = $roundIndex + 1;
            $matchdays[] = new Matchday(
                key: "group-{$number}",
                label: "Matchday {$number}",
                shortLabel: "MD{$number}",
                kind: 'group',
                fixtureIds: $fixtureIds,
            );
        }

        return $matchdays;
    }

    /**
     * Each knockout phase, in progression order, as one matchday.
     *
     * @return list<Matchday>
     */
    private function knockoutMatchdays(Tournament $tournament): array
    {
        $phases = $tournament->phases()
            ->where('type', PhaseType::Knockout)
            ->with(['fixtures' => fn ($query) => $query->orderBy('match_number')])
            ->orderBy('sort_order')
            ->get();

        $matchdays = [];
        foreach ($phases as $phase) {
            $fixtureIds = $phase->fixtures->pluck('id')->all();

            if ($fixtureIds === []) {
                continue;
            }

            $matchdays[] = new Matchday(
                key: $phase->key->value,
                label: $phase->name,
                shortLabel: $this->knockoutShortLabel($phase),
                kind: 'knockout',
                fixtureIds: $fixtureIds,
            );
        }

        return $matchdays;
    }

    private function knockoutShortLabel(Phase $phase): string
    {
        return match ($phase->key) {
            PhaseKey::RoundOf32 => 'R32',
            PhaseKey::RoundOf16 => 'R16',
            PhaseKey::QuarterFinals => 'QF',
            PhaseKey::SemiFinals => 'SF',
            PhaseKey::ThirdPlace => '3rd',
            PhaseKey::Final => 'Final',
            default => $phase->name,
        };
    }
}
