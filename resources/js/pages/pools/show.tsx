import { Head, Link, router } from '@inertiajs/react';
import {
    ArrowRight,
    CalendarDays,
    Check,
    ChevronDown,
    GitCompare,
    PencilLine,
    Plus,
    SlidersHorizontal,
    Trophy,
    Users,
} from 'lucide-react';
import { useState } from 'react';
import { AddPlayerDialog } from '@/components/add-player-dialog';
import { CompareDock } from '@/components/compare-dock';
import { CompareStrip } from '@/components/compare-strip';
import { CountdownBand } from '@/components/countdown-band';
import {
    FinalCard,
    GroupFixtureCard,
    KnockoutSlotCard,
    PhaseMeta,
    PhaseTabs,
    formatMatchDate,
    formatMatchTime,
    phaseDateRange,
} from '@/components/fixtures';
import type { Phase } from '@/components/fixtures';
import {
    CompareFinalCard,
    CompareGroupCard,
    CompareKnockoutCard,
} from '@/components/fixtures-compare';
import {
    FixturesEmptyState,
    matchesFixtureTimeFilter,
    MatchdayView,
    ScheduleView,
    timeFilterEmptyMessage,
} from '@/components/fixtures-schedule';
import type { TimeFilter } from '@/components/fixtures-schedule';
import { JoinPoolDialog } from '@/components/join-pool-dialog';
import { LeaderboardRow } from '@/components/leaderboard-row';
import { MovementArrow } from '@/components/movement-arrow';
import PlayerAvatar from '@/components/player-avatar';
import { PoolIdentity } from '@/components/pool-identity';
import { PoolInfoDialog } from '@/components/pool-info-dialog';
import { PrizePanel } from '@/components/prize-panel';
import { Button } from '@/components/ui/button';
import { SegmentedTabs } from '@/components/ui/segmented-tabs';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
    SheetTrigger,
} from '@/components/ui/sheet';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { useDisplayTimeZone } from '@/hooks/use-timezone';
import { useTranslation } from '@/hooks/use-translation';
import type { Translator } from '@/hooks/use-translation';
import { COMPARE_LIMIT } from '@/lib/compare';
import { ordinal } from '@/lib/leaderboards';
import { poolTitle } from '@/lib/pool-title';
import { prizeForPlace } from '@/lib/prizes';
import { cn } from '@/lib/utils';
import pools from '@/routes/pools';
import type {
    AttentionSummary,
    AttentionWindow,
    BoardRow,
    BoardSummary,
    BracketPhase,
    Comparison,
    PoolDetail,
    FeaturedBoard,
    GroupView,
    MatchdayDescriptor,
    PlayerDirectoryEntry,
    PoolStandings,
} from '@/types/pools';

interface PoolShowProps {
    pool: PoolDetail;
    groups: GroupView[];
    bracket: BracketPhase[];
    /** The ordered matchday timeline (group rounds, then knockout phases) for the Matchdays view. */
    matchdays: MatchdayDescriptor[];
    standings: PoolStandings;
    /** The first three boards as full tables (Overall leads). */
    featuredBoards: FeaturedBoard[];
    /** Boards beyond the first three, as condensed summary cards; empty today. */
    moreBoards: BoardSummary[];
    /** Every entry in the pool, for the "Add player" comparison picker. */
    players: PlayerDirectoryEntry[];
    /** The head-to-head payload when the page is in compare mode (a ?compare= list); else null. */
    comparison: Comparison | null;
    /** The viewer's outstanding prediction work, surfaced as a reminder banner. */
    attention: AttentionSummary;
}

