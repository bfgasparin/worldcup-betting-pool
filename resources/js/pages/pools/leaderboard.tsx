import { Head, router } from '@inertiajs/react';
import {
    ArrowDown,
    ArrowUp,
    ChevronLeft,
    ChevronRight,
    Crown,
    ListOrdered,
    Scale,
    TrendingDown,
    Trophy,
    Users,
} from 'lucide-react';
import { useState } from 'react';
import { formatMatchDate } from '@/components/fixtures';
import { LeaderboardStandings } from '@/components/leaderboard-standings';
import PlayerAvatar from '@/components/player-avatar';
import { PoolIdentity } from '@/components/pool-identity';
import { useDisplayTimeZone } from '@/hooks/use-timezone';
import { tiebreakRule } from '@/lib/leaderboards';
import { poolTitle } from '@/lib/pool-title';
import { cn } from '@/lib/utils';
import pools from '@/routes/pools';
import type { BreadcrumbItem } from '@/types/navigation';
import type {
    BoardRow,
    LeaderboardBoard,
    LeaderboardCategoryKey,
    LeaderboardMatchday,
    LeaderboardPageProps,
    MatchdayStat,
    RankMovement,
} from '@/types/pools';

/** Pill tabs for switching boards — mirrors the in-house PhaseTabs idiom. */
function BoardTabs({
    boards,
    active,
    onSelect,
}: {
    boards: LeaderboardBoard[];
    active: LeaderboardCategoryKey;
    onSelect: (key: LeaderboardCategoryKey) => void;
}) {
    return (
        <div className="flex [scrollbar-width:none] gap-2 overflow-x-auto [&::-webkit-scrollbar]:hidden">
            {boards.map((board) => {
                const on = board.key === active;

                return (
                    <button
                        key={board.key}
                        type="button"
                        onClick={() => onSelect(board.key)}
                        aria-pressed={on}
                        className={cn(
                            'shrink-0 rounded-full border-[1.5px] px-4 py-2 font-display text-sm font-semibold whitespace-nowrap transition-colors outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50',
                            on
                                ? 'border-transparent bg-pitch-deep text-white'
                                : 'border-transparent bg-secondary text-secondary-foreground hover:border-border',
                        )}
                    >
                        {board.label}
                    </button>
                );
            })}
        </div>
    );
}

/** Whether a matchday can be travelled to: it has begun (or is the current landing stop). */
function isTravellable(matchday: LeaderboardMatchday): boolean {
    return matchday.status !== 'upcoming' || matchday.is_current;
}

/**
 * The matchday timeline: prev/next steppers around a scrollable row of pills. Upcoming matchdays
 * are shown but disabled — you can only travel to ones that have begun.
 */
function MatchdaySelector({
    matchdays,
    selected,
    onSelect,
}: {
    matchdays: LeaderboardMatchday[];
    selected: string;
    onSelect: (key: string) => void;
}) {
    const travellable = matchdays.filter(isTravellable).map((m) => m.key);
    const position = travellable.indexOf(selected);
    const prev = position > 0 ? travellable[position - 1] : null;
    const next =
        position >= 0 && position < travellable.length - 1
            ? travellable[position + 1]
            : null;

    return (
        <div className="flex items-center gap-2">
            <StepButton direction="prev" target={prev} onSelect={onSelect} />
            <div
                className="flex flex-1 [scrollbar-width:none] gap-2 overflow-x-auto [&::-webkit-scrollbar]:hidden"
                role="tablist"
                aria-label="Matchday"
            >
                {matchdays.map((matchday) => {
                    const on = matchday.key === selected;
                    const enabled = isTravellable(matchday);

                    return (
                        <button
                            key={matchday.key}
                            type="button"
                            role="tab"
                            aria-selected={on}
                            disabled={!enabled}
                            onClick={() => onSelect(matchday.key)}
                            className={cn(
                                'shrink-0 rounded-full border-[1.5px] px-3.5 py-1.5 font-display text-xs font-semibold whitespace-nowrap transition-colors outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50',
                                on
                                    ? 'border-transparent bg-pitch-deep text-white'
                                    : enabled
                                      ? 'border-transparent bg-secondary text-secondary-foreground hover:border-border'
                                      : 'cursor-not-allowed border-dashed border-border/70 text-muted-foreground/50',
                            )}
                        >
                            {matchday.short_label}
                        </button>
                    );
                })}
            </div>
            <StepButton direction="next" target={next} onSelect={onSelect} />
        </div>
    );
}

