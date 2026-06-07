import { ChevronDown, Clock } from 'lucide-react';
import type { ReactNode } from 'react';
import { useState } from 'react';
import { Flag } from '@/components/flag';
import { MatchdayChip, MatchdayStripe } from '@/components/matchday-marker';
import { StandingsTable } from '@/components/standings-table';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import { SegmentedTabs } from '@/components/ui/segmented-tabs';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { useDisplayTimeZone } from '@/hooks/use-timezone';
import { cn } from '@/lib/utils';
import type {
    BracketFixture,
    GroupFixture,
    GroupTeam,
    StandingRow,
    TeamRef,
} from '@/types/pools';

/* ------------------------------------------------------------------ dates */

/** All formatters render in the viewer's timezone (`tz` from useDisplayTimeZone). */
function fmt(
    iso: string,
    options: Intl.DateTimeFormatOptions,
    tz: string,
): string {
    return new Intl.DateTimeFormat('en-US', {
        ...options,
        timeZone: tz,
    }).format(new Date(iso));
}

export function formatMatchDate(iso: string, tz: string): string {
    return fmt(iso, { month: 'short', day: 'numeric' }, tz);
}

export function formatMatchTime(iso: string, tz: string): string {
    // 24-hour, no timezone label (e.g. "16:00") — compact and unambiguous in the viewer's zone.
    return fmt(iso, { hour: '2-digit', minute: '2-digit', hour12: false }, tz);
}

export function formatLongDate(iso: string, tz: string): string {
    return fmt(
        iso,
        { weekday: 'long', month: 'long', day: 'numeric', year: 'numeric' },
        tz,
    );
}

/** A date header for the chronological schedule, e.g. "Thu · Jun 11", in the viewer's zone. */
export function formatScheduleDateHeader(iso: string, tz: string): string {
    return fmt(
        iso,
        { weekday: 'short', month: 'short', day: 'numeric' },
        tz,
    ).replace(',', ' ·');
}

/** A compact range across a phase's kick-offs in the viewer's zone, e.g. "Jun 11 – 27". */
export function phaseDateRange(
    items: { kicks_off_at: string | null }[],
    tz: string,
): string | null {
    const timed = items
        .filter((i): i is { kicks_off_at: string } => Boolean(i.kicks_off_at))
        .sort(
            (a, b) =>
                new Date(a.kicks_off_at).getTime() -
                new Date(b.kicks_off_at).getTime(),
        );

    if (timed.length === 0) {
        return null;
    }

    const first = timed[0].kicks_off_at;
    const last = timed[timed.length - 1].kicks_off_at;
    const firstDate = formatMatchDate(first, tz);
    const lastDate = formatMatchDate(last, tz);

    if (firstDate === lastDate) {
        return firstDate;
    }

    const sameMonth =
        fmt(first, { month: 'short' }, tz) ===
        fmt(last, { month: 'short' }, tz);

    return sameMonth
        ? `${firstDate} – ${fmt(last, { day: 'numeric' }, tz)}`
        : `${firstDate} – ${lastDate}`;
}

/* ------------------------------------------------------------------ atoms */

export function GroupBadge({
    name,
    className,
}: {
    name: string;
    className?: string;
}) {
    return (
        <span
            className={cn(
                'bg-brand-gradient grid size-8 shrink-0 place-items-center rounded-lg font-display text-sm font-semibold text-white',
                className,
            )}
        >
            {name}
        </span>
    );
}

export function TeamChip({ team }: { team: GroupTeam | TeamRef }) {
    return (
        <span className="inline-flex items-center gap-1.5 rounded-full bg-secondary px-2.5 py-1 text-xs font-bold text-foreground">
            <Flag team={team} className="h-3.5 w-5" />
            {team.name}
        </span>
    );
}

/** A short glanceable token for an unresolved knockout slot ("Winner Group E" -> "WE"). */
export function slotAbbrev(label: string | null): string {
    if (!label) {
        return '?';
    }

    const winnerGroup = label.match(/^winners?\s+group\s+([a-l])/i);

    if (winnerGroup) {
        return `W${winnerGroup[1].toUpperCase()}`;
    }

    const runnerUpGroup = label.match(/^runners?[-\s]?up\s+group\s+([a-l])/i);

    if (runnerUpGroup) {
        return `R${runnerUpGroup[1].toUpperCase()}`;
    }

    if (/3rd|third/i.test(label)) {
        return '3rd';
    }

    const fed = label.match(/(\d+)/);

    if (/^winner|^loser/i.test(label) && fed) {
        return `${label[0].toUpperCase()}${fed[1]}`;
    }

    return label.slice(0, 3).toUpperCase();
}

