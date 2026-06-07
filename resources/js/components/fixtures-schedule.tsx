import { useState } from 'react';
import {
    formatMatchDate,
    formatMatchTime,
    formatScheduleDateHeader,
    PhaseMeta,
    phaseDateRange,
    PickAdvancer,
    PointsBadge,
    ShowTimesToggle,
    slotAbbrev,
} from '@/components/fixtures';
import { FixtureComparePicks } from '@/components/fixtures-compare';
import { Flag } from '@/components/flag';
import { MatchdayChip, MatchdayStripe } from '@/components/matchday-marker';
import { useDisplayTimeZone } from '@/hooks/use-timezone';
import { cn } from '@/lib/utils';
import type {
    BracketFixture,
    BracketPhase,
    Comparison,
    GroupFixture,
    GroupView,
    MatchdayDescriptor,
    TeamRef,
} from '@/types/pools';

/**
 * A group or knockout fixture flattened into one shape, so the Matchday and Schedule views can list
 * them together regardless of source. `context` names where the match sits (its group or round); the
 * label fields back the placeholder text knockout slots use before their teams are known.
 */
interface NormalizedMatch {
    fixtureId: number;
    /** Which prediction map the fixture's compare picks live in, and its window key. */
    kind: 'group' | 'knockout';
    matchdayKey: string | null;
    context: string;
    kicksOffAt: string | null;
    venue: string | null;
    home: TeamRef | null;
    away: TeamRef | null;
    homeLabel: string | null;
    awayLabel: string | null;
    homeGoals: number | null;
    awayGoals: number | null;
    winnerTeamId: number | null;
    pick: {
        homeGoals: number | null;
        awayGoals: number | null;
        advancingTeamId: number | null;
        pointsAwarded: number | null;
    } | null;
}

function normalizeGroupFixture(
    fixture: GroupFixture,
    groupName: string,
): NormalizedMatch {
    return {
        fixtureId: fixture.fixture_id,
        kind: 'group',
        matchdayKey: fixture.matchday_key,
        context: `Group ${groupName}`,
        kicksOffAt: fixture.kicks_off_at,
        venue: fixture.venue,
        home: fixture.home,
        away: fixture.away,
        homeLabel: null,
        awayLabel: null,
        homeGoals: fixture.home_goals,
        awayGoals: fixture.away_goals,
        winnerTeamId: null,
        pick: fixture.prediction
            ? {
                  homeGoals: fixture.prediction.home_goals,
                  awayGoals: fixture.prediction.away_goals,
                  advancingTeamId: null,
                  pointsAwarded: fixture.prediction.points_awarded,
              }
            : null,
    };
}

function normalizeBracketFixture(
    fixture: BracketFixture,
    phaseName: string,
): NormalizedMatch {
    return {
        fixtureId: fixture.fixture_id,
        kind: 'knockout',
        matchdayKey: fixture.matchday_key,
        context: phaseName,
        kicksOffAt: fixture.kicks_off_at,
        venue: fixture.venue,
        home: fixture.home,
        away: fixture.away,
        homeLabel: fixture.home_label,
        awayLabel: fixture.away_label,
        homeGoals: fixture.home_goals,
        awayGoals: fixture.away_goals,
        winnerTeamId: fixture.winner_team_id,
        pick: fixture.prediction
            ? {
                  homeGoals: fixture.prediction.home_goals,
                  awayGoals: fixture.prediction.away_goals,
                  advancingTeamId: fixture.prediction.advancing_team_id,
                  pointsAwarded: fixture.prediction.points_awarded,
              }
            : null,
    };
}

/** Every fixture in the pool, flattened and tagged with its group/round, for the flat views. */
function normalizeAll(
    groups: GroupView[],
    bracket: BracketPhase[],
): NormalizedMatch[] {
    return [
        ...groups.flatMap((group) =>
            group.fixtures.map((fixture) =>
                normalizeGroupFixture(fixture, group.name),
            ),
        ),
        ...bracket.flatMap((phase) =>
            phase.fixtures.map((fixture) =>
                normalizeBracketFixture(fixture, phase.phase_name),
            ),
        ),
    ];
}

/** Kickoff time in ms, or +Infinity for an unscheduled match so it sorts last. */
function kickoffMs(match: NormalizedMatch): number {
    return match.kicksOffAt ? new Date(match.kicksOffAt).getTime() : Infinity;
}

