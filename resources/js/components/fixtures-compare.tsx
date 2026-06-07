import { ChevronDown, Lock } from 'lucide-react';
import type { ReactNode } from 'react';
import { useState } from 'react';
import {
    AdvanceChip,
    formatMatchDate,
    formatMatchTime,
    GroupBadge,
    KnockoutPickMatchup,
    TeamChip,
    TeamMatchupName,
} from '@/components/fixtures';
import { Flag } from '@/components/flag';
import { StandingsTable } from '@/components/standings-table';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { useDisplayTimeZone } from '@/hooks/use-timezone';
import { isRevealed, lane, laneLabel } from '@/lib/compare';
import { cn } from '@/lib/utils';
import type {
    BracketFixture,
    ComparePlayer,
    GroupView,
    PredictionWindowStatus,
    StandingRow,
    TeamRef,
} from '@/types/pools';

/* ------------------------------------------------------------------ atoms */

function slotLabel(team: TeamRef | null, label: string | null): string {
    return team ? (team.name ?? team.code ?? 'TBD') : (label ?? 'TBD');
}

function venueLabel(venue: string): string {
    return venue.replace(/\s+Stadium$/, '');
}

/**
 * The tiny points figure shown after a revealed pick: green/gold for a haul, coral/red for a scored
 * zero. The dark tone reads on the ink final card, the light tone on the elevated group/knockout
 * cards.
 */
function PointsTick({
    points,
    tone = 'light',
}: {
    points: number | null;
    tone?: 'light' | 'dark';
}) {
    if (points === null) {
        return null;
    }

    const earned =
        tone === 'dark' ? 'text-gold' : 'text-pitch-deep dark:text-primary';
    const zero = tone === 'dark' ? 'text-red-300' : 'text-destructive';

    return (
        <span
            className={cn(
                'font-display tabular-nums',
                points > 0 ? earned : zero,
            )}
        >
            {points > 0 ? `+${points}` : '0'}
        </span>
    );
}

/** A lane-coloured pill carrying one player's pick (or its empty state) for a single fixture. */
function LaneChip({
    index,
    player,
    muted,
    children,
}: {
    index: number;
    player: ComparePlayer;
    muted?: boolean;
    children: ReactNode;
}) {
    return (
        <span
            className={cn(
                'inline-flex items-center gap-1.5 rounded-full bg-secondary px-2.5 py-1 text-[11px] font-semibold',
                muted && 'opacity-60',
            )}
        >
            <span
                className={cn('size-2 shrink-0 rounded-full', lane(index).dot)}
                aria-hidden
            />
            <span className="text-muted-foreground" title={player.name}>
                {laneLabel(player)}
            </span>
            {children}
        </span>
    );
}

/** The "no revealed pick" chip: a lock until the window closes, then a dash for no prediction. */
function EmptyPick({
    index,
    player,
    hidden,
}: {
    index: number;
    player: ComparePlayer;
    hidden: boolean;
}) {
    return (
        <LaneChip index={index} player={player} muted>
            {hidden ? (
                <Lock
                    className="size-3 text-muted-foreground"
                    aria-label="Hidden until predictions lock"
                />
            ) : (
                <span className="text-muted-foreground">—</span>
            )}
        </LaneChip>
    );
}

/**
 * A compact per-fixture strip of every compared player's pick — one lane chip each, or the
 * lock/dash empty state. Group picks read as the scoreline + points; knockout picks render the full
 * {@link KnockoutPickMatchup} (predicted teams + advancing for upfront, score + advancer for
 * phased), matching the Groups compare cards. `home`/`away` are the official teams, needed to
 * resolve a phased knockout pick's advancer. Reveal gating matches the cards: an opponent's pick is
 * the lock icon until that fixture's window locks, then a dash when they predicted nothing.
 */