function StepButton({
    direction,
    target,
    onSelect,
}: {
    direction: 'prev' | 'next';
    target: string | null;
    onSelect: (key: string) => void;
}) {
    const Icon = direction === 'prev' ? ChevronLeft : ChevronRight;

    return (
        <button
            type="button"
            disabled={target === null}
            onClick={() => target !== null && onSelect(target)}
            aria-label={
                direction === 'prev' ? 'Previous matchday' : 'Next matchday'
            }
            className="grid size-9 shrink-0 place-items-center rounded-full border border-border bg-card text-muted-foreground transition-colors hover:text-foreground disabled:cursor-not-allowed disabled:opacity-40"
        >
            <Icon className="size-4" />
        </button>
    );
}

/** A small avatar bubble: the player's photo, or their initials on a brand gradient. */
function AvatarBubble({ stat }: { stat: MatchdayStat }) {
    if (stat.avatar) {
        return (
            <img
                src={stat.avatar}
                alt=""
                className="size-9 shrink-0 rounded-full object-cover"
            />
        );
    }

    return (
        <span
            className={cn(
                'grid size-9 shrink-0 place-items-center rounded-full font-display text-xs font-semibold text-white',
                stat.is_me ? 'bg-pitch-deep' : 'bg-brand-gradient',
            )}
        >
            {stat.initials}
        </span>
    );
}

/** One of the three per-matchday cards (you earned / top earner / lowest earner). */
function MatchdayStatCard({
    title,
    icon: Icon,
    tone,
    stat,
    statLabel,
    showName,
}: {
    title: string;
    icon: typeof Crown;
    tone: 'gold' | 'green' | 'muted';
    stat: MatchdayStat | null;
    statLabel: string;
    showName: boolean;
}) {
    return (
        <div className="card-elevated rounded-2xl border border-border bg-card p-4">
            <div className="flex items-center gap-2 text-xs font-bold tracking-[0.12em] text-muted-foreground uppercase">
                <Icon
                    className={cn(
                        'size-4',
                        tone === 'gold' && 'text-accent',
                        tone === 'green' && 'text-primary',
                        tone === 'muted' && 'text-muted-foreground',
                    )}
                />
                {title}
            </div>

            {stat ? (
                <div className="mt-3 flex items-center gap-3">
                    <AvatarBubble stat={stat} />
                    <div className="min-w-0">
                        <div className="font-display text-2xl leading-none font-semibold text-foreground tabular-nums">
                            +{stat.value}
                        </div>
                        <div className="mt-1 truncate text-xs text-muted-foreground">
                            {showName ? stat.name : statLabel.toLowerCase()}
                        </div>
                    </div>
                </div>
            ) : (
                <p className="mt-3 text-sm text-muted-foreground">
                    Not started
                </p>
            )}
        </div>
    );
}

/**
 * A compact movement card (biggest climber / faller) — the player and how many places they moved on
 * the board this matchday. `stat.value` is the number of places.
 */
function MoverCard({
    title,
    direction,
    stat,
}: {
    title: string;
    direction: 'up' | 'down';
    stat: MatchdayStat | null;
}) {
    const up = direction === 'up';
    const Icon = up ? ArrowUp : ArrowDown;

    return (
        <div className="card-elevated rounded-2xl border border-border bg-card p-4">
            <div className="flex items-center gap-1.5 text-xs font-bold tracking-[0.12em] text-muted-foreground uppercase">
                <Icon
                    className={cn(
                        'size-4',
                        up ? 'text-primary' : 'text-destructive',
                    )}
                />
                {title}
            </div>

            {stat ? (
                <div className="mt-3 flex items-center gap-2.5">
                    <AvatarBubble stat={stat} />
                    <div className="min-w-0">
                        <div className="truncate text-sm font-semibold text-foreground">
                            {stat.name}
                        </div>
                        <div
                            className={cn(
                                'mt-0.5 inline-flex items-center gap-0.5 font-display text-sm font-semibold tabular-nums',
                                up ? 'text-primary' : 'text-destructive',
                            )}
                        >
                            <Icon className="size-3.5" />
                            {stat.value} {stat.value === 1 ? 'place' : 'places'}
                        </div>
                    </div>
                </div>
            ) : (
                <p className="mt-3 text-sm text-muted-foreground">
                    No movement
                </p>
            )}
        </div>
    );
}

/** A compact date range for a matchday, in the viewer's zone (e.g. "Jun 24 – 27"). */
function matchdayDateRange(
    matchday: LeaderboardMatchday | undefined,
    tz: string,
): string | null {
    if (!matchday?.starts_at) {
        return null;
    }

    const start = formatMatchDate(matchday.starts_at, tz);

    if (!matchday.ends_at) {
        return start;
    }

    const end = formatMatchDate(matchday.ends_at, tz);

    return start === end ? start : `${start} – ${end}`;
}

