import { Head, Link, usePage } from '@inertiajs/react';
import {
    ArrowRight,
    CalendarDays,
    Check,
    ChevronLeft,
    ChevronRight,
    Layers,
    Sparkles,
    Trophy,
    Users,
} from 'lucide-react';
import type { ComponentType, ReactNode } from 'react';
import { PrizeSplit } from '@/components/prize-split';
import { Button } from '@/components/ui/button';
import { Chip } from '@/components/ui/chip';
import { resolveAccent, sourceMonogram } from '@/lib/accents';
import type { GameAccent } from '@/lib/accents';
import { cn } from '@/lib/utils';
import { index, show } from '@/routes/games';
import type { GameListItem, Paginated } from '@/types/games';

interface GamesIndexProps {
    games: Paginated<GameListItem>;
}

/** A tournament and the games played over it, in the order they arrived from the server. */
interface TournamentGroup {
    tournament: GameListItem['tournament'];
    games: GameListItem[];
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

/**
 * Cluster the page's games by the tournament they're played over, preserving order. Sibling games
 * arrive adjacent (the server orders by tournament then id), so each tournament forms one run.
 */
function groupByTournament(games: GameListItem[]): TournamentGroup[] {
    const groups: TournamentGroup[] = [];
    const byId = new Map<number, TournamentGroup>();

    for (const game of games) {
        let group = byId.get(game.tournament.id);

        if (!group) {
            group = { tournament: game.tournament, games: [] };
            byId.set(game.tournament.id, group);
            groups.push(group);
        }

        group.games.push(game);
    }

    return groups;
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

/** "12 players" / "1 player" / "No players yet" — the size of a game's pool. */
function playersLabel(count: number): string {
    if (count === 0) {
        return 'No players yet';
    }

    return count === 1 ? '1 player' : `${count} players`;
}

/** A game's pool size — its own per-game stat, the same shape as the tournament stat pills. */
function PlayersStat({ count }: { count: number }) {
    return <StatPill icon={Users} label={playersLabel(count)} />;
}

/**
 * The shared tournament facts (sport, group & match counts) — identical for every sibling game.
 * `playersCount`, when given, appends the game's own pool size (used on a solo game's card, where
 * the shared facts and the per-game count sit on one row).
 */
function StatPills({
    game,
    playersCount,
}: {
    game: GameListItem;
    playersCount?: number;
}) {
    return (
        <div className="flex flex-wrap gap-2">
            <StatPill
                icon={Trophy}
                label={<span className="capitalize">{game.sport}</span>}
            />
            {game.groups_count != null && (
                <StatPill icon={Layers} label={`${game.groups_count} Groups`} />
            )}
            {game.fixtures_count != null && (
                <StatPill
                    icon={Sparkles}
                    label={`${game.fixtures_count} Matches`}
                />
            )}
            {playersCount != null && <PlayersStat count={playersCount} />}
        </div>
    );
}

function StatusBadge({ status }: { status: string }) {
    return (
        <span className="bg-brand-gradient inline-flex items-center gap-1.5 rounded-full px-3 py-1 font-display text-xs font-semibold text-white capitalize shadow-[var(--sh-sm)]">
            {status.replace('_', ' ')}
        </span>
    );
}

function DateRange({ game }: { game: GameListItem }) {
    const dates = formatDates(game);

    if (!dates) {
        return null;
    }

    return (
        <span className="inline-flex items-center gap-2 text-sm text-muted-foreground">
            <CalendarDays className="size-4" />
            {dates}
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

/** The accent-coloured call-to-action at the foot of a ticket. */
function EnterButton({ accent }: { accent: GameAccent }) {
    return (
        <span
            className={cn(
                'inline-flex w-fit items-center gap-2 rounded-full px-6 py-3 font-display text-base font-semibold transition-all group-hover:gap-3',
                accent.buttonClass,
                accent.glowClass,
                accent.textClass,
            )}
        >
            View game
            <ArrowRight className="size-5" />
        </span>
    );
}

/**
 * The source rail — the ticket's coloured top banner. Carries the source emblem (a monogram) in
 * the game's kit colour + texture; it's the per-game visual anchor that sits above the body so a
 * vertical ticket reads cleanly in the multi-column games grid.
 */
function SourceRail({
    source,
    accent,
}: {
    source: string;
    accent: GameAccent;
}) {
    return (
        <div
            className={cn(
                'flex shrink-0 items-center justify-center gap-2.5 px-6 py-4',
                accent.railClass,
                accent.textClass,
            )}
        >
            <span className="font-display text-[0.6rem] font-bold tracking-[0.2em] uppercase opacity-75">
                Game by
            </span>
            <span className="font-display text-3xl leading-none font-bold">
                {sourceMonogram(source)}
            </span>
        </div>
    );
}

/**
 * A single game as a ticket: the source rail (kit colour) + the body. A game over a tournament that
 * has more than one game leads with its *source* (the thing that differs from its siblings) so the
 * shared name never reads as a duplicate; a game that's alone over its tournament keeps the full
 * header (status, dates, stats, name) in the house pitch kit.
 */
function GameTicket({
    game,
    grouped,
}: {
    game: GameListItem;
    /**
     * Whether this ticket sits in a multi-game group on this page (and so under a
     * {@see TournamentHeader} carrying the shared facts). Driven by the visible group, not a global
     * count, so the body and the header can never disagree — a game shown on its own always renders
     * the full solo layout rather than an orphaned, contextless compact ticket.
     */
    grouped: boolean;
}) {
    const accent = resolveAccent(game.accent, game.accent_index);

    return (
        <Link
            href={show(game.slug)}
            className={cn(
                'group card-elevated flex flex-col overflow-hidden rounded-3xl transition-transform duration-200 hover:-translate-y-1',
                accent.ringClass,
            )}
        >
            <SourceRail source={game.source} accent={accent} />

            <div className="flex flex-1 flex-col gap-4 p-6 sm:p-7">
                {grouped ? (
                    <div className="flex flex-wrap items-start justify-between gap-2.5">
                        <div className="flex flex-col gap-1">
                            <h3 className="text-2xl font-semibold tracking-tight text-balance text-foreground">
                                {game.source}
                            </h3>
                            <span className="font-display text-sm font-semibold text-muted-foreground">
                                {game.scoring_label}
                            </span>
                        </div>
                        <PlayersStat count={game.players_count} />
                    </div>
                ) : (
                    <>
                        <div className="flex flex-wrap items-center justify-between gap-2.5">
                            <div className="flex flex-wrap items-center gap-2.5">
                                <StatusBadge status={game.status} />
                                <SourceTag source={game.source} />
                            </div>
                            <DateRange game={game} />
                        </div>
                        <h2 className="text-2xl font-semibold tracking-tight text-balance text-foreground sm:text-3xl">
                            {game.name}
                        </h2>
                        <StatPills
                            game={game}
                            playersCount={game.players_count}
                        />
                        <Chip
                            variant="points"
                            className="w-fit px-3 py-1 text-xs"
                        >
                            {game.scoring_label}
                        </Chip>
                    </>
                )}

                <p className="text-sm text-muted-foreground">
                    {game.scoring_description}
                </p>

                <PrizeSplit pricing={game.pricing} canJoin={game.can_join} />

                <div className="mt-auto flex flex-wrap items-center gap-3">
                    {game.joined && <StatPill icon={Check} label="Joined" />}
                    <EnterButton accent={accent} />
                </div>
            </div>
        </Link>
    );
}

/**
 * A header above a cluster of same-tournament games. It carries the facts every sibling shares —
 * name, status, dates, sport & counts — once, so the tickets beneath it only have to show what
 * makes each different. Framed as multiple games over one competition, not as a pick-one choice.
 * The "N games" chip counts the games shown here, so it always matches the tickets beneath it.
 */
function TournamentHeader({ group }: { group: TournamentGroup }) {
    const lead = group.games[0];

    return (
        <div className="flex flex-col gap-3 border-b border-border pb-5">
            <div className="flex flex-wrap items-center justify-between gap-2.5">
                <div className="flex flex-wrap items-center gap-2.5">
                    <StatusBadge status={lead.status} />
                    <DateRange game={lead} />
                </div>
                <Chip variant="outline" className="px-3 py-1 text-xs">
                    {group.games.length} games
                </Chip>
            </div>
            <h2 className="text-2xl font-semibold tracking-tight text-balance text-foreground sm:text-3xl">
                {group.tournament.name}
            </h2>
            <StatPills game={lead} />
            <p className="max-w-xl text-sm text-muted-foreground">
                Each game below scores this competition its own way — play as
                many as you like.
            </p>
        </div>
    );
}

/**
 * One tournament's block: a single game renders as a lone ticket; multiple games render under a
 * shared tournament header, each in its own kit colour.
 */
function TournamentGroupSection({ group }: { group: TournamentGroup }) {
    if (group.games.length === 1) {
        return <GameTicket game={group.games[0]} grouped={false} />;
    }

    return (
        <section className="flex flex-col gap-5">
            <TournamentHeader group={group} />
            <div className="grid grid-cols-1 gap-5 lg:grid-cols-2 2xl:grid-cols-3">
                {group.games.map((game) => (
                    <GameTicket key={game.slug} game={game} grouped />
                ))}
            </div>
        </section>
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
    const groups = groupByTournament(games.data);
    const { auth } = usePage().props;
    const name = auth.user?.name ?? 'there';
    const firstName = name.split(' ')[0] || name;

    return (
        <>
            <Head title="Games" />
            <div className="relative min-h-full bg-background">
                <div className="relative w-full px-4 py-6 sm:px-6 sm:py-8 lg:px-8 xl:px-10">
                    <header className="hero relative mb-8 overflow-hidden rounded-3xl border border-border p-8">
                        <div className="hero-lines" />
                        <div className="relative flex flex-col gap-3">
                            <span className="inline-flex w-fit items-center gap-2 text-xs font-bold tracking-[0.14em] text-muted-foreground uppercase">
                                <span className="bg-brand-gradient size-2 rounded-full" />
                                Brothers Betting Pool
                            </span>
                            <span className="text-sm font-semibold text-muted-foreground">
                                {greeting()}, {firstName} 👋
                            </span>
                            <h1 className="text-4xl font-semibold tracking-tight text-balance text-foreground sm:text-5xl">
                                Join a game
                            </h1>
                            <span className="bg-gold-gradient mt-1 h-1 w-12 rounded-full" />
                            <p className="max-w-2xl text-base text-muted-foreground">
                                Browse the games below, check the buy-in and
                                prize pool, and buy into the ones you fancy.
                                There’s no picking just one — play as many as
                                you like, each scoring its tournament its own
                                way.
                            </p>
                        </div>
                    </header>

                    {games.data.length > 0 ? (
                        <div className="flex flex-col gap-10">
                            {groups.map((group) => (
                                <TournamentGroupSection
                                    key={group.tournament.id}
                                    group={group}
                                />
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

GamesIndex.layout = {
    breadcrumbs: [{ title: 'Games', href: index() }],
};