export function FixtureComparePicks({
    players,
    windowStatus,
    kind,
    fixtureId,
    home = null,
    away = null,
}: {
    players: ComparePlayer[];
    windowStatus: PredictionWindowStatus | undefined;
    kind: 'group' | 'knockout';
    fixtureId: number;
    home?: TeamRef | null;
    away?: TeamRef | null;
}) {
    return (
        <div className="mt-2 flex flex-wrap justify-center gap-1.5">
            {players.map((player, index) => {
                if (kind === 'knockout') {
                    const pick = player.knockout_predictions[fixtureId];

                    return pick ? (
                        <LaneChip key={index} index={index} player={player}>
                            <ComparePickMatchup
                                pick={pick}
                                home={home}
                                away={away}
                            />
                            <PointsTick points={pick.points_awarded} />
                        </LaneChip>
                    ) : (
                        <EmptyPick
                            key={index}
                            index={index}
                            player={player}
                            hidden={!isRevealed(player, windowStatus)}
                        />
                    );
                }

                const pick = player.group_predictions[fixtureId];

                return pick ? (
                    <LaneChip key={index} index={index} player={player}>
                        <b className="font-display text-foreground tabular-nums">
                            {pick.home_goals ?? '–'}–{pick.away_goals ?? '–'}
                        </b>
                        <PointsTick points={pick.points_awarded} />
                    </LaneChip>
                ) : (
                    <EmptyPick
                        key={index}
                        index={index}
                        player={player}
                        hidden={!isRevealed(player, windowStatus)}
                    />
                );
            })}
        </div>
    );
}

/* --------------------------------------------------------- group fixtures */

function OfficialGroupMatchup({
    fixture,
}: {
    fixture: GroupView['fixtures'][number];
}) {
    const tz = useDisplayTimeZone();
    const settled = fixture.home_goals !== null && fixture.away_goals !== null;

    return (
        <>
            <div className="mb-1.5 flex items-center justify-between gap-2 text-[11px] text-muted-foreground">
                {fixture.kicks_off_at && (
                    <span className="font-display font-semibold whitespace-nowrap">
                        {formatMatchDate(fixture.kicks_off_at, tz)} ·{' '}
                        {formatMatchTime(fixture.kicks_off_at, tz)}
                    </span>
                )}
                {fixture.venue && (
                    <span className="truncate">
                        {venueLabel(fixture.venue)}
                    </span>
                )}
            </div>
            <div className="grid grid-cols-[1fr_auto_1fr] items-center gap-2">
                <span className="flex min-w-0 items-center justify-end gap-1.5 text-sm font-bold">
                    <TeamMatchupName team={fixture.home} />
                    <Flag team={fixture.home} className="h-4 w-6" />
                </span>
                {settled ? (
                    <span className="font-display text-base font-semibold tabular-nums">
                        {fixture.home_goals}–{fixture.away_goals}
                    </span>
                ) : (
                    <span className="font-display text-xs text-muted-foreground">
                        v
                    </span>
                )}
                <span className="flex min-w-0 items-center gap-1.5 text-sm font-bold">
                    <Flag team={fixture.away} className="h-4 w-6" />
                    <TeamMatchupName team={fixture.away} />
                </span>
            </div>
        </>
    );
}

function GroupPickChips({
    fixtureId,
    players,
    windowStatus,
}: {
    fixtureId: number;
    players: ComparePlayer[];
    windowStatus: PredictionWindowStatus;
}) {
    return (
        <div className="mt-2 flex flex-wrap justify-center gap-1.5">
            {players.map((player, index) => {
                const pick = player.group_predictions[fixtureId];

                if (pick) {
                    return (
                        <LaneChip key={index} index={index} player={player}>
                            <b className="font-display text-foreground tabular-nums">
                                {pick.home_goals}–{pick.away_goals}
                            </b>
                            <PointsTick points={pick.points_awarded} />
                        </LaneChip>
                    );
                }

                return (
                    <EmptyPick
                        key={index}
                        index={index}
                        player={player}
                        hidden={!isRevealed(player, windowStatus)}
                    />
                );
            })}
        </div>
    );
}

/** A stable per-player key for the standings switch, so it survives lanes being added or removed. */
function playerKey(player: ComparePlayer, index: number): string {
    if (player.entry_id != null) {
        return `e${player.entry_id}`;
    }

    return player.is_viewer ? 'viewer' : `i${index}`;
}