function DashboardBanner({
    pool,
    standings,
    canCompare,
    onCompare,
}: {
    pool: PoolDetail;
    standings: PoolStandings;
    canCompare: boolean;
    onCompare: () => void;
}) {
    const dates = pool.starts_on
        ? pool.ends_on
            ? `${pool.starts_on} – ${pool.ends_on}`
            : pool.starts_on
        : null;

    const { t } = useTranslation();
    const tz = useDisplayTimeZone();
    const hasEntry = standings.me !== null;
    // The actions row holds the primary CTA (edit when joined, join when not) plus the compare
    // control, sitting directly under the countdown strip.
    const hasActions = hasEntry || pool.can_join || canCompare;

    return (
        <header className="hero relative overflow-hidden rounded-3xl border border-border p-5 sm:p-8">
            <div className="hero-lines" />
            {/*
              Two-column hero: identity/title/meta, then the compact lock countdown sitting directly
              above the player's actions, share the left column; the prize breakdown takes the right
              column on its own. Pairing the deadline with the CTA it gates keeps the two columns a
              similar height and the card short, and collapses to one column (deadline high) below lg.
            */}
            <div className="relative grid grid-cols-1 gap-4 lg:grid-cols-[minmax(0,1fr)_minmax(0,22rem)] lg:items-start lg:gap-6">
                <div className="flex min-w-0 flex-col gap-4">
                    <div className="flex flex-col gap-3">
                        <div className="flex items-start justify-between gap-3">
                            <PoolIdentity
                                variant="banner"
                                source={pool.source}
                                tournament={pool.tournament_name}
                                scoringLabel={pool.scoring_label}
                                accent={pool.accent}
                                className="min-w-0"
                            />
                            <div className="shrink-0">
                                <PoolInfoDialog pool={pool} />
                            </div>
                        </div>
                        <div className="min-w-0">
                            <h1 className="text-3xl font-semibold tracking-tight text-balance text-foreground sm:text-4xl">
                                {pool.name}
                            </h1>
                            <div className="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1 text-sm text-muted-foreground">
                                <span className="inline-flex items-center rounded-full bg-muted px-2.5 py-0.5 text-xs font-semibold capitalize">
                                    {t(pool.status.replace('_', ' '))}
                                </span>
                                <span className="inline-flex items-center gap-1.5">
                                    <Users className="size-4" />
                                    {standings.participants}{' '}
                                    {standings.participants === 1
                                        ? t('player')
                                        : t('players')}
                                </span>
                                {dates && (
                                    <span className="inline-flex items-center gap-1.5">
                                        <CalendarDays className="size-4" />
                                        {dates}
                                    </span>
                                )}
                            </div>
                        </div>
                    </div>

                    <div className="flex flex-col gap-3">
                        <CountdownBand
                            lockAt={pool.predictions_lock_at}
                            tz={tz}
                            joined={hasEntry}
                            canJoin={pool.can_join}
                            hasScores={standings.has_scores}
                        />
                        {hasActions && (
                            <div className="flex flex-col gap-2.5 sm:flex-row sm:flex-wrap sm:items-center sm:gap-3">
                                {hasEntry ? (
                                    <Button
                                        asChild
                                        className="w-full sm:w-auto"
                                    >
                                        <Link
                                            href={pools.predict.edit(pool.slug)}
                                        >
                                            <PencilLine className="size-4" />
                                            {t('Edit predictions')}
                                        </Link>
                                    </Button>
                                ) : pool.can_join ? (
                                    <JoinPoolDialog
                                        pool={pool}
                                        className="w-full sm:w-auto"
                                    />
                                ) : null}
                                {canCompare && (
                                    <Button
                                        type="button"
                                        variant="outline"
                                        onClick={onCompare}
                                        className="w-full sm:w-auto"
                                    >
                                        <GitCompare className="size-4" />
                                        {t('Compare players')}
                                    </Button>
                                )}
                            </div>
                        )}
                    </div>
                </div>

                <PrizePanel pricing={pool.pricing} />
            </div>
        </header>
    );
}

/** The outstanding work in one open window, e.g. "4 picks left", "ties to break", or both. */
function windowSummary(window: AttentionWindow, t: Translator['t']): string {
    const parts: string[] = [];

    if (window.missing_count > 0) {
        parts.push(
            window.missing_count === 1
                ? t('1 pick left')
                : t(':count picks left', { count: window.missing_count }),
        );
    }

    if (window.has_unresolved_ties) {
        parts.push(t('ties to break'));
    }

    return parts.join(t(' and '));
}

/**
 * A reminder banner under the hero when the viewer has unfinished prediction work in an open window:
 * what's left in each window plus its deadline, with a CTA straight into the wizard. Renders nothing
 * once everything is done (or the viewer hasn't joined), so it quietly disappears when settled.
 */
function PredictionReminder({
    pool,
    attention,
}: {
    pool: PoolDetail;
    attention: AttentionSummary;
}) {
    const { t } = useTranslation();
    const tz = useDisplayTimeZone();

    if (!attention.needs_attention) {
        return null;
    }

    return (
        <section
            role="status"
            className="card-elevated flex flex-col gap-4 rounded-3xl border border-border bg-card p-5 sm:flex-row sm:items-center sm:justify-between sm:p-6"
        >
            <div className="flex items-start gap-3">
                <span className="bg-gold-gradient mt-0.5 flex size-9 shrink-0 items-center justify-center rounded-full text-[#3a2600] shadow-[var(--sh-sm)]">
                    <PencilLine className="size-4" />
                </span>
                <div className="space-y-1.5">
                    <p className="font-semibold text-foreground">
                        {t('Your predictions need attention')}
                    </p>
                    <ul className="space-y-1 text-sm text-muted-foreground">
                        {attention.open_windows.map((window) => (
                            <li
                                key={window.phase_key}
                                className="flex flex-wrap items-center gap-x-1.5"
                            >
                                <span className="font-medium text-foreground">
                                    {t(window.label)}
                                </span>
                                <span>— {windowSummary(window, t)}</span>
                                {window.deadline && (
                                    <span className="text-xs">
                                        · {t('closes')}{' '}
                                        {formatMatchDate(window.deadline, tz)},{' '}
                                        {formatMatchTime(window.deadline, tz)}
                                    </span>
                                )}
                            </li>
                        ))}
                    </ul>
                </div>
            </div>
            <Button asChild className="shrink-0">
                <Link href={pools.predict.edit(pool.slug)}>
                    <PencilLine className="size-4" />
                    {t('Complete predictions')}
                </Link>
            </Button>
        </section>
    );
}

/** The `+`/`✓` button for adding/removing a player from the comparison, shared across boards. */
function AddToggle({
    entryId,
    name,
    selected,
    disabled,
    onToggle,
}: {
    entryId: number;
    name: string;
    selected: boolean;
    disabled: boolean;
    onToggle: (entryId: number) => void;
}) {
    const { t } = useTranslation();

    return (
        <button
            type="button"
            onClick={() => onToggle(entryId)}
            disabled={disabled}
            aria-pressed={selected}
            aria-label={
                selected
                    ? t('Remove :name from the comparison', { name })
                    : t('Add :name to the comparison', { name })
            }
            className={cn(
                'press grid size-8 shrink-0 cursor-pointer place-items-center rounded-full border transition-colors',
                selected
                    ? 'border-primary bg-primary text-white'
                    : 'border-border hover:border-primary',
                disabled && 'cursor-not-allowed opacity-40',
            )}
        >
            {selected ? (
                <Check className="size-4" />
            ) : (
                <Plus className="size-4" />
            )}
        </button>
    );
}

