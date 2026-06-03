import { Head, Link, usePage } from '@inertiajs/react';
import {
    ArrowRight,
    CalendarClock,
    CalendarDays,
    ClipboardCheck,
    PencilLine,
    Users,
} from 'lucide-react';
import { useState } from 'react';
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
import { GameIdentity } from '@/components/game-identity';
import { GameInfoDialog } from '@/components/game-info-dialog';
import { LeaderboardRow } from '@/components/leaderboard-row';
import { MovementArrow } from '@/components/movement-arrow';
import { Button } from '@/components/ui/button';
import { useDisplayTimeZone } from '@/hooks/use-timezone';
import { gameTitle } from '@/lib/game-title';
import { ordinal } from '@/lib/leaderboards';
import games from '@/routes/games';
import type {
    BoardSummary,
    BracketPhase,
    GameDetail,
    GroupView,
    PoolSummary,
} from '@/types/games';

interface GameShowProps {
    game: GameDetail;
    groups: GroupView[];
    bracket: BracketPhase[];
    pool: PoolSummary;
    boardSummaries: BoardSummary[];
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
}: {
    game: GameDetail;
    pool: PoolSummary;
    isAdmin: boolean;
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

function PoolPreview({ game, pool }: { game: GameDetail; pool: PoolSummary }) {
    if (pool.top.length === 0) {
        return null;
    }

    // Pin the viewer's own row when they're ranked outside the shown top, so they always see where
    // they stand on the Overall board.
    const pinnedMe =
        pool.me && !pool.top.some((row) => row.is_me) ? pool.me : null;

    return (
        <section className="flex flex-col gap-3">
            <div className="flex items-center justify-between">
                <h2 className="font-display text-xl font-semibold tracking-tight">
                    Overall
                </h2>
                <Link
                    href={games.leaderboard(game.slug)}
                    className="inline-flex items-center gap-1 font-display text-sm font-semibold text-primary transition-all hover:gap-2"
                >
                    See all leaderboards
                    <ArrowRight className="size-4" />
                </Link>
            </div>
            <div className="overflow-hidden rounded-3xl border border-border bg-card shadow-[var(--sh-sm)]">
                {pool.top.map((row) => (
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
                {pinnedMe && (
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
 * position beneath. Shown once scoring has begun; each card deep-links to that board's tab.
 */
function BoardSummaries({
    game,
    summaries,
}: {
    game: GameDetail;
    summaries: BoardSummary[];
}) {
    if (summaries.length === 0) {
        return null;
    }

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

                    return (
                        <Link
                            key={board.key}
                            href={`${games.leaderboard(game.slug).url}?board=${board.key}`}
                            className="group flex flex-col gap-2 rounded-2xl border border-border bg-card px-4 py-3 shadow-[var(--sh-sm)] transition-colors hover:border-primary/40"
                        >
                            <div className="flex items-center justify-between">
                                <span className="text-[11px] font-bold tracking-[0.08em] text-muted-foreground uppercase">
                                    {board.label}
                                </span>
                                <ArrowRight className="size-4 text-muted-foreground transition-all group-hover:translate-x-0.5 group-hover:text-primary" />
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
                                {leader && (
                                    <span className="shrink-0 font-display text-sm font-semibold tabular-nums">
                                        {leader.primary_value?.toLocaleString()}{' '}
                                        {unit}
                                    </span>
                                )}
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
}: {
    groups: GroupView[];
    bracket: BracketPhase[];
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
                        {groups.map((group) => (
                            <GroupFixtureCard
                                key={group.name}
                                name={group.name}
                                teams={group.teams}
                                fixtures={group.fixtures}
                                standings={group.standings}
                                predictedStandings={group.predicted_standings}
                            />
                        ))}
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
                                {phase.fixtures.map((fixture) => (
                                    <KnockoutSlotCard
                                        key={fixture.match_number}
                                        fixture={fixture}
                                    />
                                ))}
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
                        {finalPhase?.fixtures.map((fixture) => (
                            <FinalCard
                                key={fixture.match_number}
                                fixture={fixture}
                            />
                        ))}
                        {thirdPhase?.fixtures.map((fixture) => (
                            <div
                                key={fixture.match_number}
                                className="mx-auto w-full max-w-xl"
                            >
                                <KnockoutSlotCard fixture={fixture} />
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
}: GameShowProps) {
    const { auth } = usePage().props;

    return (
        <>
            <Head title={gameTitle(game.source, game.name)} />
            <div className="flex h-full flex-1 flex-col gap-10 p-4">
                <DashboardBanner
                    game={game}
                    pool={pool}
                    isAdmin={auth.isAdmin}
                />

                <PoolPreview game={game} pool={pool} />

                {pool.has_scores && (
                    <BoardSummaries game={game} summaries={boardSummaries} />
                )}

                <FixturesView groups={groups} bracket={bracket} />
            </div>
        </>
    );
}

GameShow.layout = {
    breadcrumbs: [{ title: 'Games', href: games.index() }],
};
