import { Head, Link } from '@inertiajs/react';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { index, show } from '@/routes/games';
import type { GameSummary } from '@/types/games';

interface GamesIndexProps {
    games: GameSummary[];
}

export default function GamesIndex({ games }: GamesIndexProps) {
    return (
        <>
            <Head title="Games" />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                {games.length === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        No games are available yet.
                    </p>
                ) : (
                    <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                        {games.map((game) => (
                            <Link
                                key={game.slug}
                                href={show(game.slug)}
                                className="rounded-xl transition outline-none hover:opacity-90 focus-visible:ring-2 focus-visible:ring-ring"
                            >
                                <Card className="h-full">
                                    <CardHeader>
                                        <div className="flex items-start justify-between gap-2">
                                            <CardTitle>{game.name}</CardTitle>
                                            <Badge
                                                variant="secondary"
                                                className="capitalize"
                                            >
                                                {game.status.replace('_', ' ')}
                                            </Badge>
                                        </div>
                                    </CardHeader>
                                    <CardContent className="text-sm text-muted-foreground">
                                        <span className="capitalize">
                                            {game.sport}
                                        </span>
                                        {game.starts_on && (
                                            <span>
                                                {' · '}
                                                {game.starts_on} –{' '}
                                                {game.ends_on}
                                            </span>
                                        )}
                                    </CardContent>
                                </Card>
                            </Link>
                        ))}
                    </div>
                )}
            </div>
        </>
    );
}

GamesIndex.layout = {
    breadcrumbs: [{ title: 'Games', href: index() }],
};