/** A selectable board row shown while choosing players to compare (the normal row's sibling). */
function SelectableRow({
    row,
    selected,
    disabled,
    onToggle,
}: {
    row: BoardRow;
    selected: boolean;
    disabled: boolean;
    onToggle: (entryId: number) => void;
}) {
    const { t } = useTranslation();

    return (
        <div
            className={cn(
                'flex items-center gap-3 border-b border-border px-4 py-3 last:border-0 sm:px-5',
                selected && 'bg-primary/[0.06]',
            )}
        >
            <span className="w-6 text-center font-display font-semibold text-muted-foreground tabular-nums">
                {row.rank}
            </span>
            <PlayerAvatar
                name={row.name}
                initials={row.initials}
                src={row.avatar}
                fallbackClassName="bg-brand-gradient text-white"
                className="size-9"
            />
            <span className="min-w-0 flex-1 truncate font-display font-semibold">
                {row.name}
            </span>
            <span className="font-display text-sm font-semibold text-muted-foreground tabular-nums">
                {row.primary_value ?? '—'}
            </span>
            {row.is_me ? (
                <span className="px-2 text-xs font-semibold text-muted-foreground">
                    {t('You')}
                </span>
            ) : (
                <AddToggle
                    entryId={row.entry_id}
                    name={row.name}
                    selected={selected}
                    disabled={disabled}
                    onToggle={onToggle}
                />
            )}
        </div>
    );
}

/** Header tag marking the prize board apart from the bragging-rights side boards (paid pools). */
function BoardTag({ awardsPrizes }: { awardsPrizes: boolean }) {
    const { t } = useTranslation();

    if (awardsPrizes) {
        return (
            <span className="bg-gold-gradient inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-[10px] font-bold tracking-[0.08em] text-[#3a2600] uppercase">
                <Trophy className="size-3" />
                {t('Prize board')}
            </span>
        );
    }

    return (
        <span className="inline-flex items-center rounded-full bg-secondary px-2.5 py-1 text-[10px] font-bold tracking-[0.08em] text-muted-foreground uppercase">
            {t('Bragging rights')}
        </span>
    );
}

/**
 * One featured board as a full table: its top rows plus the viewer's pinned row when they rank
 * outside them. On the Overall (prize) board of a paid pool, each paying place shows its prize
 * amount inline. While choosing players to compare, rows become selectable and the pinned row hides.
 */
function FeaturedBoardCard({
    pool,
    board,
    selecting,
    selectedIds,
    onToggle,
}: {
    pool: PoolDetail;
    board: FeaturedBoard;
    selecting: boolean;
    selectedIds: number[];
    onToggle: (entryId: number) => void;
}) {
    const { t } = useTranslation();
    const atLimit = selectedIds.length >= COMPARE_LIMIT;
    const isPaid = pool.pricing.entry_price > 0;
    // Inline prize amounts: only on the prize board, and only once the pot has money in it.
    const showPrizes = board.awards_prizes && isPaid && pool.pricing.net > 0;
    const pinnedMe = !selecting ? board.me : null;

    // undefined hides the prize column entirely; null reserves it for a place that doesn't pay.
    const prizeFor = (rank: number): string | null | undefined =>
        showPrizes ? prizeForPlace(pool.pricing, rank) : undefined;

    return (
        <section className="flex flex-col gap-3">
            <div className="flex items-center justify-between gap-3">
                <div className="flex min-w-0 items-center gap-2">
                    <h3 className="truncate font-display text-lg font-semibold tracking-tight">
                        {board.label}
                    </h3>
                    {isPaid && <BoardTag awardsPrizes={board.awards_prizes} />}
                </div>
                {!selecting && (
                    <Link
                        href={
                            pools.leaderboard(pool.slug, {
                                query: { board: board.key },
                            }).url
                        }
                        className="inline-flex shrink-0 items-center gap-1 font-display text-sm font-semibold text-primary transition-all hover:gap-2"
                    >
                        {t('Details')}
                        <ArrowRight className="size-4" />
                    </Link>
                )}
            </div>
            <div className="overflow-hidden rounded-3xl border border-border bg-card shadow-[var(--sh-sm)]">
                {selecting
                    ? board.top.map((row) => (
                          <SelectableRow
                              key={row.rank}
                              row={row}
                              selected={selectedIds.includes(row.entry_id)}
                              disabled={
                                  atLimit && !selectedIds.includes(row.entry_id)
                              }
                              onToggle={onToggle}
                          />
                      ))
                    : board.top.map((row) => (
                          <LeaderboardRow
                              key={row.rank}
                              entry={{
                                  rank: row.rank,
                                  name: row.name,
                                  initials: row.initials,
                                  avatar: row.avatar,
                                  primary: row.primary_value,
                                  isMe: row.is_me,
                                  movement: row.movement,
                                  movementDelta: row.movement_delta,
                                  prize: prizeFor(row.rank),
                              }}
                          />
                      ))}
                {pinnedMe && (
                    <>
                        <div className="border-t border-dashed border-border bg-muted/30 px-4 py-1 text-center text-[10px] font-bold tracking-[0.12em] text-muted-foreground uppercase">
                            {t('You')}
                        </div>
                        <LeaderboardRow
                            entry={{
                                rank: pinnedMe.rank,
                                name: pinnedMe.name,
                                initials: pinnedMe.initials,
                                avatar: pinnedMe.avatar,
                                primary: pinnedMe.primary_value,
                                isMe: true,
                                movement: pinnedMe.movement,
                                movementDelta: pinnedMe.movement_delta,
                                prize: prizeFor(pinnedMe.rank),
                            }}
                        />
                    </>
                )}
            </div>
        </section>
    );
}

