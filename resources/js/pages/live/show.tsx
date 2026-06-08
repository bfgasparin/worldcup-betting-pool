import { Head, usePage } from '@inertiajs/react';
import { Radio } from 'lucide-react';
import { useState } from 'react';
import { Flag } from '@/components/flag';
import { LeaderboardRow } from '@/components/leaderboard-row';
import type { LeaderboardEntry } from '@/components/leaderboard-row';
import { LiveBadge, LivePulse } from '@/components/live-badge';
import { MovementArrow } from '@/components/movement-arrow';
import { PoolIdentity } from '@/components/pool-identity';
import { SegmentedTabs } from '@/components/ui/segmented-tabs';
import { useLivePoll } from '@/hooks/use-live-poll';
import { resolveAccent, sourceMonogram } from '@/lib/accents';
import { formatMoney } from '@/lib/money';
import { cn } from '@/lib/utils';
import live from '@/routes/live';
import type {
    LiveBoardDescriptor,
    LiveFixture,
    LivePool,
    ProjectedRow,
} from '@/types/live';

interface LiveShowProps {
    tournament: { name: string; slug: string };
    boards: LiveBoardDescriptor[];
    pools: LivePool[];
    liveFixtures: LiveFixture[];
    poll_interval_ms: number;
}

// Stable reference: only the live-changing props are re-fetched each poll.
const LIVE_ONLY = ['liveFixtures', 'pools'];

/** "2 – 1", or a dash placeholder before a score is entered. */
function score(value: number | null): string {
    return value === null ? '–' : String(value);
}

function TeamSide({
    name,
    label,
    flagTeam,
    align,
}: {
    name: string | null;
    label: string | null;
    flagTeam: LiveFixture['home_team'];
    align: 'start' | 'end';
}) {
    return (
        <div
            className={cn(
                'flex min-w-0 flex-1 items-center gap-2',
                align === 'end' && 'flex-row-reverse text-right',
            )}
        >
            <Flag team={flagTeam} className="size-5" />
            <span className="truncate font-display text-sm font-semibold sm:text-base">
                {name ?? label ?? 'TBD'}
            </span>
        </div>
    );
}

/** One live match: big scoreline, flags, and a pulsing LIVE / muted FT marker. */
function LiveFixtureCard({ fixture }: { fixture: LiveFixture }) {
    const ended = fixture.status === 'ended';

    return (
        <div
            className={cn(
                'card-elevated flex flex-col gap-3 rounded-2xl border border-border p-4',
                !ended && 'ring-1 ring-red-500/20',
            )}
        >
            <div className="flex items-center justify-between">
                {ended ? (
                    <LiveBadge label="Full time" tone="ft" />
                ) : (
                    <LiveBadge />
                )}
                {fixture.is_knockout && (
                    <span className="font-display text-[0.65rem] font-bold tracking-[0.14em] text-muted-foreground uppercase">
                        Knockout
                    </span>
                )}
            </div>

            <div className="flex items-center gap-3">
                <TeamSide
                    name={fixture.home_team?.name ?? null}
                    label={fixture.home_label}
                    flagTeam={fixture.home_team}
                    align="start"
                />
                <div className="flex shrink-0 items-center gap-2 font-display text-2xl font-bold tabular-nums">
                    <span>{score(fixture.home_goals)}</span>
                    <span className="text-muted-foreground">:</span>
                    <span>{score(fixture.away_goals)}</span>
                </div>
                <TeamSide
                    name={fixture.away_team?.name ?? null}
                    label={fixture.away_label}
                    flagTeam={fixture.away_team}
                    align="end"
                />
            </div>

            {ended && (
                <p className="text-center text-xs text-muted-foreground">
                    Awaiting official confirmation
                </p>
            )}
        </div>
    );
}

function LiveScoreboard({ fixtures }: { fixtures: LiveFixture[] }) {
    if (fixtures.length === 0) {
        return (
            <div className="card-elevated flex flex-col items-center gap-2 rounded-2xl border border-border p-10 text-center">
                <Radio className="size-8 text-muted-foreground" />
                <p className="font-display font-semibold">
                    No matches are live right now
                </p>
                <p className="text-sm text-muted-foreground">
                    This page lights up the moment a match kicks off.
                </p>
            </div>
        );
    }

    return (
        <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
            {fixtures.map((fixture) => (
                <LiveFixtureCard key={fixture.id} fixture={fixture} />
            ))}
        </div>
    );
}

