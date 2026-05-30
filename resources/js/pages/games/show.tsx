import { Head } from '@inertiajs/react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { index } from '@/routes/games';
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

function Score({ home, away }: { home: number | null; away: number | null }) {
    if (home === null || away === null) {
        return <span className="text-muted-foreground tabular-nums">–</span>;
    }

    return (
        <span className="font-medium tabular-nums">
            {home}–{away}
        </span>
    );
}

function teamName(team: TeamRef | null, fallback: string | null): string {
    return team?.name ?? fallback ?? 'TBD';
}

function GroupCard({ group }: { group: GroupView }) {
    return (
        <Card>
            <CardHeader>
                <CardTitle>Group {group.name}</CardTitle>
            </CardHeader>
            <CardContent className="flex flex-col gap-4">
                <ul className="flex flex-col gap-1 text-sm">
                    {group.teams.map((team) => (
                        <li
                            key={team.id}
                            className="flex items-center justify-between"
                        >
                            <span
                                className={
                                    team.is_placeholder
                                        ? 'text-muted-foreground italic'
                                        : ''
                                }
                            >
                                {team.name}
                            </span>
                            {team.code && (
                                <span className="text-xs text-muted-foreground">
                                    {team.code}
                                </span>
                            )}
                        </li>
                    ))}
                </ul>

                <div className="flex flex-col gap-1 border-t border-border/60 pt-3 text-sm">
                    {group.fixtures.map((fixture: GroupFixture) => (
                        <div
                            key={fixture.match_number}
                            className="grid grid-cols-[1fr_auto_1fr] items-center gap-2"
                        >
                            <span className="truncate text-right">
                                {teamName(fixture.home, null)}
                            </span>
                            <Score
                                home={fixture.home_goals}
                                away={fixture.away_goals}
                            />
                            <span className="truncate">
                                {teamName(fixture.away, null)}
                            </span>
                        </div>
                    ))}
                </div>
            </CardContent>
        </Card>
    );
}

function BracketSlot({ fixture }: { fixture: BracketFixture }) {
    return (
        <div className="flex w-56 flex-col gap-1 rounded-lg border bg-card p-3 text-sm">
            <div className="flex items-center justify-between gap-2">
                <span className="truncate">
                    {teamName(fixture.home, fixture.home_label)}
                </span>
                {fixture.home_goals !== null && (
                    <span className="font-medium tabular-nums">
                        {fixture.home_goals}
                    </span>
                )}
            </div>
            <div className="flex items-center justify-between gap-2">
                <span className="truncate">
                    {teamName(fixture.away, fixture.away_label)}
                </span>
                {fixture.away_goals !== null && (
                    <span className="font-medium tabular-nums">
                        {fixture.away_goals}
                    </span>
                )}
            </div>
        </div>
    );
}

export default function GameShow({ game, groups, bracket }: GameShowProps) {
    return (
        <>
            <Head title={game.name} />
            <div className="flex h-full flex-1 flex-col gap-8 p-4">
                <header className="flex flex-col gap-1">
                    <h1 className="text-2xl font-semibold">{game.name}</h1>
                    {game.starts_on && (
                        <p className="text-sm text-muted-foreground">
                            {game.starts_on} – {game.ends_on}
                        </p>
                    )}
                </header>

                <section className="flex flex-col gap-4">
                    <h2 className="text-lg font-medium">Groups</h2>
                    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                        {groups.map((group) => (
                            <GroupCard key={group.name} group={group} />
                        ))}
                    </div>
                </section>

                <section className="flex flex-col gap-4">
                    <h2 className="text-lg font-medium">Bracket</h2>
                    <div className="flex gap-6 overflow-x-auto pb-4">
                        {bracket.map((phase) => (
                            <div
                                key={phase.phase_key}
                                className="flex flex-col gap-3"
                            >
                                <h3 className="text-sm font-medium whitespace-nowrap text-muted-foreground">
                                    {phase.phase_name}
                                </h3>
                                <div className="flex flex-col gap-3">
                                    {phase.fixtures.map((fixture) => (
                                        <BracketSlot
                                            key={fixture.match_number}
                                            fixture={fixture}
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
    breadcrumbs: [{ title: 'Games', href: index() }],
};