/* --------------------------------------------------------- group fixtures */

function teamCode(
    team: TeamRef | null,
    fallback: string | null = null,
): string {
    return team?.code ?? team?.name ?? fallback ?? 'TBD';
}

/** Display label for a venue — drops the generic " Stadium" suffix (e.g. "Mexico City"). */
function venueLabel(venue: string): string {
    return venue.replace(/\s+Stadium$/, '');
}

type GoalOutcome = 'home' | 'away' | 'draw';

function goalOutcome(home: number, away: number): GoalOutcome {
    if (home > away) {
        return 'home';
    }

    if (away > home) {
        return 'away';
    }

    return 'draw';
}

/**
 * The points a settled match earned: a green pill for a positive haul, coral for a scored zero,
 * and a muted dash when there was no prediction to score (mirrors the design's result badges).
 */
export function PointsBadge({ points }: { points: number | null }) {
    if (points === null) {
        return (
            <span className="inline-flex items-center rounded-full bg-secondary px-2.5 py-1 font-display text-xs font-semibold text-muted-foreground">
                —
            </span>
        );
    }

    const earned = points > 0;

    return (
        <span
            className={cn(
                'inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 font-display text-xs font-semibold whitespace-nowrap tabular-nums',
                earned
                    ? 'bg-primary/15 text-pitch-deep dark:text-primary'
                    : 'bg-destructive/10 text-destructive',
            )}
        >
            <span className="size-1.5 rounded-full bg-current" />
            {earned ? `+${points}` : '0'}
        </span>
    );
}

function MatchRow({
    fixture,
    showTimes,
}: {
    fixture: GroupFixture;
    showTimes: boolean;
}) {
    const tz = useDisplayTimeZone();
    const { home_goals: homeGoals, away_goals: awayGoals } = fixture;
    const settled = homeGoals !== null && awayGoals !== null;
    const outcome = settled ? goalOutcome(homeGoals, awayGoals) : null;

    // Whichever state, the match-up sits in the centre with the viewer's pick directly beneath it,
    // and the right column is reserved for the points badge (a muted dash until the match is scored).
    const homeClass = !settled
        ? 'font-bold'
        : outcome === 'home'
          ? 'font-extrabold text-foreground'
          : 'font-semibold text-muted-foreground';
    const awayClass = !settled
        ? 'font-bold'
        : outcome === 'away'
          ? 'font-extrabold text-foreground'
          : 'font-semibold text-muted-foreground';

    return (
        <div className="relative border-t border-border py-2.5 pl-3 first:border-t-0">
            <MatchdayStripe matchdayKey={fixture.matchday_key} />

            <div className="grid grid-cols-[1fr_auto_1fr] items-center gap-3">
                <div className="min-w-0">
                    <MatchdayChip matchdayKey={fixture.matchday_key} />
                </div>

                <div className="flex min-w-0 flex-col items-center gap-0.5">
                    <div className="grid grid-cols-[1fr_auto_1fr] items-center gap-2">
                        <span
                            className={cn(
                                'flex min-w-0 items-center justify-end gap-1.5 text-sm',
                                homeClass,
                            )}
                        >
                            <span className="truncate">
                                {teamCode(fixture.home)}
                            </span>
                            <Flag team={fixture.home} className="h-4 w-6" />
                        </span>
                        {settled ? (
                            <span className="text-center font-display text-base font-semibold tabular-nums">
                                {homeGoals}–{awayGoals}
                            </span>
                        ) : (
                            <span className="text-center font-display text-xs text-muted-foreground">
                                v
                            </span>
                        )}
                        <span
                            className={cn(
                                'flex min-w-0 items-center gap-1.5 text-sm',
                                awayClass,
                            )}
                        >
                            <Flag team={fixture.away} className="h-4 w-6" />
                            <span className="truncate">
                                {teamCode(fixture.away)}
                            </span>
                        </span>
                    </div>
                    {fixture.prediction ? (
                        <div className="text-[11px] text-muted-foreground">
                            You{' '}
                            <span className="font-semibold tabular-nums">
                                {fixture.prediction.home_goals}–
                                {fixture.prediction.away_goals}
                            </span>
                        </div>
                    ) : (
                        <div className="text-[11px] font-medium text-muted-foreground/70">
                            No prediction
                        </div>
                    )}
                </div>

                <div className="justify-self-end">
                    <PointsBadge
                        points={fixture.prediction?.points_awarded ?? null}
                    />
                </div>
            </div>

            {(fixture.kicks_off_at || fixture.venue) && (
                <div
                    className={cn(
                        'mt-1 truncate text-[11px] text-muted-foreground',
                        showTimes ? 'block' : 'hidden',
                    )}
                >
                    {fixture.kicks_off_at && (
                        <>
                            {formatMatchDate(fixture.kicks_off_at, tz)} ·{' '}
                            {formatMatchTime(fixture.kicks_off_at, tz)}
                        </>
                    )}
                    {fixture.kicks_off_at && fixture.venue ? ' · ' : ''}
                    {fixture.venue ? venueLabel(fixture.venue) : ''}
                </div>
            )}
        </div>
    );
}

