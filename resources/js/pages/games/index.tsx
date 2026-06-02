import { Head, Link } from '@inertiajs/react';
import {
    ArrowRight,
    CalendarDays,
    ChevronLeft,
    ChevronRight,
    Layers,
    Sparkles,
    Trophy,
} from 'lucide-react';
import type { ComponentType, ReactNode } from 'react';
import { Button } from '@/components/ui/button';
import { Chip } from '@/components/ui/chip';
import { scoringRules } from '@/lib/scoring';
import type { ScoringRule } from '@/lib/scoring';
import { show } from '@/routes/games';
import type { GameListItem, Paginated } from '@/types/games';

interface GamesIndexProps {
    games: Paginated<GameListItem>;
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
 * The point pills for one phase, under a small heading. Renders nothing when the phase has no
 * configured rules.
 */
function PhaseRules({
    heading,
    rules,
}: {
    heading: string;
    rules: ScoringRule[];
}) {
    if (rules.length === 0) {
        return null;
    }

    return (
        <div className="flex flex-col gap-1.5">
            <span className="text-[0.65rem] font-bold tracking-wide text-muted-foreground uppercase">
                {heading}
            </span>
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

/**
 * How a game scores: the strategy name, a one-line explanation, and the per-rule points — split by
 * phase so a game that scores the group stage and knockouts the same way doesn't read as a list of
 * duplicated pills.
 */
function ScoringSummary({ game }: { game: GameListItem }) {
    return (
        <div className="flex flex-col gap-3">
            <Chip variant="points" className="w-fit px-3 py-1 text-xs">
                {game.scoring_label}
            </Chip>
            <p className="text-sm text-muted-foreground">
                {game.scoring_description}
            </p>
            <div className="flex flex-col gap-3">
                <PhaseRules
                    heading="Group stage"
                    rules={scoringRules(game.scoring_config, 'group')}
                />
                <PhaseRules
                    heading="Knockouts"
                    rules={scoringRules(game.scoring_config, 'knockout')}
                />
            </div>
        </div>
    );
}

/**
 * A single game in the list. Every game uses this same card — full width, stacked — so no game is
 * visually privileged over another.
 */
function GameCard({ game }: { game: GameListItem }) {
    const dates = formatDates(game);

    return (
        <Link
            href={show(game.slug)}
            className="card-elevated group flex flex-col gap-4 rounded-3xl p-6 transition-transform duration-200 hover:-translate-y-1 sm:p-8"
        >
            <div className="flex flex-wrap items-center justify-between gap-2.5">
                <div className="flex flex-wrap items-center gap-2.5">
                    <StatusBadge status={game.status} />
                    <SourceTag source={game.source} />
                </div>
                {dates && (
                    <span className="inline-flex items-center gap-2 text-sm text-muted-foreground">
                        <CalendarDays className="size-4" />
                        {dates}
                    </span>
                )}
            </div>

            <h2 className="text-2xl font-semibold tracking-tight text-balance text-foreground sm:text-3xl">
                {game.name}
            </h2>

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
        </Link>
    );
}

/** Previous / next controls, shown only when the list spans more than one page. */
function Pagination({ games }: { games: Paginated<GameListItem> }) {
    if (games.last_page <= 1) {
        return null;
    }

    return (
        <nav className="mt-8 flex items-center justify-between gap-4">
            {games.prev_page_url ? (
                <Button variant="outline" asChild>
                    <Link href={games.prev_page_url} preserveScroll>
                        <ChevronLeft className="size-4" />
                        Previous
                    </Link>
                </Button>
            ) : (
                <Button variant="outline" disabled>
                    <ChevronLeft className="size-4" />
                    Previous
                </Button>
            )}

            <span className="text-sm font-medium text-muted-foreground">
                Page {games.current_page} of {games.last_page}
            </span>

            {games.next_page_url ? (
                <Button variant="outline" asChild>
                    <Link href={games.next_page_url} preserveScroll>
                        Next
                        <ChevronRight className="size-4" />
                    </Link>
                </Button>
            ) : (
                <Button variant="outline" disabled>
                    Next
                    <ChevronRight className="size-4" />
                </Button>
            )}
        </nav>
    );
}

export default function GamesIndex({ games }: GamesIndexProps) {
    return (
        <>
            <Head title="Games" />
            <div className="relative min-h-full bg-background">
                <div className="relative mx-auto w-full max-w-4xl px-6 py-10">
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

                    {games.data.length > 0 ? (
                        <div className="flex flex-col gap-4">
                            {games.data.map((game) => (
                                <GameCard key={game.slug} game={game} />
                            ))}
                        </div>
                    ) : (
                        <p className="text-sm text-muted-foreground">
                            No games are available yet.
                        </p>
                    )}

                    <Pagination games={games} />
                </div>
            </div>
        </>
    );
}
