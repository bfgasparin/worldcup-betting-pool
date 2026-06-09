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
import { useTranslation } from '@/hooks/use-translation';
import type { Translator } from '@/hooks/use-translation';
import { resolveAccent, sourceMonogram } from '@/lib/accents';
import type { PoolAccent } from '@/lib/accents';
import { formatMoney } from '@/lib/money';
import { cn } from '@/lib/utils';
import { index, show } from '@/routes/pools';
import type { PoolListItem, Paginated } from '@/types/pools';

interface PoolsIndexProps {
    pools: Paginated<PoolListItem>;
}

/** A tournament and the pools played over it, in the order they arrived from the server. */
interface TournamentGroup {
    tournament: PoolListItem['tournament'];
    pools: PoolListItem[];
}

function greeting(t: Translator['t']): string {
    const hour = new Date().getHours();

    if (hour < 12) {
        return t('Good morning');
    }

    if (hour < 18) {
        return t('Good afternoon');
    }

    return t('Good evening');
}

/**
 * Cluster the page's pools by the tournament they're played over, preserving order. Sibling pools
 * arrive adjacent (the server orders by tournament then id), so each tournament forms one run.
 */
function groupByTournament(pools: PoolListItem[]): TournamentGroup[] {
    const groups: TournamentGroup[] = [];
    const byId = new Map<number, TournamentGroup>();

    for (const pool of pools) {
        let group = byId.get(pool.tournament.id);

        if (!group) {
            group = { tournament: pool.tournament, pools: [] };
            byId.set(pool.tournament.id, group);
            groups.push(group);
        }

        group.pools.push(pool);
    }

    return groups;
}