/**
 * The standings shown inside a group card. With only official standings it renders a single
 * table; when the viewer's predicted standings are also supplied it adds an Official |
 * Predicted toggle so the two can be compared (and a "no prediction" note when the viewer has
 * not predicted this group).
 */
function StandingsPanel({
    official,
    predicted,
}: {
    official: StandingRow[];
    /** Undefined = no comparison; null = comparison offered but nothing predicted yet. */
    predicted?: StandingRow[] | null;
}) {
    const [view, setView] = useState<'official' | 'predicted'>('official');

    if (predicted === undefined) {
        return <StandingsTable standings={official} />;
    }

    return (
        <div className="flex flex-col gap-3">
            <ToggleGroup
                type="single"
                variant="outline"
                size="sm"
                value={view}
                onValueChange={(next) => {
                    if (next === 'official' || next === 'predicted') {
                        setView(next);
                    }
                }}
                className="self-center"
            >
                <ToggleGroupItem value="official" className="px-4 text-xs">
                    Official
                </ToggleGroupItem>
                <ToggleGroupItem value="predicted" className="px-4 text-xs">
                    Predicted
                </ToggleGroupItem>
            </ToggleGroup>

            {view === 'official' ? (
                <StandingsTable standings={official} />
            ) : predicted && predicted.length > 0 ? (
                <StandingsTable standings={predicted} />
            ) : (
                <p className="py-4 text-center text-sm text-muted-foreground">
                    No prediction yet for this group.
                </p>
            )}
        </div>
    );
}

/** A compact per-card toggle that reveals each row's kickoff date · venue. */
export function ShowTimesToggle({
    open,
    onToggle,
}: {
    open: boolean;
    onToggle: () => void;
}) {
    return (
        <button
            type="button"
            onClick={onToggle}
            aria-expanded={open}
            aria-label="Show kickoff times"
            className="inline-flex shrink-0 items-center gap-0.5 rounded-full p-1 text-muted-foreground transition-colors outline-none hover:text-foreground focus-visible:text-foreground"
        >
            <Clock className="size-3.5" />
            <ChevronDown
                className={cn(
                    'size-3.5 transition-transform duration-200',
                    open && 'rotate-180',
                )}
            />
        </button>
    );
}

