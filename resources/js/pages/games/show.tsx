import { Head, Link, usePage } from '@inertiajs/react';
import {
    ArrowRight,
    CalendarDays,
    ListOrdered,
    PencilLine,
} from 'lucide-react';
import { Flag } from '@/components/flag';
import { LeaderboardRow } from '@/components/leaderboard-row';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import games from '@/routes/games';
import type {
    BracketFixture,
    BracketPhase,
    GameDetail,
    GroupFixture,
    GroupView,
    PoolSummary,
    TeamRef,
} from '@/types/games';

interface GameShowProps {
    game: GameDetail;
    groups: GroupView[];
    bracket: BracketPhase[];
    pool: PoolSummary;
}

function teamName(team: TeamRef | null, fallback: string | null): string {
    return team?.name ?? fallback ?? 'TBD';
}

function greeting(): string {
    const hour = new Date().getHours();

    if (hour < 12) {
        return 'Good morning';
    }

    if (hour < 18) {
        return 'Good afternoon';
    }

    return 'Good evening';
}

function Score({ home, away }: { home: number | null; away: number | null }) {
    if (home === null || away === null) {
        return <span className="text-muted-foreground tabular-nums">–</span>;
    }

    return (
        <span className="font-display font-semibold tabular-nums">
            {home}–{away}
        </span>
    );
}

function PoolStat({
    label,
    value,
    accent,
}: {
    label: string;
    value: string;
    accent?: boolean;
}) {
    return (
        <div className="flex flex-col gap-0.5">
            <span className="text-[11px] font-bold tracking-[0.08em] text-muted-foreground uppercase">
                {label}
            </span>
            <span
                className={cn(
                    'font-display text-3xl font-semibold tabular-nums',
                    accent ? 'text-primary' : 'text-foreground',
                )}
            >
                {value}
            </span>
        </div>
    );
}

function DashboardBanner({
    game,
    pool,
    name,
}: {
    game: GameDetail;
    pool: PoolSummary;
    name: string;
}) {
    const dates = game.starts_on
        ? game.ends_on
            ? `${game.starts_on} – ${game.ends_on}`
            : game.starts_on
        : null;

    const firstName = name.split(' ')[0] || name;
    const hasEntry = pool.me !== null;

    return (
        <header className="hero relative overflow-hidden rounded-3xl border border-border p-6 sm:p-8">
            <div className="hero-lines" />
            <div className="relative flex flex-col gap-6">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <span className="text-sm font-semibold text-muted-foreground">
                            {greeting()}, {firstName} 👋
                        </span>
                        <h1 className="mt-1 text-3xl font-semibold tracking-tight text-balance text-foreground sm:text-4xl">
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
                        </div>
                    </div>
                </div>

                <div className="flex flex-wrap items-center gap-8 rounded-2xl border border-border bg-card px-5 py-4">
                    <PoolStat
                        label="Your points"
                        value={
                            pool.me?.points != null
                                ? pool.me.points.toLocaleString()
                                : '—'
                        }
                    />
                    <PoolStat
                        label="Pool rank"
                        value={hasEntry ? `${pool.me?.rank}` : '—'}
                        accent
                    />
                    <PoolStat label="Players" value={`${pool.participants}`} />
                    {!pool.has_scores && (
                        <span className="text-xs text-muted-foreground">
                            Points unlock as results land
                        </span>
                    )}
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
                    <Button asChild variant="outline">
                        <Link href={games.leaderboard(game.slug)}>
                            <ListOrdered className="size-4" />
                            View pool table
                        </Link>
                    </Button>
                </div>
            </div>
        </header>
    );
}

function PoolPreview({ game, pool }: { game: GameDetail; pool: PoolSummary }) {
    if (pool.top.length === 0) {
        return null;
    }

    return (
        <section className="flex flex-col gap-3">
            <div className="flex items-center justify-between">
                <h2 className="font-display text-xl font-semibold tracking-tight">
                    Pool table
                </h2>
                <Link
                    href={games.leaderboard(game.slug)}
                    className="inline-flex items-center gap-1 font-display text-sm font-semibold text-primary transition-all hover:gap-2"
                >
                    See full table
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
                            points: row.points,
                            isMe: row.is_me,
                        }}
                    />
                ))}
            </div>
        </section>
    );
}