/**
 * Condense a featured board into the summary-card shape: its leader (top row) and the viewer's own
 * standing. The viewer sits in `top` when ranked there, otherwise in the pinned `me` row; either way
 * the card needs only those two, so the side boards ship just the leader plus the pinned viewer.
 */
function featuredBoardToSummary(board: FeaturedBoard): BoardSummary {
    const leader = board.top[0] ?? null;
    const mine = board.top.find((row) => row.is_me) ?? board.me;

    return {
        key: board.key,
        label: board.label,
        primary_stat_label: board.primary_stat_label,
        leader: leader
            ? {
                  entry_id: leader.entry_id,
                  name: leader.name,
                  initials: leader.initials,
                  avatar: leader.avatar,
                  primary_value: leader.primary_value,
                  is_me: leader.is_me,
              }
            : null,
        you: mine
            ? {
                  rank: mine.rank,
                  primary_value: mine.primary_value,
                  movement: mine.movement,
                  movement_delta: mine.movement_delta,
              }
            : null,
    };
}

/**
 * The leaderboard summary on the pool page: the headline (Overall) board as a short top-N table
 * filling a wide left column, with the remaining boards condensed into leader+you summary cards
 * stacked in a narrower right column; below `lg` they stack into one column. Each board links to its
 * own detail page (the table via its "Details" header, each summary card as a whole).
 */
function FeaturedBoards({
    pool,
    boards,
    selecting,
    selectedIds,
    onToggle,
}: {
    pool: PoolDetail;
    boards: FeaturedBoard[];
    selecting: boolean;
    selectedIds: number[];
    onToggle: (entryId: number) => void;
}) {
    const { t } = useTranslation();
    const headline = boards[0];

    // Nobody on the board yet — hide the whole region (the banner still offers Join).
    if (!headline || headline.participants === 0) {
        return null;
    }

    const cardProps = { pool, selecting, selectedIds, onToggle };

    return (
        <section className="flex flex-col gap-3">
            {selecting && (
                <span className="font-display text-sm font-semibold text-muted-foreground">
                    {t('Tap')} <Plus className="inline size-3.5" />{' '}
                    {t('to add a player to the comparison')}
                </span>
            )}
            <div className="grid gap-4 lg:grid-cols-[minmax(0,1.7fr)_minmax(0,1fr)] lg:items-start">
                <FeaturedBoardCard board={headline} {...cardProps} />
                {boards.length > 1 && (
                    <div className="flex flex-col gap-3">
                        {/* Mirror the headline card's section header (h3 + Details, ~h-7)
                            so the side cards line up with the table body; lg+ only, since
                            the columns stack into one below it. */}
                        <div className="hidden h-7 lg:block" aria-hidden />
                        {boards.slice(1).map((board) => (
                            <BoardSummaryCard
                                key={board.key}
                                summary={featuredBoardToSummary(board)}
                                {...cardProps}
                            />
                        ))}
                    </div>
                )}
            </div>
        </section>
    );
}

/**
 * One non-Overall board condensed to a card: its leader as the headline, with the viewer's own
 * position beneath. Used both in the pool page's right column (next to the Overall table) and in the
 * "More leaderboards" grid. In normal mode it deep-links to that board's tab; while choosing players
 * to compare it stays put (no link) and the leader gains a `+` so its winner can be added straight
 * from the card.
 */
function BoardSummaryCard({
    pool,
    summary,
    selecting,
    selectedIds,
    onToggle,
}: {
    pool: PoolDetail;
    summary: BoardSummary;
    selecting: boolean;
    selectedIds: number[];
    onToggle: (entryId: number) => void;
}) {
    const { t } = useTranslation();
    const atLimit = selectedIds.length >= COMPARE_LIMIT;
    const unit = summary.primary_stat_label.toLowerCase();
    const leader =
        summary.leader && summary.leader.primary_value ? summary.leader : null;
    const cardClass =
        'flex flex-col gap-2 rounded-2xl border border-border bg-card px-4 py-3 shadow-[var(--sh-sm)]';

    const body = (
        <>
            <div className="flex items-center justify-between">
                <span className="text-[11px] font-bold tracking-[0.08em] text-muted-foreground uppercase">
                    {summary.label}
                </span>
                {!selecting && (
                    <ArrowRight className="size-4 text-muted-foreground transition-all group-hover:translate-x-0.5 group-hover:text-primary" />
                )}
            </div>

            <div className="flex items-center justify-between gap-2">
                {leader ? (
                    <span className="flex min-w-0 items-center gap-2">
                        <PlayerAvatar
                            name={leader.name}
                            initials={leader.initials}
                            src={leader.avatar}
                            fallbackClassName="bg-gold-gradient text-xs text-[#3a2600]"
                            className="size-7"
                        />
                        <span className="truncate font-display text-sm font-semibold">
                            {leader.name}
                        </span>
                    </span>
                ) : (
                    <span className="font-display text-sm font-semibold text-muted-foreground">
                        {t('No leader yet')}
                    </span>
                )}
                <span className="flex shrink-0 items-center gap-2">
                    {leader && (
                        <span className="font-display text-sm font-semibold tabular-nums">
                            {leader.primary_value?.toLocaleString()} {unit}
                        </span>
                    )}
                    {selecting && leader && !leader.is_me && (
                        <AddToggle
                            entryId={leader.entry_id}
                            name={leader.name}
                            selected={selectedIds.includes(leader.entry_id)}
                            disabled={
                                atLimit &&
                                !selectedIds.includes(leader.entry_id)
                            }
                            onToggle={onToggle}
                        />
                    )}
                </span>
            </div>

            <div className="flex items-center justify-between gap-2 border-t border-border pt-2 text-xs font-medium text-muted-foreground">
                <span className="inline-flex items-center gap-1.5">
                    {t('You')} · {summary.you ? ordinal(summary.you.rank) : '—'}
                    {summary.you?.movement && (
                        <MovementArrow
                            movement={summary.you.movement}
                            delta={summary.you.movement_delta}
                            size="sm"
                        />
                    )}
                </span>
                {summary.you && (
                    <span className="shrink-0 tabular-nums">
                        {summary.you.primary_value?.toLocaleString()} {unit}
                    </span>
                )}
            </div>
        </>
    );

    return selecting ? (
        <div className={cardClass}>{body}</div>
    ) : (
        <Link
            href={`${pools.leaderboard(pool.slug).url}?board=${summary.key}`}
            className={cn(
                'group transition-colors hover:border-primary/40',
                cardClass,
            )}
        >
            {body}
        </Link>
    );
}