export function GroupFixtureCard({
    name,
    teams,
    fixtures,
    standings,
    predictedStandings,
}: {
    name: string;
    teams: GroupTeam[];
    fixtures: GroupFixture[];
    /** When provided, a collapsible "Standings" table is shown beneath the fixtures. */
    standings?: StandingRow[];
    /**
     * The viewer's predicted standings. When provided (even as null), the standings section
     * gains an Official | Predicted toggle.
     */
    predictedStandings?: StandingRow[] | null;
}) {
    const [showTimes, setShowTimes] = useState(false);

    return (
        <div className="card-elevated rounded-3xl p-5">
            <div className="mb-3 flex items-center justify-between gap-2">
                <div className="flex min-w-0 items-center gap-2.5">
                    <GroupBadge name={name} />
                    <h3 className="font-display text-base font-semibold whitespace-nowrap">
                        Group {name}
                    </h3>
                </div>
                <div className="flex items-center gap-1.5">
                    <span className="text-[11px] font-bold tracking-wide text-muted-foreground uppercase">
                        {fixtures.length} matches
                    </span>
                    <ShowTimesToggle
                        open={showTimes}
                        onToggle={() => setShowTimes((value) => !value)}
                    />
                </div>
            </div>

            <div className="flex flex-wrap gap-1.5 border-b border-border pb-3">
                {teams.map((team) => (
                    <TeamChip key={team.id} team={team} />
                ))}
            </div>

            <div className="mt-1">
                {fixtures.map((fixture) => (
                    <MatchRow
                        key={fixture.match_number}
                        fixture={fixture}
                        showTimes={showTimes}
                    />
                ))}
            </div>

            {standings && standings.length > 0 && (
                <Collapsible className="mt-2">
                    <CollapsibleTrigger className="flex w-full items-center justify-center gap-1.5 border-t border-border pt-3 font-display text-xs font-semibold tracking-wide text-muted-foreground uppercase transition-colors outline-none hover:text-foreground focus-visible:text-foreground [&[data-state=open]>svg]:rotate-180">
                        Standings
                        <ChevronDown className="size-4 transition-transform duration-200" />
                    </CollapsibleTrigger>
                    <CollapsibleContent className="pt-2">
                        <StandingsPanel
                            official={standings}
                            predicted={predictedStandings}
                        />
                    </CollapsibleContent>
                </Collapsible>
            )}
        </div>
    );
}

/* ------------------------------------------------------------ knockout */

function KnockoutSlot({
    team,
    label,
}: {
    team: TeamRef | null;
    label: string | null;
}) {
    if (team) {
        return (
            <div className="flex items-center gap-3 py-1.5">
                <Flag team={team} className="h-7 w-10 rounded-md" />
                <span className="truncate font-display text-sm font-semibold">
                    {team.name}
                </span>
            </div>
        );
    }

    return (
        <div className="flex items-center gap-3 py-1.5">
            <span className="grid size-10 shrink-0 place-items-center rounded-xl border-[1.5px] border-dashed border-border bg-secondary font-display text-xs font-semibold text-muted-foreground">
                {slotAbbrev(label)}
            </span>
            <span className="text-sm font-semibold text-muted-foreground">
                {label ?? 'To be decided'}
            </span>
        </div>
    );
}

function AdvanceChip({ tone = 'light' }: { tone?: 'light' | 'dark' }) {
    return (
        <span
            className={cn(
                'font-body inline-flex shrink-0 items-center rounded-full px-2 py-0.5 text-[9.5px] font-bold tracking-wide uppercase',
                tone === 'dark'
                    ? 'bg-gold/20 text-gold'
                    : 'bg-primary/15 text-pitch-deep dark:text-primary',
            )}
        >
            Advances
        </span>
    );
}

/** The team a player picked to advance on a drawn knockout pick — flag, code and an "Advances" chip. */
export function PickAdvancer({
    team,
    tone = 'light',
}: {
    team: TeamRef;
    tone?: 'light' | 'dark';
}) {
    return (
        <span
            className={cn(
                'flex min-w-0 items-center gap-1.5 font-semibold',
                tone === 'dark' ? 'text-white' : 'text-foreground',
            )}
        >
            <Flag team={team} className="h-3.5 w-5" />
            <span className="truncate">{teamCode(team)}</span>
            <AdvanceChip tone={tone} />
        </span>
    );
}

function SettledKnockoutTeam({
    team,
    label,
    goals,
    advancing,
}: {
    team: TeamRef | null;
    label: string | null;
    goals: number;
    advancing: boolean;
}) {
    return (
        <div className="flex items-center justify-between gap-3 py-2">
            <span
                className={cn(
                    'flex min-w-0 items-center gap-2.5 text-sm',
                    advancing
                        ? 'font-bold text-foreground'
                        : 'font-semibold text-muted-foreground',
                )}
            >
                <Flag team={team} className="h-7 w-10 rounded-md" />
                <span className="truncate">{team?.name ?? label}</span>
                {advancing && <AdvanceChip />}
            </span>
            <span
                className={cn(
                    'font-display text-lg font-semibold tabular-nums',
                    advancing ? 'text-foreground' : 'text-muted-foreground',
                )}
            >
                {goals}
            </span>
        </div>
    );
}