/** The viewer's own projected line — the focal "what would happen to me" card. */
function ViewerProjection({
    row,
    pool,
    primaryLabel,
}: {
    row: ProjectedRow;
    pool: LivePool;
    primaryLabel: string;
}) {
    return (
        <div className="flex flex-wrap items-center justify-between gap-4 rounded-2xl bg-pitch-deep p-5 text-white">
            <div className="flex items-center gap-4">
                <div className="flex flex-col">
                    <span className="text-[0.7rem] font-bold tracking-[0.14em] text-white/60 uppercase">
                        Your projected spot
                    </span>
                    <div className="flex items-center gap-2">
                        <span className="font-display text-3xl font-bold tabular-nums">
                            #{row.rank}
                        </span>
                        {row.movement != null && (
                            <MovementArrow
                                movement={row.movement}
                                delta={row.movement_delta}
                                size="md"
                            />
                        )}
                    </div>
                </div>
            </div>

            <div className="flex flex-wrap items-center gap-x-6 gap-y-2">
                <div className="flex flex-col">
                    <span className="text-[0.7rem] font-bold tracking-[0.14em] text-white/60 uppercase">
                        {primaryLabel}
                    </span>
                    <span className="font-display text-xl font-bold tabular-nums">
                        {row.primary_value.toLocaleString()}
                    </span>
                </div>

                {pool.is_paid && row.projected_prize != null && (
                    <div className="flex flex-col">
                        <span className="text-[0.7rem] font-bold tracking-[0.14em] text-white/60 uppercase">
                            Projected prize
                        </span>
                        <span className="bg-gold-gradient w-fit rounded-full px-2.5 py-0.5 font-display text-sm font-bold text-[#3a2600] tabular-nums">
                            {formatMoney(row.projected_prize, pool.currency)}
                        </span>
                    </div>
                )}

                {row.pending_bonus > 0 && (
                    <div className="flex flex-col">
                        <span className="text-[0.7rem] font-bold tracking-[0.14em] text-white/60 uppercase">
                            If it holds
                        </span>
                        <span className="font-display text-sm font-bold text-amber-300 tabular-nums">
                            +{row.pending_bonus} pending
                        </span>
                    </div>
                )}
            </div>
        </div>
    );
}

function ProjectedBoard({
    pool,
    board,
    meId,
}: {
    pool: LivePool;
    board: LiveBoardDescriptor;
    meId: number;
}) {
    const rows = pool.boards[board.key] ?? [];
    const mine = rows.find((row) => row.user_id === meId);

    const toEntry = (row: ProjectedRow): LeaderboardEntry => ({
        rank: row.rank,
        name: row.user_id === meId ? 'You' : row.name,
        initials: row.initials,
        avatar: row.avatar,
        primary: row.primary_value,
        secondary:
            board.secondary_stat_label && row.secondary_value != null
                ? `${row.secondary_value} ${board.secondary_stat_label}`
                : null,
        isMe: row.user_id === meId,
        movement: row.movement,
        movementDelta: row.movement_delta,
        prize:
            board.awards_prizes && pool.is_paid && row.projected_prize != null
                ? formatMoney(row.projected_prize, pool.currency)
                : null,
    });

    return (
        <div className="flex flex-col gap-4">
            {mine && (
                <ViewerProjection
                    row={mine}
                    pool={pool}
                    primaryLabel={board.primary_stat_label}
                />
            )}

            <p className="text-sm text-muted-foreground">{board.description}</p>

            {rows.length > 0 ? (
                <div className="card-elevated overflow-hidden rounded-2xl border border-border">
                    {rows.map((row) => (
                        <LeaderboardRow
                            key={row.entry_id}
                            entry={toEntry(row)}
                        />
                    ))}
                </div>
            ) : (
                <p className="rounded-2xl border border-dashed border-border p-8 text-center text-sm text-muted-foreground">
                    No players to project yet.
                </p>
            )}
        </div>
    );
}

