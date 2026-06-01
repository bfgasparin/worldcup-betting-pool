import { Head } from '@inertiajs/react';
import { ListOrdered, Trophy, Users } from 'lucide-react';
import { LeaderboardRow } from '@/components/leaderboard-row';
import games from '@/routes/games';
import type { LeaderboardPageProps } from '@/types/games';
import type { BreadcrumbItem } from '@/types/navigation';

export default function Leaderboard({
    game,
    rows,
    has_scores,
}: LeaderboardPageProps) {
    const participants = rows.length;

    return (
        <>
            <Head title={`Pool table — ${game.name}`} />
            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <header className="hero relative overflow-hidden rounded-3xl border border-border p-8">
                    <div className="hero-lines" />
                    <div className="relative flex flex-col gap-3">
                        <span className="inline-flex items-center gap-2 text-xs font-bold tracking-[0.14em] text-muted-foreground uppercase">
                            <ListOrdered className="size-4 text-primary" />
                            Pool table
                        </span>
                        <h1 className="text-4xl font-semibold tracking-tight text-foreground sm:text-5xl">
                            The standings
                        </h1>
                        <p className="inline-flex items-center gap-2 text-sm text-muted-foreground">
                            <Users className="size-4" />
                            {participants}{' '}
                            {participants === 1 ? 'player' : 'players'} ·{' '}
                            {game.name}
                        </p>
                    </div>
                </header>

                {!has_scores && (
                    <div className="flex items-start gap-4 rounded-3xl border border-accent/30 bg-accent/[0.08] p-5">
                        <div className="app-icon app-icon--gold grid size-11 shrink-0 place-items-center rounded-2xl">
                            <Trophy className="size-5 text-[#3a2600]" />
                        </div>
                        <div>
                            <p className="font-display text-base font-semibold">
                                The table is warming up
                            </p>
                            <p className="mt-1 text-sm text-muted-foreground">
                                Points land as match results come in —
                                predictions lock at kick-off. Here's everyone in
                                the pool so far.
                            </p>
                        </div>
                    </div>
                )}

                {participants > 0 ? (
                    <div className="overflow-hidden rounded-3xl border border-border bg-card shadow-[var(--sh-sm)]">
                        {rows.map((row) => (
                            <LeaderboardRow
                                key={row.rank}
                                entry={{
                                    rank: row.rank,
                                    name: row.name,
                                    initials: row.initials,
                                    points: row.points,
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
                            Predictions create a pool entry — be the first to
                            lock in your scorelines.
                        </p>
                    </div>
                )}
            </div>
        </>
    );
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Tournaments', href: games.index() },
];

Leaderboard.layout = { breadcrumbs };