/**
 * The "More leaderboards" grid: any boards beyond the first three, each as a {@see BoardSummaryCard}.
 * Empty while a pool runs three or fewer boards (the case today).
 */
function BoardSummaries({
    pool,
    summaries,
    selecting,
    selectedIds,
    onToggle,
}: {
    pool: PoolDetail;
    summaries: BoardSummary[];
    selecting: boolean;
    selectedIds: number[];
    onToggle: (entryId: number) => void;
}) {
    const { t } = useTranslation();

    if (summaries.length === 0) {
        return null;
    }

    const cardProps = { pool, selecting, selectedIds, onToggle };

    return (
        <section className="flex flex-col gap-3">
            <h2 className="font-display text-xl font-semibold tracking-tight">
                {t('More leaderboards')}
            </h2>
            <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                {summaries.map((summary) => (
                    <BoardSummaryCard
                        key={summary.key}
                        summary={summary}
                        {...cardProps}
                    />
                ))}
            </div>
        </section>
    );
}

function metaLine(
    count: number,
    prefix: string,
    range: string | null,
    t: Translator['t'],
): string {
    const label = count === 1 ? t('1 match') : t(':count matches', { count });

    return [prefix, label, range].filter(Boolean).join(' · ');
}

type TimedFixture = {
    kicks_off_at: string | null;
    home_goals: number | null;
    away_goals: number | null;
};

/** Narrow a fixture list to the active time filter, preserving the element type. */
function applyTimeFilter<T extends TimedFixture>(
    fixtures: T[],
    filter: TimeFilter,
    tz: string,
): T[] {
    return filter === 'all'
        ? fixtures
        : fixtures.filter((fixture) =>
              matchesFixtureTimeFilter(fixture, filter, tz),
          );
}

type FixturesViewMode = 'groups' | 'matchdays' | 'schedule';

const VIEW_FILTER_OPTIONS: { value: FixturesViewMode; label: string }[] = [
    { value: 'groups', label: 'Groups' },
    { value: 'matchdays', label: 'Matchdays' },
    { value: 'schedule', label: 'Schedule' },
];

const TIME_FILTER_OPTIONS: { value: TimeFilter; label: string }[] = [
    { value: 'all', label: 'All' },
    { value: 'today', label: 'Today' },
    { value: 'upcoming', label: 'Upcoming' },
];

/**
 * The mobile face of the fixtures filters: a single pill summarising the active view + time filter
 * that opens a bottom sheet with both as segmented controls — the two filter sections collapse to one
 * tidy control on phones. Desktop keeps the side-by-side toggles.
 */
function FixtureFiltersSheet({
    view,
    onViewChange,
    filter,
    onFilterChange,
}: {
    view: FixturesViewMode;
    onViewChange: (view: FixturesViewMode) => void;
    filter: TimeFilter;
    onFilterChange: (filter: TimeFilter) => void;
}) {
    const { t } = useTranslation();
    const [open, setOpen] = useState(false);
    const viewItems = VIEW_FILTER_OPTIONS.map((o) => ({
        value: o.value,
        label: t(o.label),
    }));
    const timeItems = TIME_FILTER_OPTIONS.map((o) => ({
        value: o.value,
        label: t(o.label),
    }));
    const viewLabel = viewItems.find((o) => o.value === view)?.label;
    const timeLabel = timeItems.find((o) => o.value === filter)?.label;

    return (
        <Sheet open={open} onOpenChange={setOpen}>
            <SheetTrigger asChild>
                <button
                    type="button"
                    className="press inline-flex items-center gap-2 rounded-full border border-border bg-card px-3.5 py-2 font-display text-sm font-semibold shadow-[var(--sh-sm)]"
                >
                    <SlidersHorizontal className="size-4 text-muted-foreground" />
                    <span>
                        {viewLabel} · {timeLabel}
                    </span>
                    <ChevronDown className="size-4 text-muted-foreground" />
                </button>
            </SheetTrigger>
            <SheetContent
                side="bottom"
                className="rounded-t-3xl px-4 pt-5 pb-[calc(1rem+env(safe-area-inset-bottom,0px))]"
            >
                <SheetHeader className="p-0">
                    <SheetTitle className="font-display text-base">
                        {t('Filters')}
                    </SheetTitle>
                    <SheetDescription className="sr-only">
                        {t(
                            'Choose how fixtures are grouped and which ones to show.',
                        )}
                    </SheetDescription>
                </SheetHeader>
                <div className="flex flex-col gap-4">
                    <div className="flex flex-col gap-2">
                        <span className="text-[11px] font-bold tracking-[0.08em] text-muted-foreground uppercase">
                            {t('View')}
                        </span>
                        <SegmentedTabs
                            aria-label={t('Fixtures view')}
                            value={view}
                            onChange={onViewChange}
                            items={viewItems}
                        />
                    </div>
                    <div className="flex flex-col gap-2">
                        <span className="text-[11px] font-bold tracking-[0.08em] text-muted-foreground uppercase">
                            {t('Show')}
                        </span>
                        <SegmentedTabs
                            aria-label={t('Show fixtures')}
                            value={filter}
                            onChange={onFilterChange}
                            items={timeItems}
                        />
                    </div>
                </div>
            </SheetContent>
        </Sheet>
    );
}

