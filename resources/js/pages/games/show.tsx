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
import {
    FinalCard,
    formatLongDate,
    GroupFixtureCard,
    KnockoutSlotCard,
    PhaseMeta,
    PhaseTabs,
    phaseDateRange,
} from '@/components/fixtures';
import type { Phase } from '@/components/fixtures';
import {
    CompareFinalCard,
    CompareGroupCard,
    CompareKnockoutCard,
} from '@/components/fixtures-compare';
import { GameIdentity } from '@/components/game-identity';
import { GameInfoDialog } from '@/components/game-info-dialog';
import { LeaderboardRow } from '@/components/leaderboard-row';
import { MovementArrow } from '@/components/movement-arrow';
import { Button } from '@/components/ui/button';
import { useDisplayTimeZone } from '@/hooks/use-timezone';
import { COMPARE_LIMIT } from '@/lib/compare';
import { gameTitle } from '@/lib/game-title';
import { ordinal } from '@/lib/leaderboards';
import { cn } from '@/lib/utils';
import games from '@/routes/games';
import type {
    BoardSummary,
    BracketPhase,
    Comparison,
    GameDetail,
    GroupView,
    LeaderboardEntryRow,
    PlayerDirectoryEntry,
    PoolSummary,
} from '@/types/games';

interface GameShowProps {
    game: GameDetail;
    groups: GroupView[];
    bracket: BracketPhase[];
    pool: PoolSummary;
    boardSummaries: BoardSummary[];
    /** Every entry in the pool, for the "Add player" comparison picker. */
    players: PlayerDirectoryEntry[];
    /** The head-to-head payload when the page is in compare mode (a ?compare= list); else null. */
    comparison: Comparison | null;
}

/**
 * The hero's one-line context, by state: not entered yet, predictions still open (lock date),
 * or locked but not yet scored. Null once results are landing — the standings carry it from there.
 */
function heroContextLine(
    game: GameDetail,
    hasEntry: boolean,
    hasScores: boolean,
    tz: string,
): string | null {
    if (!hasEntry) {
        return "You're not in yet — make your predictions.";
    }

    if (
        game.predictions_lock_at &&
        new Date(game.predictions_lock_at).getTime() > Date.now()
    ) {
        return `Predictions lock ${formatLongDate(game.predictions_lock_at, tz)}.`;
    }

    if (!hasScores) {
        return 'Locked in — points unlock as results land.';
    }

    return null;
}