function PoolLiveSection({
    pool,
    boards,
    meId,
}: {
    pool: LivePool;
    boards: LiveBoardDescriptor[];
    meId: number;
}) {
    const [boardKey, setBoardKey] = useState(boards[0]?.key ?? 'overall');
    const board = boards.find((item) => item.key === boardKey) ?? boards[0];

    return (
        <section className="flex flex-col gap-4">
            <div className="flex flex-wrap items-center justify-between gap-3">
                <PoolIdentity
                    source={pool.source}
                    name={pool.name}
                    scoringLabel={pool.scoring_label}
                    accent={pool.accent}
                    variant="banner"
                />
                <span className="font-display text-xs font-semibold text-muted-foreground">
                    Projected · if scores hold
                </span>
            </div>

            <SegmentedTabs
                aria-label="Projected leaderboard"
                items={boards.map((item) => ({
                    value: item.key,
                    label: item.label,
                }))}
                value={board.key}
                onChange={setBoardKey}
                size="sm"
            />

            <ProjectedBoard pool={pool} board={board} meId={meId} />
        </section>
    );
}

export default function LiveShow({
    tournament,
    boards,
    pools,
    liveFixtures,
    poll_interval_ms,
}: LiveShowProps) {
    const page = usePage();
    const meId = page.props.auth.user?.id ?? 0;
    const [poolSlug, setPoolSlug] = useState(pools[0]?.slug ?? '');
    const selectedPool =
        pools.find((pool) => pool.slug === poolSlug) ?? pools[0] ?? null;

    useLivePoll({
        intervalMs: poll_interval_ms,
        active: liveFixtures.length > 0,
        only: LIVE_ONLY,
    });

    const liveCount = liveFixtures.filter(
        (fixture) => fixture.status === 'live',
    ).length;

    return (
        <>
            <Head title={`${tournament.name} · Live`} />
            <div className="relative min-h-full bg-background">
                <div className="w-full px-4 py-6 sm:px-6 sm:py-8 lg:px-8 xl:px-10">
                    <header className="hero relative mb-6 overflow-hidden rounded-3xl border border-border p-5 sm:mb-8 sm:p-8">
                        <div className="hero-lines" />
                        <div className="relative flex flex-wrap items-end justify-between gap-4">
                            <div className="flex flex-col gap-3">
                                <span className="inline-flex w-fit items-center gap-2 text-xs font-bold tracking-[0.14em] text-muted-foreground uppercase">
                                    <LivePulse />
                                    Live Center
                                </span>
                                <h1 className="text-3xl font-semibold tracking-tight text-balance text-foreground sm:text-5xl">
                                    {tournament.name}
                                </h1>
                                <span className="bg-gold-gradient mt-1 h-1 w-12 rounded-full" />
                                <p className="text-sm font-semibold text-muted-foreground">
                                    {liveCount > 0
                                        ? `${liveCount} match${liveCount === 1 ? '' : 'es'} live now — watch your standings move in real time.`
                                        : 'No matches live at the moment.'}
                                </p>
                            </div>
                        </div>
                    </header>

                    <div className="flex flex-col gap-8">
                        <section className="flex flex-col gap-4">
                            <h2 className="font-display text-lg font-semibold">
                                Live scores
                            </h2>
                            <LiveScoreboard fixtures={liveFixtures} />
                        </section>

                        {pools.length > 1 && (
                            <SegmentedTabs
                                aria-label="Your pools"
                                items={pools.map((pool) => {
                                    const kit = resolveAccent(pool.accent);

                                    return {
                                        value: pool.slug,
                                        label: (
                                            <span className="inline-flex items-center gap-1.5">
                                                <span
                                                    className={cn(
                                                        'flex size-4 shrink-0 items-center justify-center rounded font-display text-[0.5rem] leading-none font-bold',
                                                        kit.railClass,
                                                        kit.textClass,
                                                    )}
                                                    aria-hidden
                                                >
                                                    {sourceMonogram(
                                                        pool.source,
                                                    )}
                                                </span>
                                                {pool.name}
                                            </span>
                                        ),
                                    };
                                })}
                                value={selectedPool?.slug ?? ''}
                                onChange={setPoolSlug}
                            />
                        )}

                        {selectedPool ? (
                            <PoolLiveSection
                                key={selectedPool.slug}
                                pool={selectedPool}
                                boards={boards}
                                meId={meId}
                            />
                        ) : (
                            <p className="text-sm text-muted-foreground">
                                Join a pool over this tournament to follow your
                                live standings.
                            </p>
                        )}
                    </div>
                </div>
            </div>
        </>
    );
}

LiveShow.layout = {
    breadcrumbs: [{ title: 'Live', href: live.index() }],
};
