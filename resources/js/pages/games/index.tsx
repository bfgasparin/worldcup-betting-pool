import { Head, Link } from '@inertiajs/react';
import {
    ArrowRight,
    CalendarDays,
    Layers,
    Sparkles,
    Trophy,
} from 'lucide-react';
import type { ComponentType, ReactNode } from 'react';
import { Badge } from '@/components/ui/badge';
import { show } from '@/routes/games';
import type { GameSummary } from '@/types/games';

interface GamesIndexProps {
    games: GameSummary[];
}

function formatDates(game: GameSummary): string | null {
    if (!game.starts_on) {
        return null;
    }

    return game.ends_on
        ? `${game.starts_on} – ${game.ends_on}`
        : game.starts_on;
}

function StatChip({
    icon: Icon,
    label,
}: {
    icon: ComponentType<{ className?: string }>;
    label: ReactNode;
}) {
    return (
        <span className="inline-flex items-center gap-1.5 rounded-full border border-border/70 bg-background/60 px-3 py-1 text-xs font-semibold backdrop-blur">
            <Icon className="size-3.5 text-primary" />
            {label}
        </span>
    );
}

function FeaturedCard({ game }: { game: GameSummary }) {
    const dates = formatDates(game);

    return (
        <Link
            href={show(game.slug)}
            className="shadow-glow group relative grid overflow-hidden rounded-2xl border border-primary/30 bg-card transition-transform duration-200 hover:-translate-y-1 md:grid-cols-[1.1fr_1fr]"
        >
            <div className="bg-pitch relative flex flex-col justify-between gap-6 p-8">
                <div className="flex items-center gap-2">
                    <Badge className="bg-brand-gradient border-0 text-primary-foreground capitalize shadow">
                        {game.status.replace('_', ' ')}
                    </Badge>
                    <span className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                        Featured
                    </span>
                </div>
                <div>
                    <h2 className="text-4xl font-black tracking-tight text-balance sm:text-5xl">
                        {game.name}
                    </h2>
                    {dates && (
                        <p className="mt-3 inline-flex items-center gap-2 text-sm text-muted-foreground">
                            <CalendarDays className="size-4" />
                            {dates}
                        </p>
                    )}
                </div>
            </div>

            <div className="flex flex-col justify-between gap-6 p-8">
                <div className="flex flex-wrap gap-2">
                    <StatChip
                        icon={Trophy}
                        label={<span className="capitalize">{game.sport}</span>}
                    />
                    {game.groups_count != null && (
                        <StatChip
                            icon={Layers}
                            label={`${game.groups_count} Groups`}
                        />
                    )}
                    {game.fixtures_count != null && (
                        <StatChip
                            icon={Sparkles}
                            label={`${game.fixtures_count} Matches`}
                        />
                    )}
                </div>

                <p className="text-sm text-muted-foreground">
                    Predict every fixture, ride the bracket, and chase the
                    title. Lock in your picks before kickoff.
                </p>

                <span className="bg-brand-gradient inline-flex w-fit items-center gap-2 rounded-xl px-6 py-3 text-base font-bold text-primary-foreground shadow-md transition-transform group-hover:gap-3">
                    Enter tournament
                    <ArrowRight className="size-5" />
                </span>
            </div>
        </Link>
    );
}

function GameCard({ game }: { game: GameSummary }) {
    const dates = formatDates(game);

    return (
        <Link
            href={show(game.slug)}
            className="card-elevated group flex flex-col gap-4 rounded-2xl p-6 transition-transform duration-200 hover:-translate-y-1"
        >
            <div className="flex items-start justify-between gap-2">
                <div className="bg-brand-gradient flex size-11 items-center justify-center rounded-xl text-primary-foreground shadow">
                    <Trophy className="size-5" />
                </div>
                <Badge variant="secondary" className="capitalize">
                    {game.status.replace('_', ' ')}
                </Badge>
            </div>
            <div className="flex-1">
                <h3 className="text-lg font-bold tracking-tight">
                    {game.name}
                </h3>
                {dates && (
                    <p className="mt-1 text-sm text-muted-foreground">
                        {dates}
                    </p>
                )}
            </div>
            <span className="inline-flex items-center gap-1 text-sm font-semibold text-primary transition-all group-hover:gap-2">
                Enter
                <ArrowRight className="size-4" />
            </span>
        </Link>
    );
}

function ComingSoon() {
    return (
        <div className="flex min-h-44 flex-col items-center justify-center gap-2 rounded-2xl border border-dashed border-border/70 p-6 text-center">
            <Sparkles className="size-5 text-muted-foreground" />
            <p className="text-sm font-medium text-muted-foreground">
                More tournaments coming soon
            </p>
        </div>
    );
}

export default function GamesIndex({ games }: GamesIndexProps) {
    const [featured, ...rest] = games;

    return (
        <>
            <Head title="Tournaments" />
            <div className="bg-pitch relative min-h-full">
                <div className="pointer-events-none absolute inset-0">
                    <div className="absolute -top-32 left-1/4 size-96 rounded-full bg-primary/20 blur-3xl" />
                    <div className="absolute top-40 -right-24 size-80 rounded-full bg-accent/20 blur-3xl" />
                </div>

                <div className="relative mx-auto w-full max-w-6xl px-6 py-12">
                    <header className="mb-10 flex flex-col gap-3">
                        <span className="inline-flex w-fit items-center gap-2 rounded-full border border-primary/30 bg-primary/10 px-4 py-1.5 text-xs font-semibold tracking-wide text-primary uppercase">
                            <span className="size-1.5 rounded-full bg-accent" />
                            FF&amp;A Betting Pool
                        </span>
                        <h1 className="text-gradient-brand text-4xl font-black tracking-tight text-balance sm:text-5xl">
                            Choose your tournament
                        </h1>
                        <p className="max-w-xl text-base text-muted-foreground">
                            Pick a competition to view the draw, follow the
                            bracket, and get your predictions in.
                        </p>
                    </header>

                    {featured ? (
                        <FeaturedCard game={featured} />
                    ) : (
                        <p className="text-sm text-muted-foreground">
                            No tournaments are available yet.
                        </p>
                    )}

                    <div className="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        {rest.map((game) => (
                            <GameCard key={game.slug} game={game} />
                        ))}
                        <ComingSoon />
                    </div>
                </div>
            </div>
        </>
    );
}
