import { Head } from '@inertiajs/react';
import { ListOrdered, Scale, Trophy, Users } from 'lucide-react';
import { useState } from 'react';
import { GameIdentity } from '@/components/game-identity';
import { LeaderboardRow } from '@/components/leaderboard-row';
import { gameTitle } from '@/lib/game-title';
import { tiebreakRule } from '@/lib/leaderboards';
import { cn } from '@/lib/utils';
import games from '@/routes/games';
import type {
    LeaderboardBoard,
    LeaderboardCategoryKey,
    LeaderboardPageProps,
} from '@/types/games';
import type { BreadcrumbItem } from '@/types/navigation';

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

/** The board's secondary stat, formatted with its label (e.g. "27 team goals"). */
function secondaryStat(
    board: LeaderboardBoard,
    value: number | null,
): string | null {
    if (board.secondary_stat_label === null || value === null) {
        return null;
    }

    return `${value} ${board.secondary_stat_label.toLowerCase()}`;
}

export default function Leaderboard({
    game,
    boards,
    active_board,
}: LeaderboardPageProps) {
    const [active, setActive] = useState<LeaderboardCategoryKey>(
        active_board ?? boards[0]?.key ?? 'overall',
    );
    const board = boards.find((b) => b.key === active) ?? boards[0];
    const participants = board?.rows.length ?? 0;

    return (
        <>
            <Head title={gameTitle(game.source, game.name, 'Leaderboards')} />
            <div className="flex h-full flex-1 flex-col gap-6 p-4 sm:p-6 lg:p-8">
                <header className="hero relative overflow-hidden rounded-3xl border border-border p-8">
                    <div className="hero-lines" />
                    <div className="relative flex flex-col gap-3">
                        <span className="inline-flex items-center gap-2 text-xs font-bold tracking-[0.14em] text-muted-foreground uppercase">
                            <ListOrdered className="size-4 text-primary" />
                            Leaderboards
                        </span>
                        <h1 className="text-4xl font-semibold tracking-tight text-foreground sm:text-5xl">
                            {board?.label ?? 'Leaderboards'}
                        </h1>
                        <p className="inline-flex items-center gap-2 text-sm text-muted-foreground">
                            <Users className="size-4" />
                            {participants}{' '}
                            {participants === 1 ? 'player' : 'players'}
                        </p>
                        <GameIdentity
                            source={game.source}
                            name={game.name}
                            scoringLabel={game.scoring_label}
                            accent={game.accent}
                            className="mt-1"
                        />
                    </div>
                </header>

                <BoardTabs
                    boards={boards}
                    active={active}
                    onSelect={setActive}
                />

                {board && (
                    <>
                        <div className="flex flex-col gap-1.5">
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
                                        everyone in the pool so far.
                                    </p>
                                </div>
                            </div>
                        )}

                        {participants > 0 ? (
                            <div className="overflow-hidden rounded-3xl border border-border bg-card shadow-[var(--sh-sm)]">
                                {board.rows.map((row) => (
                                    <LeaderboardRow
                                        key={row.rank}
                                        entry={{
                                            rank: row.rank,
                                            name: row.name,
                                            initials: row.initials,
                                            avatar: row.avatar,
                                            primary: row.primary_value,
                                            secondary: secondaryStat(
                                                board,
                                                row.secondary_value,
                                            ),
                                            isMe: row.is_me,
                                            movement: row.movement,
                                        }}
                                    />
                                ))}
                            </div>
                        ) : (
                            <div className="flex min-h-44 flex-col items-center justify-center gap-2 rounded-3xl border border-dashed border-border p-8 text-center">
                                <Users className="size-6 text-muted-foreground" />
                                <p className="font-display font-semibold">
                                    No players yet
                                </p>
                                <p className="max-w-sm text-sm text-muted-foreground">
                                    Predictions create a pool entry — be the
                                    first to lock in your scorelines.
                                </p>
                            </div>
                        )}
                    </>
                )}
            </div>
        </>
    );
}

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Games', href: games.index() }];

Leaderboard.layout = { breadcrumbs };