/** The on-demand standings inside a compare group card: an Official / per-player table switch. */
function CompareStandingsPanel({
    groupName,
    official,
    players,
}: {
    groupName: string;
    official: StandingRow[];
    players: ComparePlayer[];
}) {
    // Phased pools never surface a projected table (the bracket follows real results), so when no
    // player has one, show the official table alone — no toggle. This also tidies the pre-prediction
    // case where nothing has been projected yet.
    const anyProjected = players.some(
        (player) => (player.projected_standings[groupName]?.length ?? 0) > 0,
    );

    // Keyed by a stable player identity, not array position: removing a lane reindexes `players`, so
    // an index-based selection would silently point at a different (or missing) player's table.
    const [view, setView] = useState('official');

    if (!anyProjected) {
        return official.length > 0 ? (
            <StandingsTable standings={official} />
        ) : (
            <p className="py-4 text-center text-sm text-muted-foreground">
                No results yet.
            </p>
        );
    }

    const selected =
        view === 'official'
            ? null
            : (players.find(
                  (player, index) => playerKey(player, index) === view,
              ) ?? null);
    // Fall back to Official when the selected lane is gone, so nothing stale lingers.
    const effectiveView = view === 'official' || selected ? view : 'official';
    const rows = selected ? selected.projected_standings[groupName] : official;

    return (
        <div className="flex flex-col gap-3">
            <ToggleGroup
                type="single"
                variant="outline"
                size="sm"
                value={effectiveView}
                onValueChange={(next) => {
                    if (next) {
                        setView(next);
                    }
                }}
                className="flex-wrap justify-center"
            >
                <ToggleGroupItem value="official" className="px-3 text-xs">
                    Official
                </ToggleGroupItem>
                {players.map((player, index) => (
                    <ToggleGroupItem
                        key={playerKey(player, index)}
                        value={playerKey(player, index)}
                        disabled={player.projected_standings[groupName] == null}
                        className="gap-1.5 px-3 text-xs"
                        title={player.name}
                    >
                        <span
                            className={cn(
                                'size-2 rounded-full',
                                lane(index).dot,
                            )}
                            aria-hidden
                        />
                        {laneLabel(player)}
                    </ToggleGroupItem>
                ))}
            </ToggleGroup>

            {rows && rows.length > 0 ? (
                <StandingsTable standings={rows} />
            ) : (
                <p className="py-4 text-center text-sm text-muted-foreground">
                    {effectiveView === 'official'
                        ? 'No results yet.'
                        : 'No projected table to show yet.'}
                </p>
            )}
        </div>
    );
}

export function CompareGroupCard({
    group,
    players,
    windowStatus,
}: {
    group: GroupView;
    players: ComparePlayer[];
    windowStatus: PredictionWindowStatus;
}) {
    return (
        <div className="card-elevated rounded-3xl p-5">
            <div className="mb-3 flex items-center justify-between gap-2">
                <div className="flex min-w-0 items-center gap-2.5">
                    <GroupBadge name={group.name} />
                    <h3 className="font-display text-base font-semibold whitespace-nowrap">
                        Group {group.name}
                    </h3>
                </div>
                <span className="text-[11px] font-bold tracking-wide text-muted-foreground uppercase">
                    {group.fixtures.length} matches
                </span>
            </div>

            <div className="flex flex-wrap gap-1.5 border-b border-border pb-3">
                {group.teams.map((team) => (
                    <TeamChip key={team.id} team={team} />
                ))}
            </div>

            <div className="mt-1">
                {group.fixtures.map((fixture) => (
                    <div
                        key={fixture.fixture_id}
                        className="border-t border-border py-3 first:border-t-0"
                    >
                        <OfficialGroupMatchup fixture={fixture} />
                        <GroupPickChips
                            fixtureId={fixture.fixture_id}
                            players={players}
                            windowStatus={windowStatus}
                        />
                    </div>
                ))}
            </div>

            {group.standings.length > 0 && (
                <Collapsible className="mt-2">
                    <CollapsibleTrigger className="flex w-full items-center justify-center gap-1.5 border-t border-border pt-3 font-display text-xs font-semibold tracking-wide text-muted-foreground uppercase transition-colors outline-none hover:text-foreground focus-visible:text-foreground [&[data-state=open]>svg]:rotate-180">
                        Standings
                        <ChevronDown className="size-4 transition-transform duration-200" />
                    </CollapsibleTrigger>
                    <CollapsibleContent className="pt-2">
                        <CompareStandingsPanel
                            groupName={group.name}
                            official={group.standings}
                            players={players}
                        />
                    </CollapsibleContent>
                </Collapsible>
            )}
        </div>
    );
}