/**
 * The Fixtures section, viewable three ways: Groups (phase-tabbed group/bracket cards, with
 * standings), Matchdays (sections that mirror the leaderboard's rounds), and Schedule (every match
 * in kickoff order). Comparison mode is only meaningful per-card, so it pins the Groups view.
 */
function FixturesView({
    groups,
    bracket,
    matchdays,
    comparison,
}: {
    groups: GroupView[];
    bracket: BracketPhase[];
    matchdays: MatchdayDescriptor[];
    /** When set, each fixture renders a per-player comparison instead of the viewer's own card. */
    comparison: Comparison | null;
}) {
    const { t, tPhase } = useTranslation();
    const tz = useDisplayTimeZone();
    const [view, setView] = useState<FixturesViewMode>('groups');
    const [filter, setFilter] = useState<TimeFilter>('all');
    // The view + time filter persist into compare mode: every view (including Matchdays/Schedule)
    // renders each player's picks per fixture, so comparison is no longer confined to the Groups view.
    const effectiveView: FixturesViewMode = view;
    const effectiveFilter: TimeFilter = filter;

    // The Groups view filters the fixture ROWS (standings stay full); empty groups/phases drop out.
    const visibleGroups = groups
        .map((group) => ({
            ...group,
            fixtures: applyTimeFilter(group.fixtures, effectiveFilter, tz),
        }))
        .filter(
            (group) => effectiveFilter === 'all' || group.fixtures.length > 0,
        );
    const groupFixtures = visibleGroups.flatMap((g) => g.fixtures);
    const groupMatches = groupFixtures.length;

    const koPhases = bracket.filter(
        (p) => p.phase_key !== 'final' && p.phase_key !== 'third_place',
    );
    const finalPhase = bracket.find((p) => p.phase_key === 'final');
    const thirdPhase = bracket.find((p) => p.phase_key === 'third_place');
    const visibleFinal = applyTimeFilter(
        finalPhase?.fixtures ?? [],
        effectiveFilter,
        tz,
    );
    const visibleThird = applyTimeFilter(
        thirdPhase?.fixtures ?? [],
        effectiveFilter,
        tz,
    );
    const finalFixtures = [...visibleFinal, ...visibleThird];
    const finalTabExists =
        (finalPhase?.fixtures.length ?? 0) +
            (thirdPhase?.fixtures.length ?? 0) >
        0;

    // Tab counts reflect what the active filter actually shows, so a badge never overstates a phase.
    // The Final tab stays present whenever the tournament has those matches; its count tracks the
    // filter (and its panel shows an empty state when the filter clears it).
    const phases: Phase[] = [
        { id: 'gs', label: t('Group Stage'), count: groupMatches },
        ...koPhases.map((p) => ({
            id: p.phase_key,
            label: tPhase(p.phase_key, p.phase_name),
            count: applyTimeFilter(p.fixtures, effectiveFilter, tz).length,
        })),
        ...(finalTabExists
            ? [{ id: 'final', label: t('Final'), count: finalFixtures.length }]
            : []),
    ];

    const [active, setActive] = useState('gs');
    // If the time filter empties the phase the user is on, show the first phase that still has
    // matches instead of a bare empty state. `active` itself is untouched, so clearing the filter
    // (every phase populated again) returns them to the tab they chose.
    const activeCount = phases.find((p) => p.id === active)?.count ?? 0;
    const firstNonEmpty = phases.find((p) => p.count > 0)?.id;
    const effectiveActive =
        activeCount > 0 ? active : (firstNonEmpty ?? active);
    const players = comparison?.players ?? [];

    return (
        <section className="flex flex-col gap-6">
            {/* Mobile: both filter sections collapse into one Filters sheet. */}
            <div className="sm:hidden">
                <FixtureFiltersSheet
                    view={view}
                    onViewChange={setView}
                    filter={filter}
                    onFilterChange={setFilter}
                />
            </div>

            {/* Desktop: the side-by-side toggles. */}
            <div className="hidden flex-wrap items-center justify-between gap-3 sm:flex">
                <ToggleGroup
                    type="single"
                    variant="outline"
                    size="sm"
                    value={view}
                    onValueChange={(next) => {
                        if (
                            next === 'groups' ||
                            next === 'matchdays' ||
                            next === 'schedule'
                        ) {
                            setView(next);
                        }
                    }}
                >
                    <ToggleGroupItem value="groups" className="px-4 text-xs">
                        {t('Groups')}
                    </ToggleGroupItem>
                    <ToggleGroupItem value="matchdays" className="px-4 text-xs">
                        {t('Matchdays')}
                    </ToggleGroupItem>
                    <ToggleGroupItem value="schedule" className="px-4 text-xs">
                        {t('Schedule')}
                    </ToggleGroupItem>
                </ToggleGroup>

                <ToggleGroup
                    type="single"
                    variant="outline"
                    size="sm"
                    value={filter}
                    onValueChange={(next) => {
                        if (
                            next === 'all' ||
                            next === 'today' ||
                            next === 'upcoming'
                        ) {
                            setFilter(next);
                        }
                    }}
                >
                    <ToggleGroupItem value="all" className="px-4 text-xs">
                        {t('All')}
                    </ToggleGroupItem>
                    <ToggleGroupItem value="today" className="px-4 text-xs">
                        {t('Today')}
                    </ToggleGroupItem>
                    <ToggleGroupItem value="upcoming" className="px-4 text-xs">
                        {t('Upcoming')}
                    </ToggleGroupItem>
                </ToggleGroup>
            </div>

            {effectiveView === 'matchdays' && (
                <MatchdayView
                    groups={groups}
                    bracket={bracket}
                    matchdays={matchdays}
                    filter={effectiveFilter}
                    comparison={comparison}
                />
            )}

            {effectiveView === 'schedule' && (
                <ScheduleView
                    groups={groups}
                    bracket={bracket}
                    filter={effectiveFilter}
                    comparison={comparison}
                />
            )}

            {effectiveView === 'groups' && (
                <>
                    <PhaseTabs
                        phases={phases}
                        active={effectiveActive}
                        onSelect={setActive}
                    />

                    {effectiveActive === 'gs' && (
                        <div>
                            <PhaseMeta
                                title={t('Group Stage')}
                                meta={metaLine(
                                    groupMatches,
                                    t(':count groups', {
                                        count: visibleGroups.length,
                                    }),
                                    phaseDateRange(groupFixtures, tz),
                                    t,
                                )}
                            />
                            {visibleGroups.length === 0 ? (
                                <FixturesEmptyState
                                    message={timeFilterEmptyMessage(
                                        effectiveFilter,
                                    )}
                                />
                            ) : (
                                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                                    {visibleGroups.map((group) =>
                                        comparison ? (
                                            <CompareGroupCard
                                                key={group.name}
                                                group={group}
                                                players={players}
                                                windowStatus={
                                                    comparison.windows.group ??
                                                    'pending'
                                                }
                                            />
                                        ) : (
                                            <GroupFixtureCard
                                                key={group.name}
                                                name={group.name}
                                                teams={group.teams}
                                                fixtures={group.fixtures}
                                                standings={group.standings}
                                                predictedStandings={
                                                    group.predicted_standings
                                                }
                                            />
                                        ),
                                    )}
                                </div>
                            )}
                        </div>
                    )}

                    {koPhases.map((phase) => {
                        if (effectiveActive !== phase.phase_key) {
                            return null;
                        }

                        const phaseFixtures = applyTimeFilter(
                            phase.fixtures,
                            effectiveFilter,
                            tz,
                        );

                        return (
                            <div key={phase.phase_key}>
                                <PhaseMeta
                                    title={tPhase(
                                        phase.phase_key,
                                        phase.phase_name,
                                    )}
                                    meta={metaLine(
                                        phaseFixtures.length,
                                        '',
                                        phaseDateRange(phaseFixtures, tz),
                                        t,
                                    )}
                                />
                                {phaseFixtures.length === 0 ? (
                                    <FixturesEmptyState
                                        message={timeFilterEmptyMessage(
                                            effectiveFilter,
                                        )}
                                    />
                                ) : (
                                    <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                                        {phaseFixtures.map((fixture) =>
                                            comparison ? (
                                                <CompareKnockoutCard
                                                    key={fixture.match_number}
                                                    fixture={fixture}
                                                    players={players}
                                                    windowStatus={
                                                        comparison.windows[
                                                            phase.phase_key
                                                        ] ?? 'pending'
                                                    }
                                                />
                                            ) : (
                                                <KnockoutSlotCard
                                                    key={fixture.match_number}
                                                    fixture={fixture}
                                                />
                                            ),
                                        )}
                                    </div>
                                )}
                            </div>
                        );
                    })}

                    {effectiveActive === 'final' && (
                        <div>
                            <PhaseMeta
                                title={t('Final & Third Place')}
                                meta={metaLine(
                                    finalFixtures.length,
                                    '',
                                    phaseDateRange(finalFixtures, tz),
                                    t,
                                )}
                            />
                            {finalFixtures.length === 0 ? (
                                <FixturesEmptyState
                                    message={timeFilterEmptyMessage(
                                        effectiveFilter,
                                    )}
                                />
                            ) : (
                                <div className="flex flex-col gap-4">
                                    {visibleFinal.map((fixture) =>
                                        comparison ? (
                                            <CompareFinalCard
                                                key={fixture.match_number}
                                                fixture={fixture}
                                                players={players}
                                                windowStatus={
                                                    comparison.windows[
                                                        finalPhase?.phase_key ??
                                                            'final'
                                                    ] ?? 'pending'
                                                }
                                            />
                                        ) : (
                                            <FinalCard
                                                key={fixture.match_number}
                                                fixture={fixture}
                                            />
                                        ),
                                    )}
                                    {visibleThird.map((fixture) => (
                                        <div
                                            key={fixture.match_number}
                                            className="mx-auto w-full max-w-xl"
                                        >
                                            {comparison ? (
                                                <CompareKnockoutCard
                                                    fixture={fixture}
                                                    players={players}
                                                    windowStatus={
                                                        comparison.windows[
                                                            thirdPhase?.phase_key ??
                                                                'third_place'
                                                        ] ?? 'pending'
                                                    }
                                                />
                                            ) : (
                                                <KnockoutSlotCard
                                                    fixture={fixture}
                                                />
                                            )}
                                        </div>
                                    ))}
                                </div>
                            )}
                        </div>
                    )}
                </>
            )}
        </section>
    );
}

