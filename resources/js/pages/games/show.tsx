import { Head, Link, router, usePage } from '@inertiajs/react';
import {
    ArrowRight,
    CalendarDays,
    ListOrdered,
    PencilLine,
} from 'lucide-react';
import { useState } from 'react';
import {
    FinalCard,
    GroupFixtureCard,
    KnockoutSlotCard,
    PhaseMeta,
    PhaseTabs,
    phaseDateRange,
} from '@/components/fixtures';
import type { Phase } from '@/components/fixtures';
import { LeaderboardRow } from '@/components/leaderboard-row';
import { Button } from '@/components/ui/button';
import { useDisplayTimeZone } from '@/hooks/use-timezone';
import { cn } from '@/lib/utils';
import games from '@/routes/games';
import type {
    BracketPhase,
    GameDetail,
    GameStatus,
    GroupView,
    PoolSummary,
} from '@/types/games';

interface GameShowProps {
    game: GameDetail;
    groups: GroupView[];
    bracket: BracketPhase[];
    pool: PoolSummary;
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

const STATUS_LABELS: Record<GameStatus, string> = {
    upcoming: 'Upcoming',
    in_progress: 'In Progress',
    completed: 'Completed',
};

function AdminStatusControl({ game }: { game: GameDetail }) {
    const [submitting, setSubmitting] = useState<GameStatus | null>(null);
    const [errors, setErrors] = useState<Record<string, string>>({});

    const advance = (status: GameStatus) => {
        setSubmitting(status);
        router.patch(
            games.status.update(game.slug).url,
            { status },
            {
                preserveScroll: true,
                onError: (formErrors) => setErrors(formErrors),
                onSuccess: () => setErrors({}),
                onFinish: () => setSubmitting(null),
            },
        );
    };

    return (
        <div className="mt-4 flex flex-wrap items-center gap-2 rounded-2xl border border-dashed border-border bg-card/60 p-3">
            <span className="font-display text-[11px] font-bold tracking-[0.08em] text-muted-foreground uppercase">
                Admin
            </span>
            {game.allowed_transitions.map((status) => (
                <Button
                    key={status}
                    size="sm"
                    variant="outline"
                    disabled={submitting !== null}
                    onClick={() => advance(status)}
                >
                    {submitting === status
                        ? 'Saving…'
                        : `Set ${STATUS_LABELS[status]}`}
                </Button>
            ))}
            {errors.status && (
                <span className="text-xs text-destructive">
                    {errors.status}
                </span>
            )}
        </div>
    );
}

function DashboardBanner({
    game,
    pool,
    name,
    isAdmin,
}: {
    game: GameDetail;
    pool: PoolSummary;
    name: string;
    isAdmin: boolean;
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
                        {isAdmin && <AdminStatusControl game={game} />}
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
}: GameShowProps) {
    const { auth } = usePage().props;
    const name = auth.user?.name ?? 'there';

    return (
        <>
            <Head title={game.name} />
            <div className="flex h-full flex-1 flex-col gap-10 p-4">
                <DashboardBanner
                    game={game}
                    pool={pool}
                    name={name}
                    isAdmin={auth.isAdmin}
                />

                <PoolPreview game={game} pool={pool} />

                <FixturesView groups={groups} bracket={bracket} />
            </div>
        </>
    );
}

GameShow.layout = {
    breadcrumbs: [{ title: 'Tournaments', href: games.index() }],
};