/* ------------------------------------------------------------ knockout */

/**
 * Adapts a compared player's knockout pick to the shared {@link KnockoutPickMatchup} so every
 * surface (cards, compare lanes, flat rows) renders picks identically. `home`/`away` are the
 * official teams, used to resolve the advancing team for phased picks (which carry no predicted
 * teams of their own).
 */
function ComparePickMatchup({
    pick,
    home,
    away,
    tone = 'light',
}: {
    pick: ComparePlayer['knockout_predictions'][number];
    home: TeamRef | null;
    away: TeamRef | null;
    tone?: 'light' | 'dark';
}) {
    return (
        <KnockoutPickMatchup
            homeGoals={pick.home_goals}
            awayGoals={pick.away_goals}
            advancingTeamId={pick.advancing_team_id}
            predictedHome={pick.predicted_home}
            predictedAway={pick.predicted_away}
            officialHome={home}
            officialAway={away}
            tone={tone}
        />
    );
}

function OfficialKnockout({ fixture }: { fixture: BracketFixture }) {
    const tz = useDisplayTimeZone();
    const settled = fixture.home_goals !== null && fixture.away_goals !== null;
    const homeWins =
        fixture.winner_team_id != null &&
        fixture.winner_team_id === fixture.home?.id;
    const awayWins =
        fixture.winner_team_id != null &&
        fixture.winner_team_id === fixture.away?.id;
    // A level result decided on penalties/extra time — the only case the "Advances" chip is needed.
    const isDraw = settled && fixture.home_goals === fixture.away_goals;

    return (
        <>
            <div className="mb-1.5 flex items-center justify-between gap-2 text-[11px] text-muted-foreground">
                <span className="font-display font-semibold">
                    Match {fixture.match_number}
                </span>
                {fixture.kicks_off_at && (
                    <span className="font-semibold whitespace-nowrap">
                        {formatMatchDate(fixture.kicks_off_at, tz)} ·{' '}
                        {formatMatchTime(fixture.kicks_off_at, tz)}
                    </span>
                )}
            </div>
            <div className="grid grid-cols-[1fr_auto_1fr] items-center gap-2">
                <span
                    className={cn(
                        'flex min-w-0 items-center justify-end gap-1.5 text-sm',
                        homeWins
                            ? 'font-extrabold text-foreground'
                            : 'font-semibold text-muted-foreground',
                    )}
                >
                    <span className="truncate">
                        {slotLabel(fixture.home, fixture.home_label)}
                    </span>
                    <Flag team={fixture.home} className="h-4 w-6" />
                    {isDraw && homeWins && <AdvanceChip />}
                </span>
                {settled ? (
                    <span className="font-display text-base font-semibold tabular-nums">
                        {fixture.home_goals}–{fixture.away_goals}
                    </span>
                ) : (
                    <span className="font-display text-xs text-muted-foreground">
                        v
                    </span>
                )}
                <span
                    className={cn(
                        'flex min-w-0 items-center gap-1.5 text-sm',
                        awayWins
                            ? 'font-extrabold text-foreground'
                            : 'font-semibold text-muted-foreground',
                    )}
                >
                    {isDraw && awayWins && <AdvanceChip />}
                    <Flag team={fixture.away} className="h-4 w-6" />
                    <span className="truncate">
                        {slotLabel(fixture.away, fixture.away_label)}
                    </span>
                </span>
            </div>
        </>
    );
}

function KnockoutPickChips({
    fixture,
    players,
    windowStatus,
}: {
    fixture: BracketFixture;
    players: ComparePlayer[];
    windowStatus: PredictionWindowStatus;
}) {
    return (
        <div className="mt-3 flex flex-wrap justify-center gap-1.5 border-t border-border pt-3">
            {players.map((player, index) => {
                const pick = player.knockout_predictions[fixture.fixture_id];

                if (pick) {
                    return (
                        <LaneChip key={index} index={index} player={player}>
                            <ComparePickMatchup
                                pick={pick}
                                home={fixture.home}
                                away={fixture.away}
                            />
                            <PointsTick points={pick.points_awarded} />
                        </LaneChip>
                    );
                }

                return (
                    <EmptyPick
                        key={index}
                        index={index}
                        player={player}
                        hidden={!isRevealed(player, windowStatus)}
                    />
                );
            })}
        </div>
    );
}