function DashboardBanner({
    game,
    pool,
    isAdmin,
    canCompare,
    onCompare,
}: {
    game: GameDetail;
    pool: PoolSummary;
    isAdmin: boolean;
    canCompare: boolean;
    onCompare: () => void;
}) {
    const dates = game.starts_on
        ? game.ends_on
            ? `${game.starts_on} – ${game.ends_on}`
            : game.starts_on
        : null;

    const tz = useDisplayTimeZone();
    const hasEntry = pool.me !== null;
    const contextLine = heroContextLine(game, hasEntry, pool.has_scores, tz);

    return (
        <header className="hero relative overflow-hidden rounded-3xl border border-border p-6 sm:p-8">
            <div className="hero-lines" />
            <div className="relative flex flex-col gap-6">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <GameIdentity
                            variant="banner"
                            source={game.source}
                            scoringLabel={game.scoring_label}
                            accent={game.accent}
                            className="mb-3"
                        />
                        <h1 className="text-3xl font-semibold tracking-tight text-balance text-foreground sm:text-4xl">
                            {game.name}
                        </h1>
                        <div className="mt-2 flex flex-wrap items-center gap-3 text-sm text-muted-foreground">
                            <span className="inline-flex items-center rounded-full bg-muted px-2.5 py-0.5 text-xs font-semibold capitalize">
                                {game.status.replace('_', ' ')}
                            </span>
                            <span className="capitalize">{game.sport}</span>
                            {dates && (
                                <span className="inline-flex items-center gap-1.5">
                                    <CalendarDays className="size-4" />
                                    {dates}
                                </span>
                            )}
                            <span className="inline-flex items-center gap-1.5">
                                <Users className="size-4" />
                                {pool.participants}{' '}
                                {pool.participants === 1 ? 'player' : 'players'}
                            </span>
                        </div>
                        {contextLine && (
                            <p className="mt-2 text-sm font-medium text-foreground">
                                {contextLine}
                            </p>
                        )}
                    </div>
                    <GameInfoDialog game={game} />
                </div>

                <div className="flex flex-wrap items-center gap-3">
                    <Button asChild>
                        <Link href={games.predict.edit(game.slug)}>
                            <PencilLine className="size-4" />
                            {hasEntry
                                ? 'Edit predictions'
                                : 'Make your predictions'}
                        </Link>
                    </Button>
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
                    {game.can_review_scores && (
                        <Button asChild variant="outline">
                            <Link href={games.scores.review(game.slug)}>
                                <ClipboardCheck className="size-4" />
                                Review scores
                            </Link>
                        </Button>
                    )}
                    {isAdmin && (
                        <Button asChild variant="outline">
                            <Link href={games.schedule.index(game.slug)}>
                                <CalendarClock className="size-4" />
                                Manage schedule
                            </Link>
                        </Button>
                    )}
                </div>
            </div>
        </header>
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
            <span className="bg-brand-gradient grid size-9 shrink-0 place-items-center rounded-full font-display text-sm font-semibold text-white">
                {row.initials}
            </span>
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
    game,
    pool,
    selecting,
    selectedIds,
    onToggle,
}: {
    game: GameDetail;
    pool: PoolSummary;
    selecting: boolean;
    selectedIds: number[];
    onToggle: (entryId: number) => void;
}) {
    if (pool.top.length === 0) {
        return null;
    }

    // Pin the viewer's own row when they're ranked outside the shown top, so they always see where
    // they stand on the Overall board.
    const pinnedMe =
        pool.me && !pool.top.some((row) => row.is_me) ? pool.me : null;
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
                        href={games.leaderboard(game.slug)}
                        className="inline-flex items-center gap-1 font-display text-sm font-semibold text-primary transition-all hover:gap-2"
                    >
                        See all leaderboards
                        <ArrowRight className="size-4" />
                    </Link>
                )}
            </div>
            <div className="overflow-hidden rounded-3xl border border-border bg-card shadow-[var(--sh-sm)]">
                {selecting
                    ? pool.top.map((row) => (
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
                    : pool.top.map((row) => (
                          <LeaderboardRow
                              key={row.rank}
                              entry={{
                                  rank: row.rank,
                                  name: row.name,
                                  initials: row.initials,
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
    game,
    summaries,
    selecting,
    selectedIds,
    onToggle,
}: {
    game: GameDetail;
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
                                        <span className="bg-gold-gradient grid size-7 shrink-0 place-items-center rounded-full font-display text-xs font-semibold text-[#3a2600]">
                                            {leader.initials}
                                        </span>
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
                            href={`${games.leaderboard(game.slug).url}?board=${board.key}`}
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

export default function GameShow({
    game,
    groups,
    bracket,
    pool,
    boardSummaries,
    players,
    comparison,
}: GameShowProps) {
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
        router.visit(games.show(game.slug).url, {
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
            <Head title={gameTitle(game.source, game.name)} />
            <div className="flex h-full flex-1 flex-col gap-10 p-4">
                <DashboardBanner
                    game={game}
                    pool={pool}
                    isAdmin={auth.isAdmin}
                    canCompare={
                        !compareActive && !selecting && pool.participants > 1
                    }
                    onCompare={startSelecting}
                />

                {compareActive ? (
                    <CompareStrip
                        players={comparison.players}
                        windows={comparison.windows}
                        boards={game.leaderboards}
                        onRemove={removeLane}
                    />
                ) : (
                    <PoolPreview
                        game={game}
                        pool={pool}
                        selecting={selecting}
                        selectedIds={selectedIds}
                        onToggle={toggleSelect}
                    />
                )}

                {!compareActive && pool.has_scores && (
                    <BoardSummaries
                        game={game}
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

GameShow.layout = {
    breadcrumbs: [{ title: 'Games', href: games.index() }],
};