/** The pool page's global time filter: everything, today's kickoffs, or every not-yet-played match. */
export type TimeFilter = 'all' | 'today' | 'upcoming';

/** A match is settled once its official result is in (mirrors ScheduleRow / MatchRow). */
function isSettled(
    homeGoals: number | null,
    awayGoals: number | null,
): boolean {
    return homeGoals !== null && awayGoals !== null;
}

/** Whether a kickoff falls on the current calendar day in the viewer's timezone. */
function isToday(kicksOffAt: string | null, tz: string): boolean {
    if (!kicksOffAt) {
        return false;
    }

    // en-CA renders YYYY-MM-DD, so comparing the formatted day strings in the viewer's zone is exact.
    const day = new Intl.DateTimeFormat('en-CA', {
        timeZone: tz,
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
    });

    return day.format(new Date(kicksOffAt)) === day.format(new Date());
}

/**
 * Whether a match passes the time filter: All keeps everything, Today keeps matches kicking off
 * today (viewer's zone), Upcoming keeps every match without an official result yet.
 */
function matchesTimeFilter(
    fields: {
        kicksOffAt: string | null;
        homeGoals: number | null;
        awayGoals: number | null;
    },
    filter: TimeFilter,
    tz: string,
): boolean {
    switch (filter) {
        case 'today':
            return isToday(fields.kicksOffAt, tz);
        case 'upcoming':
            return !isSettled(fields.homeGoals, fields.awayGoals);
        default:
            return true;
    }
}

/** {@see matchesTimeFilter} for a raw group/bracket fixture (snake_case fields), used in the Groups view. */
export function matchesFixtureTimeFilter(
    fixture: {
        kicks_off_at: string | null;
        home_goals: number | null;
        away_goals: number | null;
    },
    filter: TimeFilter,
    tz: string,
): boolean {
    return matchesTimeFilter(
        {
            kicksOffAt: fixture.kicks_off_at,
            homeGoals: fixture.home_goals,
            awayGoals: fixture.away_goals,
        },
        filter,
        tz,
    );
}

function matchesNormalized(
    match: NormalizedMatch,
    filter: TimeFilter,
    tz: string,
): boolean {
    return matchesTimeFilter(
        {
            kicksOffAt: match.kicksOffAt,
            homeGoals: match.homeGoals,
            awayGoals: match.awayGoals,
        },
        filter,
        tz,
    );
}

/** Shown in a flat view when the active time filter leaves nothing to list. */
export function FixturesEmptyState({ message }: { message: string }) {
    return (
        <p className="card-elevated rounded-3xl px-5 py-12 text-center text-sm text-muted-foreground">
            {message}
        </p>
    );
}

/** The empty-state message for a time filter that matched no fixtures. */
export function timeFilterEmptyMessage(filter: TimeFilter): string {
    return filter === 'today'
        ? 'No matches kicking off today.'
        : 'No upcoming matches — every match has been played.';
}

function sectionMeta(count: number, range: string | null): string {
    const label = `${count} ${count === 1 ? 'match' : 'matches'}`;

    return [label, range].filter(Boolean).join(' · ');
}

function venueLabel(venue: string): string {
    return venue.replace(/\s+Stadium$/, '');
}

function teamCode(team: TeamRef | null, label: string | null): string {
    return team?.code ?? team?.name ?? slotAbbrev(label);
}

/** One side of a flat match row: the team's flag + code, or the placeholder token for an open slot. */
function SideToken({
    team,
    label,
    align,
    advancing,
}: {
    team: TeamRef | null;
    label: string | null;
    align: 'start' | 'end';
    advancing: boolean;
}) {
    return (
        <span
            className={cn(
                'flex min-w-0 items-center gap-1.5 text-sm',
                align === 'end' ? 'justify-end' : 'justify-start',
                advancing
                    ? 'font-extrabold text-foreground'
                    : 'font-semibold text-muted-foreground',
            )}
        >
            {align === 'end' ? (
                <>
                    <span className="truncate">{teamCode(team, label)}</span>
                    <Flag team={team} className="h-4 w-6" />
                </>
            ) : (
                <>
                    <Flag team={team} className="h-4 w-6" />
                    <span className="truncate">{teamCode(team, label)}</span>
                </>
            )}
        </span>
    );
}