export default function PoolShow({
    pool,
    groups,
    bracket,
    matchdays,
    standings,
    featuredBoards,
    moreBoards,
    players,
    comparison,
    attention,
}: PoolShowProps) {
    const [selecting, setSelecting] = useState(false);
    const [selectedIds, setSelectedIds] = useState<number[]>([]);
    const [pickerOpen, setPickerOpen] = useState(false);

    // URL-driven: compare mode is on when the server sent a comparison; selection is a transient
    // local step that takes precedence so the picker UI replaces the head-to-head while editing.
    const compareActive = comparison !== null && !selecting;

    const opponentIds = (): number[] =>
        (comparison?.players ?? [])
            .filter((player) => !player.is_viewer && player.entry_id != null)
            .map((player) => player.entry_id as number);

    const applyCompare = (ids: number[], onSuccess?: () => void) => {
        // reload already preserves scroll + local state; we only swap the comparison prop. Replace
        // the history entry when adjusting an existing comparison so Back doesn't step through every
        // tweak; the first entry into compare mode stays a push, so Back leaves compare cleanly.
        router.reload({
            only: ['comparison'],
            data: { compare: ids.join(',') },
            replace: comparison !== null,
            onSuccess,
        });
    };

    const startSelecting = () => {
        setSelectedIds(opponentIds());
        setSelecting(true);
    };

    const toggleSelect = (entryId: number) => {
        setSelectedIds((prev) =>
            prev.includes(entryId)
                ? prev.filter((id) => id !== entryId)
                : prev.length >= COMPARE_LIMIT
                  ? prev
                  : [...prev, entryId],
        );
    };

    const confirmSelection = () => {
        // Keep the selection UI up until the comparison lands, so the page doesn't flash normal mode.
        applyCompare(selectedIds, () => {
            setPickerOpen(false);
            setSelecting(false);
        });
    };

    const cancelSelection = () => {
        setPickerOpen(false);
        setSelecting(false);
    };

    const exitCompare = () => {
        // preserveState keeps the mounted FixturesView (and so its active phase tab) and scroll
        // position; only the comparison prop clears. replace keeps Back out of compare history.
        router.visit(pools.show(pool.slug).url, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    };

    const addFromCompare = () => {
        startSelecting();
        setPickerOpen(true);
    };

    const removeLane = (entryId: number) => {
        const next = opponentIds().filter((id) => id !== entryId);

        if (next.length === 0) {
            exitCompare();
        } else {
            applyCompare(next);
        }
    };

    return (
        <>
            <Head title={poolTitle(pool.name, pool.source)} />
            <div className="flex h-full flex-1 flex-col gap-10 p-4 sm:p-6 lg:p-8">
                <DashboardBanner
                    pool={pool}
                    standings={standings}
                    canCompare={
                        !compareActive &&
                        !selecting &&
                        standings.participants > 1
                    }
                    onCompare={startSelecting}
                />

                <PredictionReminder pool={pool} attention={attention} />

                {compareActive ? (
                    <CompareStrip
                        players={comparison.players}
                        windows={comparison.windows}
                        boards={pool.leaderboards}
                        onRemove={removeLane}
                    />
                ) : (
                    <FeaturedBoards
                        pool={pool}
                        boards={featuredBoards}
                        selecting={selecting}
                        selectedIds={selectedIds}
                        onToggle={toggleSelect}
                    />
                )}

                {!compareActive && moreBoards.length > 0 && (
                    <BoardSummaries
                        pool={pool}
                        summaries={moreBoards}
                        selecting={selecting}
                        selectedIds={selectedIds}
                        onToggle={toggleSelect}
                    />
                )}

                <FixturesView
                    groups={groups}
                    bracket={bracket}
                    matchdays={matchdays}
                    comparison={compareActive ? comparison : null}
                />
            </div>

            {selecting && (
                <CompareDock
                    mode="selecting"
                    directory={players}
                    selectedIds={selectedIds}
                    limit={COMPARE_LIMIT}
                    onRemove={toggleSelect}
                    onOpenPicker={() => setPickerOpen(true)}
                    onCancel={cancelSelection}
                    onConfirm={confirmSelection}
                />
            )}

            {compareActive && (
                <CompareDock
                    mode="comparing"
                    playerCount={comparison.players.length}
                    canAddMore={comparison.players.length - 1 < COMPARE_LIMIT}
                    onAddPlayer={addFromCompare}
                    onEdit={startSelecting}
                    onExit={exitCompare}
                />
            )}

            <AddPlayerDialog
                open={pickerOpen && selecting}
                onOpenChange={setPickerOpen}
                directory={players}
                selectedIds={selectedIds}
                onToggle={toggleSelect}
                limit={COMPARE_LIMIT}
            />
        </>
    );
}

PoolShow.layout = {
    breadcrumbs: [{ title: 'Pools', href: pools.index() }],
};
