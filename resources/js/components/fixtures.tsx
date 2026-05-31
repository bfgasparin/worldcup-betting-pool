import type { ReactNode } from 'react';
import { Flag } from '@/components/flag';
import { useDisplayTimeZone } from '@/hooks/use-timezone';
import { cn } from '@/lib/utils';
import type {
    BracketFixture,
    GroupFixture,
    GroupTeam,
    TeamRef,
} from '@/types/games';

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
            {team.code ?? team.name}
        </span>
    );
}

/** A short glanceable token for an unresolved knockout slot ("Winner Group E" -> "WE"). */
function slotAbbrev(label: string | null): string {
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

function MatchRow({ fixture }: { fixture: GroupFixture }) {
    const tz = useDisplayTimeZone();

    return (
        <div className="grid grid-cols-[1fr_auto_1fr] items-center gap-3 border-t border-border py-2.5 first:border-t-0">
            <div className="min-w-0">
                {fixture.kicks_off_at && (
                    <div className="font-display text-[11px] font-semibold whitespace-nowrap">
                        {formatMatchDate(fixture.kicks_off_at, tz)} ·{' '}
                        {formatMatchTime(fixture.kicks_off_at, tz)}
                    </div>
                )}
                {fixture.venue && (
                    <div className="truncate text-[11px] text-muted-foreground">
                        {venueLabel(fixture.venue)}
                    </div>
                )}
            </div>

            <div className="grid grid-cols-[1fr_auto_1fr] items-center gap-2">
                <span className="flex min-w-0 items-center justify-end gap-1.5 text-sm font-bold">
                    <span className="truncate">{teamCode(fixture.home)}</span>
                    <Flag team={fixture.home} className="h-4 w-6" />
                </span>
                <span className="text-center font-display text-xs text-muted-foreground">
                    v
                </span>
                <span className="flex min-w-0 items-center gap-1.5 text-sm font-bold">
                    <Flag team={fixture.away} className="h-4 w-6" />
                    <span className="truncate">{teamCode(fixture.away)}</span>
                </span>
            </div>

            <div className="justify-self-end">
                {fixture.prediction ? (
                    <span className="inline-flex items-center gap-1.5 font-display text-sm font-semibold text-pitch-deep tabular-nums dark:text-primary">
                        <span className="size-1.5 rounded-full bg-primary" />
                        {fixture.prediction.home_goals}–
                        {fixture.prediction.away_goals}
                    </span>
                ) : (
                    <span className="text-sm font-semibold text-muted-foreground">
                        —
                    </span>
                )}
            </div>
        </div>
    );
}

export function GroupFixtureCard({
    name,
    teams,
    fixtures,
}: {
    name: string;
    teams: GroupTeam[];
    fixtures: GroupFixture[];
}) {
    return (
        <div className="card-elevated rounded-3xl p-5">
            <div className="mb-3 flex items-center justify-between gap-2">
                <div className="flex min-w-0 items-center gap-2.5">
                    <GroupBadge name={name} />
                    <h3 className="font-display text-base font-semibold whitespace-nowrap">
                        Group {name}
                    </h3>
                </div>
                <span className="text-[11px] font-bold tracking-wide text-muted-foreground uppercase">
                    {fixtures.length} matches
                </span>
            </div>

            <div className="flex flex-wrap gap-1.5 border-b border-border pb-3">
                {teams.map((team) => (
                    <TeamChip key={team.id} team={team} />
                ))}
            </div>

            <div className="mt-1">
                {fixtures.map((fixture) => (
                    <MatchRow key={fixture.match_number} fixture={fixture} />
                ))}
            </div>
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

export function KnockoutSlotCard({ fixture }: { fixture: BracketFixture }) {
    const tz = useDisplayTimeZone();

    return (
        <div className="card-elevated rounded-3xl p-5">
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
            <KnockoutSlot team={fixture.home} label={fixture.home_label} />
            <div className="my-1 flex items-center gap-2.5">
                <span className="h-px flex-1 bg-border" />
                <span className="font-display text-[11px] tracking-[0.1em] text-muted-foreground">
                    VS
                </span>
                <span className="h-px flex-1 bg-border" />
            </div>
            <KnockoutSlot team={fixture.away} label={fixture.away_label} />
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

export function FinalCard({ fixture }: { fixture: BracketFixture }) {
    const tz = useDisplayTimeZone();

    return (
        <div className="relative mx-auto max-w-xl overflow-hidden rounded-3xl border border-accent/30 bg-ink p-9 text-center text-white">
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
            <div className="flex [scrollbar-width:none] gap-2 overflow-x-auto [&::-webkit-scrollbar]:hidden">
                {phases.map((phase) => {
                    const on = phase.id === active;

                    return (
                        <button
                            key={phase.id}
                            type="button"
                            onClick={() => onSelect(phase.id)}
                            className={cn(
                                'flex shrink-0 items-center gap-2 rounded-full border-[1.5px] px-4 py-2 font-display text-sm font-semibold whitespace-nowrap transition-colors outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50',
                                on
                                    ? 'border-transparent bg-pitch-deep text-white'
                                    : 'border-transparent bg-secondary text-secondary-foreground hover:border-border',
                            )}
                        >
                            {phase.label}
                            <span
                                className={cn(
                                    'font-body text-[11px] font-bold',
                                    on
                                        ? 'text-white/70'
                                        : 'text-muted-foreground',
                                )}
                            >
                                {phase.count}
                            </span>
                        </button>
                    );
                })}
            </div>
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
