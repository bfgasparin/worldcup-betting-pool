import { ChevronDown, Lock } from 'lucide-react';
import type { ReactNode } from 'react';
import { useState } from 'react';
import {
    formatMatchDate,
    formatMatchTime,
    GroupBadge,
    TeamChip,
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

function code(team: TeamRef | null): string {
    return team?.code ?? team?.name ?? 'TBD';
}

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
                    <span className="truncate">{code(fixture.home)}</span>
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
                    <span className="truncate">{code(fixture.away)}</span>
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
    // Keyed by a stable player identity, not array position: removing a lane reindexes `players`, so
    // an index-based selection would silently point at a different (or missing) player's table.
    const [view, setView] = useState('official');
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
 * A compact rendering of one player's knockout pick. For an **upfront** bracket the player picks
 * their own match-up, so both teams are shown (flag + code) with the side they sent through
 * emphasised and the scoreline between (e.g. "ARG🏴 2–1 🏴MEX"). For a **phased** bracket the teams
 * are the official ones (already shown atop the card) and aren't predicted, so only the score and
 * the advancing team (flag + code) are shown. Tones adapt to the light knockout card and the dark
 * final card.
 */
function PickMatchup({
    pick,
    fixture,
    tone = 'light',
}: {
    pick: ComparePlayer['knockout_predictions'][number];
    fixture: BracketFixture;
    tone?: 'light' | 'dark';
}) {
    const advancingId = pick.advancing_team_id;
    const hasScore = pick.home_goals !== null && pick.away_goals !== null;
    // Predicted teams are present only for upfront brackets; phased picks omit them (the payload
    // sends null), since the match-up is the official one shown on the card.
    const predictsTeams =
        pick.predicted_home != null || pick.predicted_away != null;

    const advanced =
        tone === 'dark' ? 'font-bold text-gold' : 'font-bold text-foreground';
    const muted = tone === 'dark' ? 'text-white/70' : 'text-muted-foreground';
    const scoreColor = tone === 'dark' ? 'text-white/80' : 'text-foreground';
    const flagClass = 'h-3 w-[18px]';

    const scoreNode = hasScore ? (
        <b className={cn('font-display tabular-nums', scoreColor)}>
            {pick.home_goals}–{pick.away_goals}
        </b>
    ) : (
        <span className={muted}>v</span>
    );

    if (predictsTeams) {
        const home = pick.predicted_home;
        const away = pick.predicted_away;
        const homeAdvances = advancingId != null && advancingId === home?.id;
        const awayAdvances = advancingId != null && advancingId === away?.id;

        return (
            <span className="inline-flex items-center gap-1.5">
                <span
                    className={cn(
                        'inline-flex items-center gap-1',
                        homeAdvances ? advanced : muted,
                    )}
                >
                    {code(home)}
                    <Flag team={home} className={flagClass} />
                </span>
                {scoreNode}
                <span
                    className={cn(
                        'inline-flex items-center gap-1',
                        awayAdvances ? advanced : muted,
                    )}
                >
                    <Flag team={away} className={flagClass} />
                    {code(away)}
                </span>
            </span>
        );
    }

    // Phased: show only the score and the advancing team (resolved against the official teams).
    const advancing =
        advancingId == null
            ? null
            : advancingId === fixture.home?.id
              ? fixture.home
              : advancingId === fixture.away?.id
                ? fixture.away
                : null;

    if (!hasScore && advancing === null) {
        return <span className={muted}>—</span>;
    }

    return (
        <span className="inline-flex items-center gap-1.5">
            {scoreNode}
            {advancing && (
                <span
                    className={cn('inline-flex items-center gap-1', advanced)}
                >
                    <Flag team={advancing} className={flagClass} />
                    {code(advancing)}
                </span>
            )}
        </span>
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
                            <PickMatchup pick={pick} fixture={fixture} />
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
        <div className="relative mx-auto max-w-xl overflow-hidden rounded-3xl border border-accent/30 bg-ink p-8 text-center text-white">
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
                                            <PickMatchup
                                                pick={pick}
                                                fixture={fixture}
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
