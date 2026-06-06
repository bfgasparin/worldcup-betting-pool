import { Head, Link, router, usePage } from '@inertiajs/react';
import {
    ArrowRight,
    CalendarClock,
    CalendarDays,
    Check,
    ClipboardCheck,
    GitCompare,
    PencilLine,
    Plus,
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
import { JoinPoolDialog } from '@/components/join-pool-dialog';
import { LeaderboardRow } from '@/components/leaderboard-row';
import { MovementArrow } from '@/components/movement-arrow';
import PlayerAvatar from '@/components/player-avatar';
import { PoolIdentity } from '@/components/pool-identity';
import { PoolInfoDialog } from '@/components/pool-info-dialog';
import { PrizePanel } from '@/components/prize-panel';
import { Button } from '@/components/ui/button';
import { useDisplayTimeZone } from '@/hooks/use-timezone';
import { COMPARE_LIMIT } from '@/lib/compare';
import { ordinal } from '@/lib/leaderboards';
import { poolTitle } from '@/lib/pool-title';
import { cn } from '@/lib/utils';
import pools from '@/routes/pools';
import type {
    AttentionSummary,
    AttentionWindow,
    BoardSummary,
    BracketPhase,
    Comparison,
    PoolDetail,
    GroupView,
    LeaderboardEntryRow,
    PlayerDirectoryEntry,
    PoolStandings,
} from '@/types/pools';

interface PoolShowProps {
    pool: PoolDetail;
    groups: GroupView[];
    bracket: BracketPhase[];
    standings: PoolStandings;
    boardSummaries: BoardSummary[];
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
    isAdmin,
    canCompare,
    onCompare,
}: {
    pool: PoolDetail;
    standings: PoolStandings;
    isAdmin: boolean;
    canCompare: boolean;
    onCompare: () => void;
}) {
    const dates = pool.starts_on
        ? pool.ends_on
            ? `${pool.starts_on} – ${pool.ends_on}`
            : pool.starts_on
        : null;

    const tz = useDisplayTimeZone();
    const hasEntry = standings.me !== null;
    // The actions row holds the primary CTA (edit when joined, join when not) plus the compare and
    // admin tools, sitting directly under the countdown strip.
    const hasActions =
        hasEntry ||
        pool.can_join ||
        canCompare ||
        pool.can_review_scores ||
        isAdmin;

    return (
        <header className="hero relative overflow-hidden rounded-3xl border border-border p-6 sm:p-8">
            <div className="hero-lines" />
            {/*
              Two-column hero: identity/title/meta, then the compact lock countdown sitting directly
              above the player's actions, share the left column; the prize breakdown takes the right
              column on its own. Pairing the deadline with the CTA it gates keeps the two columns a
              similar height and the card short, and collapses to one column (deadline high) below lg.
            */}
            <div className="relative grid grid-cols-1 gap-6 lg:grid-cols-[minmax(0,1fr)_minmax(0,22rem)] lg:items-start">
                <div className="flex min-w-0 flex-col gap-5">
                    <div className="flex items-start justify-between gap-3">
                        <div className="min-w-0">
                            <PoolIdentity
                                variant="banner"
                                source={pool.source}
                                scoringLabel={pool.scoring_label}
                                accent={pool.accent}
                                className="mb-3"
                            />
                            <h1 className="text-3xl font-semibold tracking-tight text-balance text-foreground sm:text-4xl">
                                {pool.name}
                            </h1>
                            <div className="mt-2 flex flex-wrap items-center gap-3 text-sm text-muted-foreground">
                                <span className="inline-flex items-center rounded-full bg-muted px-2.5 py-0.5 text-xs font-semibold capitalize">
                                    {pool.status.replace('_', ' ')}
                                </span>
                                <span className="capitalize">{pool.sport}</span>
                                {dates && (
                                    <span className="inline-flex items-center gap-1.5">
                                        <CalendarDays className="size-4" />
                                        {dates}
                                    </span>
                                )}
                                <span className="inline-flex items-center gap-1.5">
                                    <Users className="size-4" />
                                    {standings.participants}{' '}
                                    {standings.participants === 1
                                        ? 'player'
                                        : 'players'}
                                </span>
                            </div>
                        </div>
                        <div className="shrink-0">
                            <PoolInfoDialog pool={pool} />
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
                            <div className="flex flex-wrap items-center gap-3">
                                {hasEntry ? (
                                    <Button asChild>
                                        <Link
                                            href={pools.predict.edit(pool.slug)}
                                        >
                                            <PencilLine className="size-4" />
                                            Edit predictions
                                        </Link>
                                    </Button>
                                ) : pool.can_join ? (
                                    <JoinPoolDialog pool={pool} />
                                ) : null}
                                {canCompare && (
                                    <Button
                                        type="button"
                                        variant="outline"
                                        onClick={onCompare}
                                    >
                                        <GitCompare className="size-4" />
                                        Compare players
                                    </Button>
                                )}
                                {pool.can_review_scores && (
                                    <Button asChild variant="outline">
                                        <Link
                                            href={pools.scores.review(
                                                pool.slug,
                                            )}
                                        >
                                            <ClipboardCheck className="size-4" />
                                            Review scores
                                        </Link>
                                    </Button>
                                )}
                                {isAdmin && (
                                    <Button asChild variant="outline">
                                        <Link
                                            href={pools.schedule.index(
                                                pool.slug,
                                            )}
                                        >
                                            <CalendarClock className="size-4" />
                                            Manage schedule
                                        </Link>
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
function windowSummary(window: AttentionWindow): string {
    const parts: string[] = [];

    if (window.missing_count > 0) {
        parts.push(
            `${window.missing_count} ${window.missing_count === 1 ? 'pick' : 'picks'} left`,
        );
    }

    if (window.has_unresolved_ties) {
        parts.push('ties to break');
    }

    return parts.join(' and ');
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
                        Your predictions need attention
                    </p>
                    <ul className="space-y-1 text-sm text-muted-foreground">
                        {attention.open_windows.map((window) => (
                            <li
                                key={window.phase_key}
                                className="flex flex-wrap items-center gap-x-1.5"
                            >
                                <span className="font-medium text-foreground">
                                    {window.label}
                                </span>
                                <span>— {windowSummary(window)}</span>
                                {window.deadline && (
                                    <span className="text-xs">
                                        · closes{' '}
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
                    Complete predictions
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
    return (
        <button
            type="button"
            onClick={() => onToggle(entryId)}
            disabled={disabled}
            aria-pressed={selected}
            aria-label={
                selected
                    ? `Remove ${name} from the comparison`
                    : `Add ${name} to the comparison`
            }
            className={cn(
                'grid size-8 shrink-0 cursor-pointer place-items-center rounded-full border transition-colors',
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

/** A selectable Overall row shown while choosing players to compare (the normal row's sibling). */
function SelectableRow({
    row,
    selected,
    disabled,
    onToggle,
}: {
    row: LeaderboardEntryRow;
    selected: boolean;
    disabled: boolean;
    onToggle: (entryId: number) => void;
}) {
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
                {row.points ?? '—'}
            </span>
            {row.is_me ? (
                <span className="px-2 text-xs font-semibold text-muted-foreground">
                    You
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

function PoolPreview({
    pool,
    standings,
    selecting,
    selectedIds,
    onToggle,
}: {
    pool: PoolDetail;
    standings: PoolStandings;
    selecting: boolean;
    selectedIds: number[];
    onToggle: (entryId: number) => void;
}) {
    if (standings.top.length === 0) {
        return null;
    }

    // Pin the viewer's own row when they're ranked outside the shown top, so they always see where
    // they stand on the Overall board.
    const pinnedMe =
        standings.me && !standings.top.some((row) => row.is_me)
            ? standings.me
            : null;
    const atLimit = selectedIds.length >= COMPARE_LIMIT;

    return (
        <section className="flex flex-col gap-3">
            <div className="flex items-center justify-between gap-3">
                <h2 className="font-display text-xl font-semibold tracking-tight">
                    Overall
                </h2>
                {selecting ? (
                    <span className="font-display text-sm font-semibold text-muted-foreground">
                        Tap <Plus className="inline size-3.5" /> to add a player
                    </span>
                ) : (
                    <Link
                        href={pools.leaderboard(pool.slug)}
                        className="inline-flex items-center gap-1 font-display text-sm font-semibold text-primary transition-all hover:gap-2"
                    >
                        See all leaderboards
                        <ArrowRight className="size-4" />
                    </Link>
                )}
            </div>
            <div className="overflow-hidden rounded-3xl border border-border bg-card shadow-[var(--sh-sm)]">
                {selecting
                    ? standings.top.map((row) => (
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
                    : standings.top.map((row) => (
                          <LeaderboardRow
                              key={row.rank}
                              entry={{
                                  rank: row.rank,
                                  name: row.name,
                                  initials: row.initials,
                                  avatar: row.avatar,
                                  primary: row.points,
                                  isMe: row.is_me,
                                  movement: row.movement,
                              }}
                          />
                      ))}
                {!selecting && pinnedMe && (
                    <>
                        <div className="border-t border-dashed border-border bg-muted/30 px-4 py-1 text-center text-[10px] font-bold tracking-[0.12em] text-muted-foreground uppercase">
                            You
                        </div>
                        <LeaderboardRow
                            entry={{
                                rank: pinnedMe.rank,
                                name: pinnedMe.name,
                                initials: pinnedMe.initials,
                                avatar: pinnedMe.avatar,
                                primary: pinnedMe.points,
                                isMe: true,
                                movement: pinnedMe.movement,
                            }}
                        />
                    </>
                )}
            </div>
        </section>
    );
}

/**
 * A summary card per non-Overall board: the board's leader as the headline, with the viewer's own
 * position beneath. Shown once scoring has begun. In normal mode each card deep-links to that
 * board's tab; while choosing players to compare it stays put (no link) and the leader gains a `+`
 * so its winner can be added straight from the card — the section no longer vanishes on selection.
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
    if (summaries.length === 0) {
        return null;
    }

    const atLimit = selectedIds.length >= COMPARE_LIMIT;
    const cardClass =
        'flex flex-col gap-2 rounded-2xl border border-border bg-card px-4 py-3 shadow-[var(--sh-sm)]';

    return (
        <section className="flex flex-col gap-3">
            <h2 className="font-display text-xl font-semibold tracking-tight">
                More leaderboards
            </h2>
            <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                {summaries.map((board) => {
                    const unit = board.primary_stat_label.toLowerCase();
                    const leader =
                        board.leader && board.leader.primary_value
                            ? board.leader
                            : null;

                    const body = (
                        <>
                            <div className="flex items-center justify-between">
                                <span className="text-[11px] font-bold tracking-[0.08em] text-muted-foreground uppercase">
                                    {board.label}
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
                                        No leader yet
                                    </span>
                                )}
                                <span className="flex shrink-0 items-center gap-2">
                                    {leader && (
                                        <span className="font-display text-sm font-semibold tabular-nums">
                                            {leader.primary_value?.toLocaleString()}{' '}
                                            {unit}
                                        </span>
                                    )}
                                    {selecting && leader && !leader.is_me && (
                                        <AddToggle
                                            entryId={leader.entry_id}
                                            name={leader.name}
                                            selected={selectedIds.includes(
                                                leader.entry_id,
                                            )}
                                            disabled={
                                                atLimit &&
                                                !selectedIds.includes(
                                                    leader.entry_id,
                                                )
                                            }
                                            onToggle={onToggle}
                                        />
                                    )}
                                </span>
                            </div>

                            <div className="flex items-center justify-between gap-2 border-t border-border pt-2 text-xs font-medium text-muted-foreground">
                                <span className="inline-flex items-center gap-1.5">
                                    You ·{' '}
                                    {board.you ? ordinal(board.you.rank) : '—'}
                                    {board.you?.movement && (
                                        <MovementArrow
                                            movement={board.you.movement}
                                        />
                                    )}
                                </span>
                                {board.you && (
                                    <span className="shrink-0 tabular-nums">
                                        {board.you.primary_value?.toLocaleString()}{' '}
                                        {unit}
                                    </span>
                                )}
                            </div>
                        </>
                    );

                    return selecting ? (
                        <div key={board.key} className={cardClass}>
                            {body}
                        </div>
                    ) : (
                        <Link
                            key={board.key}
                            href={`${pools.leaderboard(pool.slug).url}?board=${board.key}`}
                            className={cn(
                                'group transition-colors hover:border-primary/40',
                                cardClass,
                            )}
                        >
                            {body}
                        </Link>
                    );
                })}
            </div>
        </section>
    );
}

function metaLine(count: number, prefix: string, range: string | null): string {
    const label = `${count} ${count === 1 ? 'match' : 'matches'}`;

    return [prefix, label, range].filter(Boolean).join(' · ');
}

/** The phase-tabbed Fixtures view: group cards, knockout slot cards, and the Final card. */
function FixturesView({
    groups,
    bracket,
    comparison,
}: {
    groups: GroupView[];
    bracket: BracketPhase[];
    /** When set, each fixture renders a per-player comparison instead of the viewer's own card. */
    comparison: Comparison | null;
}) {
    const tz = useDisplayTimeZone();
    const groupMatches = groups.reduce((n, g) => n + g.fixtures.length, 0);
    const groupFixtures = groups.flatMap((g) => g.fixtures);

    const koPhases = bracket.filter(
        (p) => p.phase_key !== 'final' && p.phase_key !== 'third_place',
    );
    const finalPhase = bracket.find((p) => p.phase_key === 'final');
    const thirdPhase = bracket.find((p) => p.phase_key === 'third_place');
    const finalFixtures = [
        ...(finalPhase?.fixtures ?? []),
        ...(thirdPhase?.fixtures ?? []),
    ];

    const phases: Phase[] = [
        { id: 'gs', label: 'Group Stage', count: groupMatches },
        ...koPhases.map((p) => ({
            id: p.phase_key,
            label: p.phase_name,
            count: p.fixtures.length,
        })),
        ...(finalFixtures.length > 0
            ? [{ id: 'final', label: 'Final', count: finalFixtures.length }]
            : []),
    ];

    const [active, setActive] = useState('gs');
    const players = comparison?.players ?? [];

    return (
        <section className="flex flex-col gap-6">
            <PhaseTabs phases={phases} active={active} onSelect={setActive} />

            {active === 'gs' && (
                <div>
                    <PhaseMeta
                        title="Group Stage"
                        meta={metaLine(
                            groupMatches,
                            `${groups.length} groups`,
                            phaseDateRange(groupFixtures, tz),
                        )}
                    />
                    <div className="grid gap-4 sm:grid-cols-2 2xl:grid-cols-3">
                        {groups.map((group) =>
                            comparison ? (
                                <CompareGroupCard
                                    key={group.name}
                                    group={group}
                                    players={players}
                                    windowStatus={
                                        comparison.windows.group ?? 'pending'
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
                </div>
            )}

            {koPhases.map(
                (phase) =>
                    active === phase.phase_key && (
                        <div key={phase.phase_key}>
                            <PhaseMeta
                                title={phase.phase_name}
                                meta={metaLine(
                                    phase.fixtures.length,
                                    '',
                                    phaseDateRange(phase.fixtures, tz),
                                )}
                            />
                            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                                {phase.fixtures.map((fixture) =>
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
                        </div>
                    ),
            )}

            {active === 'final' && (
                <div>
                    <PhaseMeta
                        title="Final & Third Place"
                        meta={metaLine(
                            finalFixtures.length,
                            '',
                            phaseDateRange(finalFixtures, tz),
                        )}
                    />
                    <div className="flex flex-col gap-4">
                        {finalPhase?.fixtures.map((fixture) =>
                            comparison ? (
                                <CompareFinalCard
                                    key={fixture.match_number}
                                    fixture={fixture}
                                    players={players}
                                    windowStatus={
                                        comparison.windows[
                                            finalPhase.phase_key
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
                        {thirdPhase?.fixtures.map((fixture) => (
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
                                                thirdPhase.phase_key
                                            ] ?? 'pending'
                                        }
                                    />
                                ) : (
                                    <KnockoutSlotCard fixture={fixture} />
                                )}
                            </div>
                        ))}
                    </div>
                </div>
            )}
        </section>
    );
}

export default function PoolShow({
    pool,
    groups,
    bracket,
    standings,
    boardSummaries,
    players,
    comparison,
    attention,
}: PoolShowProps) {
    const { auth } = usePage().props;

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
            <Head title={poolTitle(pool.source, pool.name)} />
            <div className="flex h-full flex-1 flex-col gap-10 p-4 sm:p-6 lg:p-8">
                <DashboardBanner
                    pool={pool}
                    standings={standings}
                    isAdmin={auth.isAdmin}
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
                    <PoolPreview
                        pool={pool}
                        standings={standings}
                        selecting={selecting}
                        selectedIds={selectedIds}
                        onToggle={toggleSelect}
                    />
                )}

                {!compareActive && standings.has_scores && (
                    <BoardSummaries
                        pool={pool}
                        summaries={boardSummaries}
                        selecting={selecting}
                        selectedIds={selectedIds}
                        onToggle={toggleSelect}
                    />
                )}

                <FixturesView
                    groups={groups}
                    bracket={bracket}
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
