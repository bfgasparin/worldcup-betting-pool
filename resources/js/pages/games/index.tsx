import { Head, Link } from '@inertiajs/react';
import {
    ArrowRight,
    CalendarDays,
    Layers,
    Sparkles,
    Trophy,
} from 'lucide-react';
import type { ComponentType, ReactNode } from 'react';
import { Chip } from '@/components/ui/chip';
import { scoringRules } from '@/lib/scoring';
import { show } from '@/routes/games';
import type { GameListItem } from '@/types/games';

interface GamesIndexProps {
    games: GameListItem[];
}

function formatDates(game: GameListItem): string | null {
    if (!game.starts_on) {
        return null;
    }

    return game.ends_on
        ? `${game.starts_on} – ${game.ends_on}`
        : game.starts_on;
}

function StatPill({
    icon: Icon,
    label,
}: {
    icon: ComponentType<{ className?: string }>;
    label: ReactNode;
}) {
    return (
        <span className="inline-flex items-center gap-1.5 rounded-full border border-border bg-secondary px-3 py-1 font-display text-xs font-semibold">
            <Icon className="size-3.5 text-primary" />
            {label}
        </span>
    );
}

function StatusBadge({ status }: { status: string }) {
    return (
        <span className="bg-brand-gradient inline-flex items-center gap-1.5 rounded-full px-3 py-1 font-display text-xs font-semibold text-white capitalize shadow-[var(--sh-sm)]">
            {status.replace('_', ' ')}
        </span>
    );
}

function SourceTag({ source }: { source: string }) {
    return (
        <span className="inline-flex items-center gap-1.5 text-xs font-bold tracking-[0.14em] text-muted-foreground uppercase">
            <span className="opacity-70">Game by</span>
            <span className="bg-gold-gradient rounded-full px-3 py-1 font-display text-sm font-bold tracking-normal text-[#0D2E23] normal-case shadow-[var(--sh-sm)]">
                {source}
            </span>
        </span>
    );
}

/**
 * How a game scores: the strategy name, a one-line explanation, and the per-rule points so a
 * player can tell games over the same competition apart before entering.
 */
function ScoringSummary({ game }: { game: GameListItem }) {
    const rules = [
        ...scoringRules(game.scoring_config, 'group'),
        ...scoringRules(game.scoring_config, 'knockout'),
    ];

    return (
        <div className="flex flex-col gap-3">
            <Chip variant="points" className="w-fit px-3 py-1 text-xs">
                {game.scoring_label}
            </Chip>
            <p className="text-sm text-muted-foreground">
                {game.scoring_description}
            </p>
            <div className="flex flex-wrap gap-1.5">
                {rules.map((rule) => (
                    <span
                        key={rule.label}
                        className="inline-flex items-center gap-1.5 rounded-full bg-secondary px-2.5 py-1 text-xs font-semibold text-secondary-foreground"
                    >
                        {rule.label}
                        <b className="font-display text-primary">
                            +{rule.points}
                        </b>
                    </span>
                ))}
            </div>
        </div>
    );
}

function FeaturedCard({ game }: { game: GameListItem }) {
    const dates = formatDates(game);

    return (
        <Link
            href={show(game.slug)}
            className="group grid overflow-hidden rounded-3xl border border-border bg-card shadow-[var(--sh-md)] transition-transform duration-200 hover:-translate-y-1 md:grid-cols-[1.1fr_1fr]"
        >
            <div className="hero relative flex flex-col justify-between gap-6 overflow-hidden border-b border-border p-8 md:border-r md:border-b-0">
                <div className="hero-lines" />
                <div className="relative flex flex-wrap items-center gap-2.5">
                    <StatusBadge status={game.status} />
                    <SourceTag source={game.source} />
                </div>
                <div className="relative">
                    <h2 className="text-4xl font-semibold tracking-tight text-balance text-foreground sm:text-5xl">
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
                    <StatPill
                        icon={Trophy}
                        label={<span className="capitalize">{game.sport}</span>}
                    />
                    {game.groups_count != null && (
                        <StatPill
                            icon={Layers}
                            label={`${game.groups_count} Groups`}
                        />
                    )}
                    {game.fixtures_count != null && (
                        <StatPill
                            icon={Sparkles}
                            label={`${game.fixtures_count} Matches`}
                        />
                    )}
                </div>

                <ScoringSummary game={game} />

                <span className="bg-brand-gradient shadow-glow inline-flex w-fit items-center gap-2 rounded-full px-6 py-3 font-display text-base font-semibold text-white transition-all group-hover:gap-3">
                    Enter game
                    <ArrowRight className="size-5" />
                </span>
            </div>
        </Link>
    );
}

function GameCard({ game }: { game: GameListItem }) {
    const dates = formatDates(game);

    return (
        <Link
            href={show(game.slug)}
            className="card-elevated group flex flex-col gap-4 rounded-3xl p-6 transition-transform duration-200 hover:-translate-y-1"
        >
            <div className="flex items-start justify-between gap-2">
                <div className="app-icon size-11 rounded-2xl shadow-[var(--sh-sm)]">
                    <Trophy className="size-5 text-white" />
                </div>
                <span className="inline-flex items-center rounded-full bg-secondary px-3 py-1 font-display text-xs font-semibold text-secondary-foreground capitalize">
                    {game.status.replace('_', ' ')}
                </span>
            </div>
            <div className="flex-1">
                <h3 className="font-display text-lg font-semibold tracking-tight">
                    {game.name}
                </h3>
                <div className="mt-1 flex flex-wrap items-center gap-x-2 gap-y-1">
                    <SourceTag source={game.source} />
                    {dates && (
                        <span className="text-sm text-muted-foreground">
                            {dates}
                        </span>
                    )}
                </div>
            </div>
            <ScoringSummary game={game} />
            <span className="inline-flex items-center gap-1 font-display text-sm font-semibold text-primary transition-all group-hover:gap-2">
                Enter
                <ArrowRight className="size-4" />
            </span>
        </Link>
    );
}

function ComingSoon() {
    return (
        <div className="flex min-h-44 flex-col items-center justify-center gap-2 rounded-3xl border border-dashed border-border p-6 text-center">
            <Sparkles className="size-5 text-muted-foreground" />
            <p className="text-sm font-medium text-muted-foreground">
                More games coming soon
            </p>
        </div>
    );
}

export default function GamesIndex({ games }: GamesIndexProps) {
    const [featured, ...rest] = games;

    return (
        <>
            <Head title="Games" />
            <div className="relative min-h-full bg-background">
                <div className="relative mx-auto w-full max-w-6xl px-6 py-10">
                    <header className="hero relative mb-8 overflow-hidden rounded-3xl border border-border p-8">
                        <div className="hero-lines" />
                        <div className="relative flex flex-col gap-3">
                            <span className="inline-flex w-fit items-center gap-2 text-xs font-bold tracking-[0.14em] text-muted-foreground uppercase">
                                <span className="bg-brand-gradient size-2 rounded-full" />
                                Brothers Betting Pool
                            </span>
                            <h1 className="text-4xl font-semibold tracking-tight text-balance text-foreground sm:text-5xl">
                                Choose your game
                            </h1>
                            <p className="max-w-xl text-base text-muted-foreground">
                                Pick a game to view the draw, follow the
                                bracket, and get your predictions in. Each one
                                scores its own way.
                            </p>
                        </div>
                    </header>

                    {featured ? (
                        <FeaturedCard game={featured} />
                    ) : (
                        <p className="text-sm text-muted-foreground">
                            No games are available yet.
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