/**
 * The teams the viewer predicted for a knockout match, with the one they picked to advance
 * emphasised — so the pick can be compared against the official match-up above it. Tones adapt
 * to the light knockout card and the dark final card.
 */
function PredictedMatchup({
    prediction,
    tone = 'light',
    inline = false,
}: {
    prediction: NonNullable<BracketFixture['prediction']>;
    tone?: 'light' | 'dark';
    /** Render at natural width on one line (for a footer beside its label) instead of a full grid. */
    inline?: boolean;
}) {
    const advHome =
        prediction.advancing_team_id != null &&
        prediction.advancing_team_id === prediction.predicted_home?.id;
    const advAway =
        prediction.advancing_team_id != null &&
        prediction.advancing_team_id === prediction.predicted_away?.id;

    // On a draw the score alone doesn't reveal who the viewer picked to advance (extra time or
    // penalties), so flag the chosen side explicitly. A decisive score speaks for itself.
    const isDraw =
        prediction.home_goals != null &&
        prediction.away_goals != null &&
        prediction.home_goals === prediction.away_goals;

    const advanced =
        tone === 'dark' ? 'font-bold text-gold' : 'font-bold text-foreground';
    const muted = tone === 'dark' ? 'text-white/60' : 'text-muted-foreground';
    const score = tone === 'dark' ? 'text-white/70' : 'text-muted-foreground';

    return (
        <div
            className={cn(
                'items-center text-xs',
                inline
                    ? 'flex min-w-0 justify-center gap-1.5'
                    : 'mt-1.5 grid grid-cols-[1fr_auto_1fr] gap-2',
            )}
        >
            <span
                className={cn(
                    'flex min-w-0 items-center justify-end gap-1.5',
                    advHome ? advanced : muted,
                )}
            >
                <span className="truncate">
                    {teamCode(prediction.predicted_home)}
                </span>
                <Flag team={prediction.predicted_home} className="h-3.5 w-5" />
                {isDraw && advHome && <AdvanceChip tone={tone} />}
            </span>
            <span className={cn('font-display tabular-nums', score)}>
                {prediction.home_goals ?? '–'}–{prediction.away_goals ?? '–'}
            </span>
            <span
                className={cn(
                    'flex min-w-0 items-center gap-1.5',
                    advAway ? advanced : muted,
                )}
            >
                {isDraw && advAway && <AdvanceChip tone={tone} />}
                <Flag team={prediction.predicted_away} className="h-3.5 w-5" />
                <span className="truncate">
                    {teamCode(prediction.predicted_away)}
                </span>
            </span>
        </div>
    );
}

/**
 * The knockout-card footer: the viewer's pick and, once the match is scored, the points it earned.
 * Set `showPoints` to false for an unplayed match, where there is no score yet — the footer then
 * just previews the match-up the player called.
 */
function PredictionFoot({
    prediction,
    home,
    away,
    showPoints = true,
}: {
    prediction: BracketFixture['prediction'];
    home: TeamRef | null;
    away: TeamRef | null;
    showPoints?: boolean;
}) {
    const hasTeams = prediction != null && prediction.predicted_home != null;
    const hasPick =
        prediction != null &&
        prediction.home_goals !== null &&
        prediction.away_goals !== null;
    // On a draw the score alone doesn't say who the player put through (extra time / penalties).
    // Phased pools carry no predicted teams, so resolve the advancing id against the real match-up.
    const drawAdvancer =
        prediction != null &&
        prediction.home_goals !== null &&
        prediction.away_goals !== null &&
        prediction.home_goals === prediction.away_goals &&
        prediction.advancing_team_id != null
            ? prediction.advancing_team_id === home?.id
                ? home
                : prediction.advancing_team_id === away?.id
                  ? away
                  : null
            : null;

    if (hasTeams) {
        return (
            <div className="mt-3 flex items-center justify-between gap-2 border-t border-border pt-3">
                <div className="flex min-w-0 items-center gap-2">
                    <span className="shrink-0 text-[11px] font-medium text-muted-foreground">
                        Your pick
                    </span>
                    <PredictedMatchup prediction={prediction} inline />
                </div>
                {showPoints && (
                    <PointsBadge points={prediction.points_awarded ?? null} />
                )}
            </div>
        );
    }

    return (
        <div className="mt-3 flex items-center justify-between gap-2 border-t border-border pt-3">
            <span className="flex min-w-0 flex-wrap items-center gap-x-2 gap-y-1 text-xs font-medium text-muted-foreground">
                {hasPick ? (
                    <>
                        <span className="flex items-baseline gap-2">
                            Your pick
                            <b className="font-display text-foreground">
                                {prediction.home_goals}–{prediction.away_goals}
                            </b>
                        </span>
                        {drawAdvancer && <PickAdvancer team={drawAdvancer} />}
                    </>
                ) : (
                    'No prediction'
                )}
            </span>
            <PointsBadge points={prediction?.points_awarded ?? null} />
        </div>
    );
}