/** A single match as a flat row, shared by the Matchday and Schedule views. */
function ScheduleRow({
    match,
    comparison,
    showTimes,
}: {
    match: NormalizedMatch;
    comparison: Comparison | null;
    showTimes: boolean;
}) {
    const tz = useDisplayTimeZone();
    const settled = match.homeGoals !== null && match.awayGoals !== null;
    const homeAdvances =
        settled &&
        (match.winnerTeamId !== null
            ? match.winnerTeamId === match.home?.id
            : (match.homeGoals ?? 0) > (match.awayGoals ?? 0));
    const awayAdvances =
        settled &&
        (match.winnerTeamId !== null
            ? match.winnerTeamId === match.away?.id
            : (match.awayGoals ?? 0) > (match.homeGoals ?? 0));

    // On a drawn knockout pick the score alone doesn't say who the viewer put through; resolve the
    // advancing id against the real match-up so phased/score-only views can surface it.
    const pickDrawAdvancer =
        match.kind === 'knockout' &&
        match.pick != null &&
        match.pick.homeGoals !== null &&
        match.pick.awayGoals !== null &&
        match.pick.homeGoals === match.pick.awayGoals &&
        match.pick.advancingTeamId != null
            ? match.pick.advancingTeamId === match.home?.id
                ? match.home
                : match.pick.advancingTeamId === match.away?.id
                  ? match.away
                  : null
            : null;

    // While comparing, the row shows every player's pick (below) instead of just the viewer's; the
    // window key is the phase the fixture sits in (the group stage shares one 'group' window).
    const windowKey =
        match.kind === 'group' ? 'group' : (match.matchdayKey ?? '');

    return (
        <div className="relative border-t border-border py-2.5 pl-3 first:border-t-0">
            <MatchdayStripe matchdayKey={match.matchdayKey} />
            <div className="grid grid-cols-[1fr_auto_1fr] items-center gap-3">
                <div className="min-w-0">
                    <MatchdayChip matchdayKey={match.matchdayKey} />
                </div>

                <div className="flex min-w-0 flex-col items-center gap-0.5">
                    <div className="grid grid-cols-[1fr_auto_1fr] items-center gap-2">
                        <SideToken
                            team={match.home}
                            label={match.homeLabel}
                            align="end"
                            advancing={homeAdvances}
                        />
                        {settled ? (
                            <span className="text-center font-display text-base font-semibold tabular-nums">
                                {match.homeGoals}–{match.awayGoals}
                            </span>
                        ) : (
                            <span className="text-center font-display text-xs text-muted-foreground">
                                v
                            </span>
                        )}
                        <SideToken
                            team={match.away}
                            label={match.awayLabel}
                            align="start"
                            advancing={awayAdvances}
                        />
                    </div>
                    {!comparison &&
                        (match.pick &&
                        match.pick.homeGoals !== null &&
                        match.pick.awayGoals !== null ? (
                            <div className="flex flex-wrap items-center justify-center gap-x-2 gap-y-1 text-[11px] text-muted-foreground">
                                <span>
                                    You{' '}
                                    <span className="font-semibold tabular-nums">
                                        {match.pick.homeGoals}–
                                        {match.pick.awayGoals}
                                    </span>
                                </span>
                                {pickDrawAdvancer && (
                                    <PickAdvancer team={pickDrawAdvancer} />
                                )}
                            </div>
                        ) : (
                            <div className="text-[11px] font-medium text-muted-foreground/70">
                                No prediction
                            </div>
                        ))}
                </div>

                <div className="justify-self-end">
                    {!comparison && (
                        <PointsBadge
                            points={match.pick?.pointsAwarded ?? null}
                        />
                    )}
                </div>
            </div>

            <div
                className={cn(
                    'mt-1 truncate text-[11px] text-muted-foreground',
                    showTimes ? 'block' : 'hidden',
                )}
            >
                {match.kicksOffAt
                    ? `${formatMatchDate(match.kicksOffAt, tz)} · ${formatMatchTime(match.kicksOffAt, tz)} · `
                    : ''}
                {match.context}
                {match.venue ? ` · ${venueLabel(match.venue)}` : ''}
            </div>

            {comparison && (
                <FixtureComparePicks
                    players={comparison.players}
                    windowStatus={comparison.windows[windowKey]}
                    kind={match.kind}
                    fixtureId={match.fixtureId}
                />
            )}
        </div>
    );
}