function GroupCard({ group }: { group: GroupView }) {
    return (
        <div className="card-elevated overflow-hidden rounded-3xl">
            <div className="bg-brand-gradient px-5 py-3">
                <h3 className="font-display text-sm font-semibold tracking-wide text-white uppercase">
                    Group {group.name}
                </h3>
            </div>
            <div className="flex flex-col gap-4 p-5">
                <ul className="flex flex-col gap-2 text-sm">
                    {group.teams.map((team) => (
                        <li
                            key={team.id}
                            className="flex items-center justify-between gap-2"
                        >
                            <span className="flex min-w-0 items-center gap-2">
                                <Flag team={team} />
                                <span
                                    className={cn(
                                        'truncate',
                                        team.is_placeholder
                                            ? 'text-muted-foreground italic'
                                            : 'font-medium',
                                    )}
                                >
                                    {team.name}
                                </span>
                            </span>
                            {team.code && (
                                <span className="rounded bg-secondary px-1.5 py-0.5 font-mono text-[0.65rem] font-semibold text-secondary-foreground">
                                    {team.code}
                                </span>
                            )}
                        </li>
                    ))}
                </ul>

                <div className="flex flex-col gap-1.5 border-t border-border pt-3 text-sm">
                    {group.fixtures.map((fixture: GroupFixture) => (
                        <div
                            key={fixture.match_number}
                            className="grid grid-cols-[1fr_auto_1fr] items-center gap-2"
                        >
                            <span className="flex min-w-0 items-center justify-end gap-1.5 text-muted-foreground">
                                <span className="truncate">
                                    {teamName(fixture.home, null)}
                                </span>
                                <Flag team={fixture.home} />
                            </span>
                            <Score
                                home={fixture.home_goals}
                                away={fixture.away_goals}
                            />
                            <span className="flex min-w-0 items-center gap-1.5 text-muted-foreground">
                                <Flag team={fixture.away} />
                                <span className="truncate">
                                    {teamName(fixture.away, null)}
                                </span>
                            </span>
                        </div>
                    ))}
                </div>
            </div>
        </div>
    );
}

function BracketSlot({
    fixture,
    isFinal,
}: {
    fixture: BracketFixture;
    isFinal: boolean;
}) {
    return (
        <div
            className={cn(
                'w-56 rounded-2xl p-3.5 text-sm',
                isFinal
                    ? 'shadow-glow-accent border border-accent/40 bg-card'
                    : 'card-elevated',
            )}
        >
            <div className="flex items-center justify-between gap-2">
                <span className="flex min-w-0 items-center gap-1.5 font-medium">
                    {fixture.home && <Flag team={fixture.home} />}
                    <span className="truncate">
                        {teamName(fixture.home, fixture.home_label)}
                    </span>
                </span>
                {fixture.home_goals !== null && (
                    <span className="font-display font-semibold tabular-nums">
                        {fixture.home_goals}
                    </span>
                )}
            </div>
            <div className="my-1.5 border-t border-border" />
            <div className="flex items-center justify-between gap-2">
                <span className="flex min-w-0 items-center gap-1.5 font-medium">
                    {fixture.away && <Flag team={fixture.away} />}
                    <span className="truncate">
                        {teamName(fixture.away, fixture.away_label)}
                    </span>
                </span>
                {fixture.away_goals !== null && (
                    <span className="font-display font-semibold tabular-nums">
                        {fixture.away_goals}
                    </span>
                )}
            </div>
        </div>
    );
}

export default function GameShow({
    game,
    groups,
    bracket,
    pool,
}: GameShowProps) {
    const { auth } = usePage().props;
    const name = auth.user?.name ?? 'there';

    return (
        <>
            <Head title={game.name} />
            <div className="flex h-full flex-1 flex-col gap-10 p-4">
                <DashboardBanner game={game} pool={pool} name={name} />

                <PoolPreview game={game} pool={pool} />

                <section
                    id="groups"
                    className="flex scroll-mt-20 flex-col gap-4"
                >
                    <h2 className="font-display text-xl font-semibold tracking-tight">
                        Groups
                    </h2>
                    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                        {groups.map((group) => (
                            <GroupCard key={group.name} group={group} />
                        ))}
                    </div>
                </section>

                <section
                    id="bracket"
                    className="flex scroll-mt-20 flex-col gap-4"
                >
                    <h2 className="font-display text-xl font-semibold tracking-tight">
                        Bracket
                    </h2>
                    <div className="flex gap-6 overflow-x-auto pb-4">
                        {bracket.map((phase) => (
                            <div
                                key={phase.phase_key}
                                className="flex flex-col gap-3"
                            >
                                <h3 className="font-display text-xs font-bold tracking-wide text-primary uppercase">
                                    {phase.phase_name}
                                </h3>
                                <div className="flex flex-col gap-3">
                                    {phase.fixtures.map((fixture) => (
                                        <BracketSlot
                                            key={fixture.match_number}
                                            fixture={fixture}
                                            isFinal={
                                                phase.phase_key === 'final'
                                            }
                                        />
                                    ))}
                                </div>
                            </div>
                        ))}
                    </div>
                </section>
            </div>
        </>
    );
}

GameShow.layout = {
    breadcrumbs: [{ title: 'Tournaments', href: games.index() }],
};
