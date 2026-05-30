import { Head } from '@inertiajs/react';
import { CalendarDays } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { index as games } from '@/routes/games';
import type {
    BracketFixture,
    BracketPhase,
    GameDetail,
    GroupFixture,
    GroupView,
    TeamRef,
} from '@/types/games';

interface GameShowProps {
    game: GameDetail;
    groups: GroupView[];
    bracket: BracketPhase[];
}

function teamName(team: TeamRef | null, fallback: string | null): string {
    return team?.name ?? fallback ?? 'TBD';
}

function Score({ home, away }: { home: number | null; away: number | null }) {
    if (home === null || away === null) {
        return <span className="text-muted-foreground tabular-nums">–</span>;
    }

    return (
        <span className="font-bold tabular-nums">
            {home}–{away}
        </span>
    );
}

function GroupCard({ group }: { group: GroupView }) {
    return (
        <div className="card-elevated overflow-hidden rounded-2xl">
            <div className="bg-brand-gradient px-5 py-3">
                <h3 className="text-sm font-black tracking-wide text-primary-foreground uppercase">
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
                            <span
                                className={
                                    team.is_placeholder
                                        ? 'text-muted-foreground italic'
                                        : 'font-medium'
                                }
                            >
                                {team.name}
                            </span>
                            {team.code && (
                                <span className="rounded bg-secondary px-1.5 py-0.5 font-mono text-[0.65rem] font-semibold text-secondary-foreground">
                                    {team.code}
                                </span>
                            )}
                        </li>
                    ))}
                </ul>

                <div className="flex flex-col gap-1.5 border-t border-border/60 pt-3 text-sm">
                    {group.fixtures.map((fixture: GroupFixture) => (
                        <div
                            key={fixture.match_number}
                            className="grid grid-cols-[1fr_auto_1fr] items-center gap-2"
                        >
                            <span className="truncate text-right text-muted-foreground">
                                {teamName(fixture.home, null)}
                            </span>
                            <Score
                                home={fixture.home_goals}
                                away={fixture.away_goals}
                            />
                            <span className="truncate text-muted-foreground">
                                {teamName(fixture.away, null)}
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
            className={
                isFinal
                    ? 'shadow-glow-accent w-60 rounded-xl border border-accent/40 bg-card p-3.5 text-sm'
                    : 'card-elevated w-56 rounded-xl p-3.5 text-sm'
            }
        >
            <div className="flex items-center justify-between gap-2">
                <span className="truncate font-medium">
                    {teamName(fixture.home, fixture.home_label)}
                </span>
                {fixture.home_goals !== null && (
                    <span className="font-bold tabular-nums">
                        {fixture.home_goals}
                    </span>
                )}
            </div>
            <div className="my-1.5 border-t border-border/50" />
            <div className="flex items-center justify-between gap-2">
                <span className="truncate font-medium">
                    {teamName(fixture.away, fixture.away_label)}
                </span>
                {fixture.away_goals !== null && (
                    <span className="font-bold tabular-nums">
                        {fixture.away_goals}
                    </span>
                )}
            </div>
        </div>
    );
}

export default function GameShow({ game, groups, bracket }: GameShowProps) {
    const dates = game.starts_on
        ? game.ends_on
            ? `${game.starts_on} – ${game.ends_on}`
            : game.starts_on
        : null;

    return (
        <>
            <Head title={game.name} />
            <div className="flex h-full flex-1 flex-col gap-10 p-4">
                <header className="bg-pitch relative overflow-hidden rounded-2xl border border-primary/20 p-8">
                    <div className="pointer-events-none absolute -top-16 -right-10 size-56 rounded-full bg-accent/20 blur-3xl" />
                    <div className="relative flex flex-col gap-3">
                        <Badge className="bg-brand-gradient w-fit border-0 text-primary-foreground capitalize shadow">
                            {game.status.replace('_', ' ')}
                        </Badge>
                        <h1 className="text-gradient-brand text-4xl font-black tracking-tight text-balance sm:text-5xl">
                            {game.name}
                        </h1>
                        <div className="flex flex-wrap items-center gap-4 text-sm text-muted-foreground">
                            <span className="capitalize">{game.sport}</span>
                            {dates && (
                                <span className="inline-flex items-center gap-2">
                                    <CalendarDays className="size-4" />
                                    {dates}
                                </span>
                            )}
                        </div>
                    </div>
                </header>

                <section
                    id="groups"
                    className="flex scroll-mt-20 flex-col gap-4"
                >
                    <h2 className="text-xl font-bold tracking-tight">Groups</h2>
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
                    <h2 className="text-xl font-bold tracking-tight">
                        Bracket
                    </h2>
                    <div className="flex gap-6 overflow-x-auto pb-4">
                        {bracket.map((phase) => (
                            <div
                                key={phase.phase_key}
                                className="flex flex-col gap-3"
                            >
                                <h3 className="text-xs font-bold tracking-wide text-primary uppercase">
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
    breadcrumbs: [{ title: 'Tournaments', href: games() }],
};