function KnockoutCardHeader({ fixture }: { fixture: BracketFixture }) {
    const tz = useDisplayTimeZone();

    return (
        <div className="mb-2 flex items-start justify-between gap-2">
            <span className="font-display text-xs font-semibold text-muted-foreground">
                Match {fixture.match_number}
            </span>
            {fixture.kicks_off_at && (
                <span className="text-right text-[11px] font-semibold text-muted-foreground">
                    <span className="font-bold tracking-wide uppercase">
                        {formatMatchDate(fixture.kicks_off_at, tz)} ·{' '}
                        {formatMatchTime(fixture.kicks_off_at, tz)}
                    </span>
                    {fixture.venue && (
                        <span className="block font-medium normal-case">
                            {venueLabel(fixture.venue)}
                        </span>
                    )}
                </span>
            )}
        </div>
    );
}

export function KnockoutSlotCard({ fixture }: { fixture: BracketFixture }) {
    const { home_goals: homeGoals, away_goals: awayGoals } = fixture;

    if (homeGoals !== null && awayGoals !== null) {
        const homeAdvances =
            fixture.winner_team_id != null &&
            fixture.winner_team_id === fixture.home?.id;
        const awayAdvances =
            fixture.winner_team_id != null &&
            fixture.winner_team_id === fixture.away?.id;
        const penalties =
            fixture.home_penalties !== null && fixture.away_penalties !== null;

        return (
            <div className="card-elevated rounded-3xl p-5">
                <KnockoutCardHeader fixture={fixture} />
                <SettledKnockoutTeam
                    team={fixture.home}
                    label={fixture.home_label}
                    goals={homeGoals}
                    advancing={homeAdvances}
                />
                <SettledKnockoutTeam
                    team={fixture.away}
                    label={fixture.away_label}
                    goals={awayGoals}
                    advancing={awayAdvances}
                />
                {penalties && (
                    <div className="mt-1 text-[11px] font-semibold text-muted-foreground">
                        Penalties {fixture.home_penalties}–
                        {fixture.away_penalties}
                    </div>
                )}
                <PredictionFoot
                    prediction={fixture.prediction}
                    home={fixture.home}
                    away={fixture.away}
                />
            </div>
        );
    }

    return (
        <div className="card-elevated rounded-3xl p-5">
            <KnockoutCardHeader fixture={fixture} />
            <KnockoutSlot team={fixture.home} label={fixture.home_label} />
            <div className="my-1 flex items-center gap-2.5">
                <span className="h-px flex-1 bg-border" />
                <span className="font-display text-[11px] tracking-[0.1em] text-muted-foreground">
                    VS
                </span>
                <span className="h-px flex-1 bg-border" />
            </div>
            <KnockoutSlot team={fixture.away} label={fixture.away_label} />
            {/* Upfront-bracket tournaments: preview the player's pick before the match-up is set. */}
            {fixture.prediction?.predicted_home != null && (
                <PredictionFoot
                    prediction={fixture.prediction}
                    home={fixture.home}
                    away={fixture.away}
                    showPoints={false}
                />
            )}
        </div>
    );
}

/* ------------------------------------------------------------------ final */

function FinalSlot({
    team,
    label,
}: {
    team: TeamRef | null;
    label: string | null;
}) {
    return (
        <div className="flex flex-col items-center gap-2">
            {team ? (
                <Flag
                    team={team}
                    className="h-12 w-16 rounded-xl ring-white/20"
                />
            ) : (
                <span className="grid size-14 place-items-center rounded-2xl border-[1.5px] border-dashed border-white/25 bg-white/[0.08] font-display text-base font-semibold text-white">
                    {slotAbbrev(label)}
                </span>
            )}
            <small className="font-medium text-white/60">
                {team ? team.name : label}
            </small>
        </div>
    );
}