function formatDates(pool: PoolListItem): string | null {
    if (!pool.starts_on) {
        return null;
    }

    return pool.ends_on
        ? `${pool.starts_on} – ${pool.ends_on}`
        : pool.starts_on;
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

/** "12 players" / "1 player" / "No players yet" — a pool's player count. */
function playersLabel(count: number, t: Translator['t']): string {
    if (count === 0) {
        return t('No players yet');
    }

    return count === 1 ? t('1 player') : t(':count players', { count });
}

/** A pool's pool size — its own per-pool stat, the same shape as the tournament stat pills. */
function PlayersStat({ count }: { count: number }) {
    const { t } = useTranslation();

    return <StatPill icon={Users} label={playersLabel(count, t)} />;
}

/**
 * The shared tournament facts (sport, group & match counts) — identical for every sibling pool.
 * `playersCount`, when given, appends the pool's own pool size (used on a solo pool's card, where
 * the shared facts and the per-pool count sit on one row).
 */
function StatPills({
    pool,
    playersCount,
}: {
    pool: PoolListItem;
    playersCount?: number;
}) {
    const { t } = useTranslation();

    return (
        <div className="flex flex-wrap gap-2">
            <StatPill
                icon={Trophy}
                label={<span className="capitalize">{pool.sport}</span>}
            />
            {pool.groups_count != null && (
                <StatPill
                    icon={Layers}
                    label={t(':count Groups', { count: pool.groups_count })}
                />
            )}
            {pool.fixtures_count != null && (
                <StatPill
                    icon={Sparkles}
                    label={t(':count Matches', { count: pool.fixtures_count })}
                />
            )}
            {playersCount != null && <PlayersStat count={playersCount} />}
        </div>
    );
}

function StatusBadge({ status }: { status: string }) {
    const { t } = useTranslation();

    return (
        <span className="bg-brand-gradient inline-flex items-center gap-1.5 rounded-full px-3 py-1 font-display text-xs font-semibold text-white capitalize shadow-[var(--sh-sm)]">
            {t(status.replace('_', ' '))}
        </span>
    );
}

function DateRange({ pool }: { pool: PoolListItem }) {
    const dates = formatDates(pool);

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

/** The accent-coloured call-to-action at the foot of a ticket. */
function EnterButton({ accent }: { accent: PoolAccent }) {
    const { t } = useTranslation();

    return (
        <span
            className={cn(
                'inline-flex w-fit items-center gap-2 rounded-full px-6 py-3 font-display text-base font-semibold transition-all group-hover:gap-3',
                accent.buttonClass,
                accent.glowClass,
                accent.textClass,
            )}
        >
            {t('View pool')}
            <ArrowRight className="size-5" />
        </span>
    );
}

/**
 * A slim kit-colour bar across the top of a ticket. It's the per-pool visual anchor that tells
 * sibling pools (played over the same tournament) apart at a glance, without competing with the
 * pool's name — the name now leads the body.
 */
function AccentBar({ accent }: { accent: PoolAccent }) {
    return (
        <div
            className={cn('h-1.5 w-full shrink-0', accent.railClass)}
            aria-hidden
        />
    );
}

/**
 * A small source-monogram chip in the pool's kit colour, shown beside the pool name as a quiet
 * source cue.
 */
function SourceChip({
    source,
    accent,
}: {
    source: string;
    accent: PoolAccent;
}) {
    return (
        <span
            className={cn(
                'flex size-10 shrink-0 items-center justify-center rounded-xl font-display text-sm leading-none font-bold shadow-[var(--sh-sm)]',
                accent.railClass,
                accent.textClass,
            )}
            aria-hidden
        >
            {sourceMonogram(source)}
        </span>
    );
}

/**
 * A single pool as a ticket: a slim kit-colour accent bar atop a name-led body. The pool's name is
 * the hero, with a small source-monogram chip beside it and a muted source · scoring subline; a
 * pool that's alone over its tournament also carries its status, dates and stats, plus the
 * tournament name in the subline.
 */
function PoolTicket({
    pool,
    grouped,
}: {
    pool: PoolListItem;
    /**
     * Whether this ticket sits in a multi-pool group on this page (and so under a
     * {@see TournamentHeader} carrying the shared facts). Driven by the visible group, not a global
     * count, so the body and the header can never disagree — a pool shown on its own always renders
     * the full solo layout rather than an orphaned, contextless compact ticket.
     */
    grouped: boolean;
}) {
    const accent = resolveAccent(pool.accent, pool.accent_index);
    const { t } = useTranslation();

    return (
        <Link
            href={show(pool.slug)}
            className={cn(
                'group card-elevated flex flex-col overflow-hidden rounded-3xl transition-transform duration-200 hover:-translate-y-1',
                accent.ringClass,
            )}
        >
            <AccentBar accent={accent} />

            <div className="flex flex-1 flex-col gap-4 p-6 sm:p-7">
                {grouped ? (
                    <div className="flex flex-wrap items-start justify-between gap-2.5">
                        <div className="flex min-w-0 items-center gap-3">
                            <SourceChip source={pool.source} accent={accent} />
                            <div className="flex min-w-0 flex-col gap-1">
                                <h3 className="text-2xl font-semibold tracking-tight text-balance text-foreground">
                                    {pool.name}
                                </h3>
                                <span className="font-display text-sm font-semibold text-muted-foreground">
                                    {pool.source} · {pool.scoring_label}
                                </span>
                            </div>
                        </div>
                        <PlayersStat count={pool.players_count} />
                    </div>
                ) : (
                    <>
                        <div className="flex flex-wrap items-center justify-between gap-2.5">
                            <StatusBadge status={pool.status} />
                            <DateRange pool={pool} />
                        </div>
                        <div className="flex min-w-0 items-center gap-3">
                            <SourceChip source={pool.source} accent={accent} />
                            <div className="flex min-w-0 flex-col gap-1">
                                <h2 className="text-2xl font-semibold tracking-tight text-balance text-foreground sm:text-3xl">
                                    {pool.name}
                                </h2>
                                <span className="font-display text-sm font-semibold text-muted-foreground">
                                    {pool.source} · {t(pool.tournament_name)} ·{' '}
                                    {pool.scoring_label}
                                </span>
                            </div>
                        </div>
                        <StatPills
                            pool={pool}
                            playersCount={pool.players_count}
                        />
                    </>
                )}

                <p className="text-sm text-muted-foreground">
                    {pool.scoring_description}
                </p>

                <PrizeSplit pricing={pool.pricing} canJoin={pool.can_join} />

                <div className="mt-auto flex flex-wrap items-center gap-3">
                    {pool.joined && (
                        <StatPill icon={Check} label={t('Joined')} />
                    )}
                    <EnterButton accent={accent} />
                </div>
            </div>
        </Link>
    );
}

/**
 * A pool as a compact, tappable list row — the mobile counterpart to {@link PoolTicket}. It leads
 * with what differs between sibling pools (source, scoring style + its one-line explainer, buy-in
 * and pool size) so the list stays scannable and comparable; the whole row is the link into the
 * pool. A solo pool (no tournament header above it) carries its status inline.
 */
function PoolRow({ pool, grouped }: { pool: PoolListItem; grouped: boolean }) {
    const accent = resolveAccent(pool.accent, pool.accent_index);
    const { t } = useTranslation();
    const money = pool.can_join
        ? t(':amount buy-in', {
              amount: formatMoney(
                  pool.pricing.entry_price,
                  pool.pricing.currency,
              ),
          })
        : pool.pricing.net > 0
          ? t('Pot :amount', {
                amount: formatMoney(pool.pricing.net, pool.pricing.currency),
            })
          : null;

    return (
        <Link
            href={show(pool.slug)}
            className="flex items-center gap-3 px-4 py-3.5 transition-colors hover:bg-muted/50"
        >
            <span
                className={cn(
                    'flex size-10 shrink-0 items-center justify-center rounded-xl font-display text-sm leading-none font-bold shadow-[var(--sh-sm)]',
                    accent.railClass,
                    accent.textClass,
                )}
            >
                {sourceMonogram(pool.source)}
            </span>

            <div className="min-w-0 flex-1">
                <div className="flex items-center gap-2">
                    <span className="truncate font-display text-base font-semibold text-foreground">
                        {pool.name}
                    </span>
                    {pool.joined && (
                        <Check className="size-4 shrink-0 text-primary" />
                    )}
                    {!grouped && <StatusBadge status={pool.status} />}
                </div>
                <span className="mt-0.5 block font-display text-xs font-semibold text-primary">
                    {pool.source} · {pool.scoring_label}
                </span>
                <p className="line-clamp-1 text-xs text-muted-foreground">
                    {pool.scoring_description}
                </p>
                <div className="mt-1 flex flex-wrap items-center gap-x-2 gap-y-0.5 text-xs text-muted-foreground">
                    {money && (
                        <span className="font-semibold text-foreground">
                            {money}
                        </span>
                    )}
                    {money && <span aria-hidden>·</span>}
                    <span>{playersLabel(pool.players_count, t)}</span>
                </div>
            </div>

            <ChevronRight className="size-5 shrink-0 text-muted-foreground" />
        </Link>
    );
}

/**
 * A header above a cluster of same-tournament pools. It carries the facts every sibling shares —
 * name, status, dates, sport & counts — once, so the tickets beneath it only have to show what
 * makes each different. Framed as multiple pools over one competition, not as a pick-one choice.
 * The "N pools" chip counts the pools shown here, so it always matches the tickets beneath it.
 */
function TournamentHeader({ group }: { group: TournamentGroup }) {
    const lead = group.pools[0];
    const { t } = useTranslation();

    return (
        <div className="flex flex-col gap-3 border-b border-border pb-4 sm:pb-5">
            <div className="flex flex-wrap items-center gap-2.5 sm:justify-between">
                <div className="flex flex-wrap items-center gap-2.5">
                    <StatusBadge status={lead.status} />
                    <DateRange pool={lead} />
                </div>
                <Chip
                    variant="outline"
                    className="hidden px-3 py-1 text-xs sm:inline-flex"
                >
                    {t(':count pools', { count: group.pools.length })}
                </Chip>
            </div>
            <h2 className="text-xl font-semibold tracking-tight text-balance text-foreground sm:text-3xl">
                {t(group.tournament.name)}
            </h2>
            <StatPills pool={lead} />
            <p className="max-w-xl text-sm text-muted-foreground">
                {t(
                    'Each pool below scores this competition its own way — play as many as you like.',
                )}
            </p>
        </div>
    );
}

/**
 * One tournament's block: a single pool renders as a lone ticket; multiple pools render under a
 * shared tournament header, each in its own kit colour.
 */
function TournamentGroupSection({ group }: { group: TournamentGroup }) {
    if (group.pools.length === 1) {
        const pool = group.pools[0];

        return (
            <>
                {/* Mobile: the same compact row, in its own list card. */}
                <div className="overflow-hidden rounded-2xl border border-border bg-card lg:hidden">
                    <PoolRow pool={pool} grouped={false} />
                </div>
                {/* Desktop: the full ticket. */}
                <div className="hidden lg:block">
                    <PoolTicket pool={pool} grouped={false} />
                </div>
            </>
        );
    }

    return (
        <section className="flex flex-col gap-4 lg:gap-5">
            <TournamentHeader group={group} />
            {/* Mobile: a tight, scannable divided list of pools. */}
            <div className="divide-y divide-border overflow-hidden rounded-2xl border border-border bg-card lg:hidden">
                {group.pools.map((pool) => (
                    <PoolRow key={pool.slug} pool={pool} grouped />
                ))}
            </div>
            {/* Desktop: the rich ticket grid. */}
            <div className="hidden gap-5 lg:grid lg:grid-cols-2 2xl:grid-cols-3">
                {group.pools.map((pool) => (
                    <PoolTicket key={pool.slug} pool={pool} grouped />
                ))}
            </div>
        </section>
    );
}

/** Previous / next controls, shown only when the list spans more than one page. */
function Pagination({ pools }: { pools: Paginated<PoolListItem> }) {
    const { t } = useTranslation();

    if (pools.last_page <= 1) {
        return null;
    }

    return (
        <nav className="mt-8 flex items-center justify-between gap-4">
            {pools.prev_page_url ? (
                <Button variant="outline" asChild>
                    <Link href={pools.prev_page_url} preserveScroll>
                        <ChevronLeft className="size-4" />
                        {t('Previous')}
                    </Link>
                </Button>
            ) : (
                <Button variant="outline" disabled>
                    <ChevronLeft className="size-4" />
                    {t('Previous')}
                </Button>
            )}

            <span className="text-sm font-medium text-muted-foreground">
                {t('Page :current of :last', {
                    current: pools.current_page,
                    last: pools.last_page,
                })}
            </span>

            {pools.next_page_url ? (
                <Button variant="outline" asChild>
                    <Link href={pools.next_page_url} preserveScroll>
                        {t('Next')}
                        <ChevronRight className="size-4" />
                    </Link>
                </Button>
            ) : (
                <Button variant="outline" disabled>
                    {t('Next')}
                    <ChevronRight className="size-4" />
                </Button>
            )}
        </nav>
    );
}

export default function PoolsIndex({ pools }: PoolsIndexProps) {
    const groups = groupByTournament(pools.data);
    const { auth } = usePage().props;
    const { t } = useTranslation();
    const name = auth.user?.name ?? t('there');
    const firstName = name.split(' ')[0] || name;

    return (
        <>
            <Head title={t('Pools')} />
            <div className="relative min-h-full bg-background">
                <div className="relative w-full px-4 py-6 sm:px-6 sm:py-8 lg:px-8 xl:px-10">
                    <header className="hero relative mb-6 overflow-hidden rounded-3xl border border-border p-5 sm:mb-8 sm:p-8">
                        <div className="hero-lines" />
                        <div className="relative flex flex-col gap-3">
                            <span className="inline-flex w-fit items-center gap-2 text-xs font-bold tracking-[0.14em] text-muted-foreground uppercase">
                                <span className="bg-brand-gradient size-2 rounded-full" />
                                Brothers Bets
                            </span>
                            <span className="text-sm font-semibold text-muted-foreground">
                                {t(':greeting, :name 👋', {
                                    greeting: greeting(t),
                                    name: firstName,
                                })}
                            </span>
                            <h1 className="text-3xl font-semibold tracking-tight text-balance text-foreground sm:text-5xl">
                                {t('Join a pool')}
                            </h1>
                            <span className="bg-gold-gradient mt-1 h-1 w-12 rounded-full" />
                            <p className="max-w-2xl text-sm text-muted-foreground sm:text-base">
                                {t(
                                    'Browse the pools below, check the buy-in and prize pot, and buy into the ones you fancy. There’s no picking just one — play as many as you like, each scoring its tournament its own way.',
                                )}
                            </p>
                        </div>
                    </header>

                    {pools.data.length > 0 ? (
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
                            {t('No pools are available yet.')}
                        </p>
                    )}

                    <Pagination pools={pools} />
                </div>
            </div>
        </>
    );
}

PoolsIndex.layout = {
    breadcrumbs: [{ title: 'Pools', href: index() }],
};