/** A card holding the flat rows of one section (a matchday, or a calendar day). */
function ScheduleSection({
    title,
    meta,
    matches,
    comparison,
}: {
    title: string;
    meta: string;
    matches: NormalizedMatch[];
    comparison: Comparison | null;
}) {
    const [showTimes, setShowTimes] = useState(false);

    return (
        <div>
            <PhaseMeta
                title={title}
                meta={
                    <span className="flex items-center gap-2">
                        {meta}
                        <ShowTimesToggle
                            open={showTimes}
                            onToggle={() => setShowTimes((value) => !value)}
                        />
                    </span>
                }
            />
            <div className="card-elevated rounded-3xl px-5 py-1">
                {matches.map((match) => (
                    <ScheduleRow
                        key={match.fixtureId}
                        match={match}
                        comparison={comparison}
                        showTimes={showTimes}
                    />
                ))}
            </div>
        </div>
    );
}

/**
 * Fixtures grouped into the leaderboard's matchdays — Matchday 1/2/3, then one section per knockout
 * round — so the page lines up exactly with how the leaderboard reports each round.
 */
export function MatchdayView({
    groups,
    bracket,
    matchdays,
    filter,
    comparison,
}: {
    groups: GroupView[];
    bracket: BracketPhase[];
    matchdays: MatchdayDescriptor[];
    filter: TimeFilter;
    comparison: Comparison | null;
}) {
    const tz = useDisplayTimeZone();
    const all = normalizeAll(groups, bracket).filter((match) =>
        matchesNormalized(match, filter, tz),
    );

    const sections = matchdays
        .map((matchday) => ({
            matchday,
            matches: all
                .filter((match) => match.matchdayKey === matchday.key)
                .sort((a, b) => kickoffMs(a) - kickoffMs(b)),
        }))
        .filter((section) => section.matches.length > 0);

    if (sections.length === 0) {
        return <FixturesEmptyState message={timeFilterEmptyMessage(filter)} />;
    }

    return (
        <div className="flex flex-col gap-10">
            {sections.map(({ matchday, matches }) => (
                <ScheduleSection
                    key={matchday.key}
                    title={matchday.label}
                    meta={sectionMeta(
                        matches.length,
                        phaseDateRange(
                            matches.map((match) => ({
                                kicks_off_at: match.kicksOffAt,
                            })),
                            tz,
                        ),
                    )}
                    matches={matches}
                    comparison={comparison}
                />
            ))}
        </div>
    );
}

/** Every fixture in strict kickoff order, grouped under calendar-day headers in the viewer's zone. */
export function ScheduleView({
    groups,
    bracket,
    filter,
    comparison,
}: {
    groups: GroupView[];
    bracket: BracketPhase[];
    filter: TimeFilter;
    comparison: Comparison | null;
}) {
    const tz = useDisplayTimeZone();
    const sorted = normalizeAll(groups, bracket)
        .filter((match) => matchesNormalized(match, filter, tz))
        .sort((a, b) => kickoffMs(a) - kickoffMs(b));

    // Group consecutive matches by their calendar day (in the viewer's zone); unscheduled matches
    // fall to a trailing "Date TBD" bucket. Order is preserved from the kickoff sort above.
    const days: { header: string; matches: NormalizedMatch[] }[] = [];

    for (const match of sorted) {
        const header = match.kicksOffAt
            ? formatScheduleDateHeader(match.kicksOffAt, tz)
            : 'Date TBD';
        const last = days[days.length - 1];

        if (last && last.header === header) {
            last.matches.push(match);
        } else {
            days.push({ header, matches: [match] });
        }
    }

    if (days.length === 0) {
        return <FixturesEmptyState message={timeFilterEmptyMessage(filter)} />;
    }

    return (
        <div className="flex flex-col gap-10">
            {days.map((day) => (
                <ScheduleSection
                    key={day.header}
                    title={day.header}
                    meta={sectionMeta(day.matches.length, null)}
                    matches={day.matches}
                    comparison={comparison}
                />
            ))}
        </div>
    );
}