function FinalResultTeam({
    team,
    label,
    champion,
}: {
    team: TeamRef | null;
    label: string | null;
    champion: boolean;
}) {
    return (
        <div
            className={cn(
                'flex flex-col items-center gap-2',
                champion ? 'opacity-100' : 'opacity-60',
            )}
        >
            {team ? (
                <Flag
                    team={team}
                    className="h-12 w-16 rounded-xl ring-white/20"
                />
            ) : (
                <span className="grid size-14 place-items-center rounded-2xl border-[1.5px] border-dashed border-white/25 bg-white/[0.08] font-display text-base font-semibold text-white">
                    {slotAbbrev(label)}
                </span>
            )}
            <small
                className={cn(
                    'font-display font-semibold',
                    champion ? 'text-gold' : 'text-white/70',
                )}
            >
                {team?.name ?? label}
            </small>
        </div>
    );
}

/** The points line for the dark final card — gold for a haul, red for a scored zero. */
function FinalPoints({ points }: { points: number | null }) {
    if (points === null) {
        return <span className="text-white/40">—</span>;
    }

    return (
        <span
            className={cn(
                'font-display font-semibold tabular-nums',
                points > 0 ? 'text-gold' : 'text-red-300',
            )}
        >
            {points > 0 ? `+${points}` : '0'} pts
        </span>
    );
}