/** A white movement indicator that stays legible on the branded personal card. */
function PersonalMovement({
    movement,
    delta,
}: {
    movement: RankMovement | null;
    delta: number | null;
}) {
    if (movement === null || movement === 'same') {
        return null;
    }

    if (movement === 'new') {
        return (
            <span className="rounded-full bg-white/15 px-2 py-0.5 text-[10px] font-bold tracking-wide uppercase">
                New
            </span>
        );
    }

    const up = movement === 'up';
    const Icon = up ? ArrowUp : ArrowDown;

    return (
        <span className="inline-flex items-center gap-0.5 rounded-full bg-white/15 px-2 py-0.5 font-display text-xs font-semibold tabular-nums">
            <Icon className="size-3.5" />
            {delta}
        </span>
    );
}

/**
 * The viewer's own standing — a prominent, branded card that reads as "about you", set apart from
 * the pool-wide cards. Shows the viewer's rank and movement, their board total, and what they earned
 * this matchday.
 */
function PersonalCard({
    row,
    matchdayValue,
    statLabel,
}: {
    row: BoardRow;
    matchdayValue: number | null;
    statLabel: string;
}) {
    const label = statLabel.toLowerCase();

    return (
        <div className="bg-brand-gradient shadow-glow relative flex flex-col overflow-hidden rounded-2xl p-5 text-white lg:w-72 lg:shrink-0">
            <div className="text-xs font-bold tracking-[0.12em] text-white/80 uppercase">
                Your standing
            </div>

            <div className="flex flex-1 items-center gap-4 py-4">
                <PlayerAvatar
                    name={row.name}
                    initials={row.initials}
                    src={row.avatar}
                    fallbackClassName="bg-white/15 text-white"
                    ringClassName="ring-2 ring-white/40"
                    className="size-14"
                />
                <div className="flex items-baseline gap-2">
                    <span className="font-display text-5xl leading-none font-semibold tabular-nums">
                        #{row.rank}
                    </span>
                    <PersonalMovement
                        movement={row.movement}
                        delta={row.movement_delta}
                    />
                </div>
            </div>

            <div className="grid grid-cols-2 gap-3 border-t border-white/15 pt-3">
                <div>
                    <div className="text-[11px] font-bold tracking-[0.1em] text-white/70 uppercase">
                        This matchday
                    </div>
                    <div className="font-display text-lg font-semibold tabular-nums">
                        {matchdayValue === null ? '—' : `+${matchdayValue}`}
                    </div>
                </div>
                <div>
                    <div className="truncate text-[11px] font-bold tracking-[0.1em] text-white/70 uppercase">
                        Total {label}
                    </div>
                    <div className="font-display text-lg font-semibold tabular-nums">
                        {row.primary_value === null
                            ? '—'
                            : row.primary_value.toLocaleString()}
                    </div>
                </div>
            </div>
        </div>
    );
}