export function CompareKnockoutCard({
    fixture,
    players,
    windowStatus,
}: {
    fixture: BracketFixture;
    players: ComparePlayer[];
    windowStatus: PredictionWindowStatus;
}) {
    return (
        <div className="card-elevated rounded-3xl p-5">
            <OfficialKnockout fixture={fixture} />
            <KnockoutPickChips
                fixture={fixture}
                players={players}
                windowStatus={windowStatus}
            />
        </div>
    );
}

export function CompareFinalCard({
    fixture,
    players,
    windowStatus,
}: {
    fixture: BracketFixture;
    players: ComparePlayer[];
    windowStatus: PredictionWindowStatus;
}) {
    const tz = useDisplayTimeZone();
    const settled = fixture.home_goals !== null && fixture.away_goals !== null;

    return (
        <div className="relative mx-auto max-w-xl overflow-hidden rounded-3xl border border-accent/30 bg-ink p-6 text-center text-white sm:p-8">
            <div className="pointer-events-none absolute -top-20 left-1/2 size-72 -translate-x-1/2 rounded-full bg-gold opacity-20 blur-[110px]" />
            <div className="relative">
                <div className="text-3xl">🏆</div>
                <h3 className="mt-2 font-display text-xs font-bold tracking-[0.18em] text-gold uppercase">
                    The Final · Match {fixture.match_number}
                </h3>
                {fixture.kicks_off_at && (
                    <div className="mt-1 text-sm font-semibold text-white/60">
                        {formatMatchDate(fixture.kicks_off_at, tz)} ·{' '}
                        {formatMatchTime(fixture.kicks_off_at, tz)}
                    </div>
                )}

                <div className="mt-5 flex items-center justify-center gap-4">
                    <span className="flex items-center gap-2 font-display text-sm font-semibold">
                        {slotLabel(fixture.home, fixture.home_label)}
                        <Flag
                            team={fixture.home}
                            className="h-8 w-11 rounded-md"
                        />
                    </span>
                    <span className="font-display text-lg font-semibold text-white/80 tabular-nums">
                        {settled
                            ? `${fixture.home_goals}–${fixture.away_goals}`
                            : 'v'}
                    </span>
                    <span className="flex items-center gap-2 font-display text-sm font-semibold">
                        <Flag
                            team={fixture.away}
                            className="h-8 w-11 rounded-md"
                        />
                        {slotLabel(fixture.away, fixture.away_label)}
                    </span>
                </div>

                <div className="mt-5 border-t border-white/10 pt-4">
                    <div className="text-[11px] font-bold tracking-[0.14em] text-white/50 uppercase">
                        Their final
                    </div>
                    <div className="mt-2.5 flex flex-wrap justify-center gap-2">
                        {players.map((player, index) => {
                            const pick =
                                player.knockout_predictions[fixture.fixture_id];
                            const hidden = !isRevealed(player, windowStatus);

                            return (
                                <span
                                    key={index}
                                    className={cn(
                                        'inline-flex items-center gap-1.5 rounded-full bg-white/10 px-2.5 py-1 text-[11px] font-semibold',
                                        !pick && 'opacity-60',
                                    )}
                                >
                                    <span
                                        className={cn(
                                            'size-2 shrink-0 rounded-full',
                                            lane(index).dot,
                                        )}
                                        aria-hidden
                                    />
                                    <span
                                        className="text-white/60"
                                        title={player.name}
                                    >
                                        {laneLabel(player)}
                                    </span>
                                    {pick ? (
                                        <>
                                            <ComparePickMatchup
                                                pick={pick}
                                                home={fixture.home}
                                                away={fixture.away}
                                                tone="dark"
                                            />
                                            <PointsTick
                                                points={pick.points_awarded}
                                                tone="dark"
                                            />
                                        </>
                                    ) : hidden ? (
                                        <Lock
                                            className="size-3 text-white/50"
                                            aria-label="Hidden until predictions lock"
                                        />
                                    ) : (
                                        <span className="text-white/50">—</span>
                                    )}
                                </span>
                            );
                        })}
                    </div>
                </div>
            </div>
        </div>
    );
}