export function FinalCard({ fixture }: { fixture: BracketFixture }) {
    const tz = useDisplayTimeZone();
    const { home_goals: homeGoals, away_goals: awayGoals } = fixture;

    if (homeGoals !== null && awayGoals !== null) {
        const homeChampion =
            fixture.winner_team_id != null &&
            fixture.winner_team_id === fixture.home?.id;
        const champion = homeChampion ? fixture.home : fixture.away;
        const penalties =
            fixture.home_penalties !== null && fixture.away_penalties !== null;
        const pick = fixture.prediction;
        const hasPick =
            pick != null &&
            pick.home_goals !== null &&
            pick.away_goals !== null;
        const hasTeams = pick != null && pick.predicted_home != null;
        // On a draw, the score alone doesn't reveal the champion the player picked — resolve the id.
        const pickedChampion =
            pick != null &&
            pick.home_goals !== null &&
            pick.away_goals !== null &&
            pick.home_goals === pick.away_goals &&
            pick.advancing_team_id != null
                ? pick.advancing_team_id === fixture.home?.id
                    ? fixture.home
                    : pick.advancing_team_id === fixture.away?.id
                      ? fixture.away
                      : null
                : null;

        return (
            <div className="relative mx-auto max-w-xl overflow-hidden rounded-3xl border border-accent/30 bg-ink p-6 text-center text-white sm:p-9">
                <div className="pointer-events-none absolute -top-20 left-1/2 size-72 -translate-x-1/2 rounded-full bg-gold opacity-20 blur-[110px]" />
                <div className="relative">
                    <div className="text-4xl">🏆</div>
                    <h3 className="mt-2 font-display text-xs font-bold tracking-[0.18em] text-gold uppercase">
                        Champions · Match {fixture.match_number}
                    </h3>
                    {fixture.kicks_off_at && (
                        <div className="mt-1 text-sm font-semibold text-white/60">
                            {formatLongDate(fixture.kicks_off_at, tz)}
                        </div>
                    )}
                    <div className="mt-6 flex items-center justify-center gap-4 sm:gap-6">
                        <FinalResultTeam
                            team={fixture.home}
                            label={fixture.home_label}
                            champion={homeChampion}
                        />
                        <span className="font-display text-3xl font-semibold tabular-nums">
                            {homeGoals}–{awayGoals}
                        </span>
                        <FinalResultTeam
                            team={fixture.away}
                            label={fixture.away_label}
                            champion={!homeChampion}
                        />
                    </div>
                    {penalties && (
                        <div className="mt-1 text-xs font-semibold text-white/60">
                            Won on penalties ({fixture.home_penalties}–
                            {fixture.away_penalties})
                        </div>
                    )}
                    {champion && (
                        <div className="mt-3 font-display text-base font-semibold text-gold">
                            🏆 {champion.name} are World Champions
                        </div>
                    )}
                    {hasTeams ? (
                        <div className="mt-5 flex items-center justify-between gap-3 border-t border-white/10 pt-4 text-sm font-medium text-white/60">
                            <div className="flex min-w-0 items-center gap-2">
                                <span className="shrink-0">Your pick</span>
                                <PredictedMatchup
                                    prediction={pick}
                                    tone="dark"
                                    inline
                                />
                            </div>
                            <FinalPoints points={pick.points_awarded ?? null} />
                        </div>
                    ) : (
                        <div className="mt-5 flex flex-wrap items-center justify-between gap-x-3 gap-y-2 border-t border-white/10 pt-4 text-sm font-medium text-white/60">
                            <span className="flex min-w-0 flex-wrap items-center gap-x-3 gap-y-1">
                                {hasPick ? (
                                    <>
                                        <span className="flex items-baseline gap-2">
                                            Your pick
                                            <b className="font-display text-white">
                                                {pick.home_goals}–
                                                {pick.away_goals}
                                            </b>
                                        </span>
                                        {pickedChampion && (
                                            <PickAdvancer
                                                team={pickedChampion}
                                                tone="dark"
                                            />
                                        )}
                                    </>
                                ) : (
                                    'No prediction'
                                )}
                            </span>
                            <FinalPoints
                                points={pick?.points_awarded ?? null}
                            />
                        </div>
                    )}
                </div>
            </div>
        );
    }

    return (
        <div className="relative mx-auto max-w-xl overflow-hidden rounded-3xl border border-accent/30 bg-ink p-6 text-center text-white sm:p-9">
            <div className="pointer-events-none absolute -top-20 left-1/2 size-72 -translate-x-1/2 rounded-full bg-gold opacity-20 blur-[110px]" />
            <div className="relative">
                <div className="text-4xl">🏆</div>
                <h3 className="mt-2 font-display text-xs font-bold tracking-[0.18em] text-gold uppercase">
                    The Final · Match {fixture.match_number}
                </h3>
                {fixture.kicks_off_at && (
                    <div className="mt-1 text-sm font-semibold text-white/60">
                        {formatLongDate(fixture.kicks_off_at, tz)} ·{' '}
                        {formatMatchTime(fixture.kicks_off_at, tz)}
                        {fixture.venue ? ` · ${venueLabel(fixture.venue)}` : ''}
                    </div>
                )}
                <div className="mt-6 flex items-center justify-center gap-6">
                    <FinalSlot team={fixture.home} label={fixture.home_label} />
                    <span className="font-display text-xl text-white/50">
                        v
                    </span>
                    <FinalSlot team={fixture.away} label={fixture.away_label} />
                </div>
                {/* Upfront-bracket tournaments: preview the final the player called. */}
                {fixture.prediction?.predicted_home != null && (
                    <div className="mt-6 flex items-center justify-center gap-2 border-t border-white/10 pt-4 text-sm font-medium text-white/60">
                        <span className="shrink-0">Your pick</span>
                        <PredictedMatchup
                            prediction={fixture.prediction}
                            tone="dark"
                            inline
                        />
                    </div>
                )}
            </div>
        </div>
    );
}

/* ------------------------------------------------------------- phase tabs */

export interface Phase {
    id: string;
    label: string;
    count: number;
}

export function PhaseTabs({
    phases,
    active,
    onSelect,
}: {
    phases: Phase[];
    active: string;
    onSelect: (id: string) => void;
}) {
    return (
        <div className="sticky top-0 z-30 -mx-4 border-b border-border bg-background/85 px-4 py-3 backdrop-blur">
            <SegmentedTabs
                aria-label="Phases"
                value={active}
                onChange={onSelect}
                items={phases.map((phase) => ({
                    value: phase.id,
                    label: phase.label,
                    count: phase.count,
                    // A filter can empty a phase; dim and disable it so it can't be selected.
                    disabled: phase.count === 0,
                }))}
            />
        </div>
    );
}

/** A phase header: title + count/date-range meta. */
export function PhaseMeta({ title, meta }: { title: string; meta: ReactNode }) {
    return (
        <div className="mb-6 flex flex-wrap items-baseline justify-between gap-3">
            <h2 className="font-display text-2xl font-semibold tracking-tight sm:text-3xl">
                {title}
            </h2>
            <span className="text-sm font-semibold text-muted-foreground">
                {meta}
            </span>
        </div>
    );
}