export default function Leaderboard({
    pool,
    boards,
    active_board,
    matchdays,
    selected_matchday,
}: LeaderboardPageProps) {
    const tz = useDisplayTimeZone();
    const [active, setActive] = useState<LeaderboardCategoryKey>(
        active_board ?? boards[0]?.key ?? 'overall',
    );
    const board = boards.find((b) => b.key === active) ?? boards[0];
    const participants = board?.rows.length ?? 0;
    const isPaid = pool.pricing.entry_price > 0;

    const selected = matchdays.find((m) => m.key === selected_matchday);
    const dateRange = matchdayDateRange(selected, tz);
    const isCurrent = selected?.is_current ?? true;

    // Inline prize amounts: only on the prize (Overall) board of a paid pool with a funded pot —
    // and only on the live (current) view, since prizes describe the live race, not history.
    const showPrizes =
        (board?.awards_prizes ?? false) &&
        isPaid &&
        pool.pricing.net > 0 &&
        isCurrent;

    const goToMatchday = (key: string) => {
        if (key === selected_matchday) {
            return;
        }

        router.get(
            pools.leaderboard(pool.slug, { query: { matchday: key } }).url,
            {},
            {
                only: ['boards', 'matchdays', 'selected_matchday', 'pool'],
                preserveState: true,
                preserveScroll: true,
            },
        );
    };

    const cards = board?.matchday_stats;
    const myRow = board?.rows.find((row) => row.is_me) ?? null;

    return (
        <>
            <Head title={poolTitle(pool.source, pool.name, 'Leaderboards')} />
            <div className="flex h-full flex-1 flex-col gap-6 p-4 sm:p-6 lg:p-8">
                <header className="hero relative overflow-hidden rounded-3xl border border-border p-8">
                    <div className="hero-lines" />
                    <div className="relative flex flex-col gap-3">
                        <span className="inline-flex items-center gap-2 text-xs font-bold tracking-[0.14em] text-muted-foreground uppercase">
                            <ListOrdered className="size-4 text-primary" />
                            Leaderboards
                        </span>
                        <div className="flex flex-wrap items-center gap-3">
                            <h1 className="text-4xl font-semibold tracking-tight text-foreground sm:text-5xl">
                                {board?.label ?? 'Leaderboards'}
                            </h1>
                            {isPaid && board?.awards_prizes && (
                                <span className="bg-gold-gradient inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-bold tracking-wide text-[#3a2600] uppercase shadow-[var(--sh-sm)]">
                                    <Trophy className="size-3.5" />
                                    Prize board
                                </span>
                            )}
                        </div>
                        <p className="inline-flex items-center gap-2 text-sm text-muted-foreground">
                            <Users className="size-4" />
                            {participants}{' '}
                            {participants === 1 ? 'player' : 'players'}
                        </p>
                        <PoolIdentity
                            source={pool.source}
                            name={pool.name}
                            scoringLabel={pool.scoring_label}
                            accent={pool.accent}
                            className="mt-1"
                        />
                    </div>
                </header>

                <BoardTabs
                    boards={boards}
                    active={active}
                    onSelect={setActive}
                />

                {matchdays.length > 0 && (
                    <MatchdaySelector
                        matchdays={matchdays}
                        selected={selected_matchday}
                        onSelect={goToMatchday}
                    />
                )}

                {board && (
                    <div className="flex flex-col items-baseline justify-between gap-1 sm:flex-row sm:gap-3">
                        <p className="font-display text-sm font-semibold text-foreground">
                            {selected?.label ?? 'This matchday'}
                            {dateRange && (
                                <span className="ml-1.5 font-sans font-normal text-muted-foreground">
                                    · {dateRange}
                                </span>
                            )}
                        </p>
                    </div>
                )}

                {board && cards && (
                    <div className="flex flex-col gap-4 lg:flex-row lg:items-stretch">
                        {myRow && (
                            <PersonalCard
                                row={myRow}
                                matchdayValue={cards.you?.value ?? null}
                                statLabel={board.primary_stat_label}
                            />
                        )}
                        <div className="grid flex-1 grid-cols-2 gap-3">
                            <MatchdayStatCard
                                title="Top of the matchday"
                                icon={Crown}
                                tone="gold"
                                stat={cards.top}
                                statLabel={board.primary_stat_label}
                                showName
                            />
                            <MatchdayStatCard
                                title="Quietest matchday"
                                icon={TrendingDown}
                                tone="muted"
                                stat={cards.lowest}
                                statLabel={board.primary_stat_label}
                                showName
                            />
                            <MoverCard
                                title="Climber"
                                direction="up"
                                stat={cards.biggest_climber}
                            />
                            <MoverCard
                                title="Faller"
                                direction="down"
                                stat={cards.biggest_faller}
                            />
                        </div>
                    </div>
                )}

                {board && (
                    <div className="flex flex-col gap-3">
                        <div className="flex flex-col gap-1.5">
                            <p className="text-sm font-semibold text-foreground">
                                {isCurrent
                                    ? 'Live standings'
                                    : `Standings at the end of ${selected?.label ?? 'this matchday'}`}
                            </p>
                            <p className="text-sm text-muted-foreground">
                                {board.description}
                            </p>
                            <p className="inline-flex items-start gap-1.5 text-xs font-medium text-muted-foreground">
                                <Scale className="mt-px size-3.5 shrink-0 text-primary/70" />
                                {tiebreakRule(board)}
                            </p>
                        </div>

                        {!board.has_scores && participants > 0 && (
                            <div className="flex items-start gap-4 rounded-3xl border border-accent/30 bg-accent/[0.08] p-5">
                                <div className="app-icon app-icon--gold grid size-11 shrink-0 place-items-center rounded-2xl">
                                    <Trophy className="size-5 text-[#3a2600]" />
                                </div>
                                <div>
                                    <p className="font-display text-base font-semibold">
                                        The table is warming up
                                    </p>
                                    <p className="mt-1 text-sm text-muted-foreground">
                                        Standings land as match results come in
                                        — predictions lock at kick-off. Here's
                                        everyone playing so far.
                                    </p>
                                </div>
                            </div>
                        )}

                        {participants > 0 ? (
                            <LeaderboardStandings
                                key={`${active}|${selected_matchday}`}
                                rows={board.rows}
                                board={board}
                                showPrizes={showPrizes}
                                pricing={pool.pricing}
                            />
                        ) : (
                            <div className="flex min-h-44 flex-col items-center justify-center gap-2 rounded-3xl border border-dashed border-border p-8 text-center">
                                <Users className="size-6 text-muted-foreground" />
                                <p className="font-display font-semibold">
                                    No players yet
                                </p>
                                <p className="max-w-sm text-sm text-muted-foreground">
                                    Predictions put you on the board — be the
                                    first to lock in your scorelines.
                                </p>
                            </div>
                        )}
                    </div>
                )}
            </div>
        </>
    );
}

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Pools', href: pools.index() }];

Leaderboard.layout = { breadcrumbs };
