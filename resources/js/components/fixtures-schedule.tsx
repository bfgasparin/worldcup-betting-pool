import {
    formatMatchDate,
    formatMatchTime,
    formatScheduleDateHeader,
    PhaseMeta,
    phaseDateRange,
    PointsBadge,
    slotAbbrev,
} from '@/components/fixtures';
import { Flag } from '@/components/flag';
import { MatchdayChip, MatchdayStripe } from '@/components/matchday-marker';
import { useDisplayTimeZone } from '@/hooks/use-timezone';
import { cn } from '@/lib/utils';
import type {
    BracketFixture,
    BracketPhase,
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
        pointsAwarded: number | null;
    } | null;
}

function normalizeGroupFixture(
    fixture: GroupFixture,
    groupName: string,
): NormalizedMatch {
    return {
        fixtureId: fixture.fixture_id,
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
function ScheduleRow({ match }: { match: NormalizedMatch }) {
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

    return (
        <div className="relative grid grid-cols-[1fr_auto_1fr] items-center gap-3 border-t border-border py-2.5 pl-3 first:border-t-0">
            <MatchdayStripe matchdayKey={match.matchdayKey} />
            <div className="min-w-0">
                <div className="flex items-center gap-1.5">
                    <MatchdayChip matchdayKey={match.matchdayKey} />
                    {match.kicksOffAt && (
                        <span
                            className={cn(
                                'font-display text-[11px] font-semibold whitespace-nowrap',
                                settled && 'text-muted-foreground',
                            )}
                        >
                            {formatMatchDate(match.kicksOffAt, tz)} ·{' '}
                            {formatMatchTime(match.kicksOffAt, tz)}
                        </span>
                    )}
                </div>
                <div className="truncate text-[11px] text-muted-foreground">
                    {match.context}
                    {match.venue ? ` · ${venueLabel(match.venue)}` : ''}
                </div>
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
                {match.pick &&
                match.pick.homeGoals !== null &&
                match.pick.awayGoals !== null ? (
                    <div className="text-[11px] text-muted-foreground">
                        You{' '}
                        <span className="font-semibold tabular-nums">
                            {match.pick.homeGoals}–{match.pick.awayGoals}
                        </span>
                    </div>
                ) : (
                    <div className="text-[11px] font-medium text-muted-foreground/70">
                        No prediction
                    </div>
                )}
            </div>

            <div className="justify-self-end">
                <PointsBadge points={match.pick?.pointsAwarded ?? null} />
            </div>
        </div>
    );
}

/** A card holding the flat rows of one section (a matchday, or a calendar day). */
function ScheduleSection({
    title,
    meta,
    matches,
}: {
    title: string;
    meta: string;
    matches: NormalizedMatch[];
}) {
    return (
        <div>
            <PhaseMeta title={title} meta={meta} />
            <div className="card-elevated rounded-3xl px-5 py-1">
                {matches.map((match) => (
                    <ScheduleRow key={match.fixtureId} match={match} />
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
}: {
    groups: GroupView[];
    bracket: BracketPhase[];
    matchdays: MatchdayDescriptor[];
}) {
    const tz = useDisplayTimeZone();
    const all = normalizeAll(groups, bracket);

    return (
        <div className="flex flex-col gap-10">
            {matchdays.map((matchday) => {
                const matches = all
                    .filter((match) => match.matchdayKey === matchday.key)
                    .sort((a, b) => kickoffMs(a) - kickoffMs(b));

                if (matches.length === 0) {
                    return null;
                }

                return (
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
                    />
                );
            })}
        </div>
    );
}

/** Every fixture in strict kickoff order, grouped under calendar-day headers in the viewer's zone. */
export function ScheduleView({
    groups,
    bracket,
}: {
    groups: GroupView[];
    bracket: BracketPhase[];
}) {
    const tz = useDisplayTimeZone();
    const sorted = normalizeAll(groups, bracket).sort(
        (a, b) => kickoffMs(a) - kickoffMs(b),
    );

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

    return (
        <div className="flex flex-col gap-10">
            {days.map((day) => (
                <ScheduleSection
                    key={day.header}
                    title={day.header}
                    meta={sectionMeta(day.matches.length, null)}
                    matches={day.matches}
                />
            ))}
        </div>
    );
}
