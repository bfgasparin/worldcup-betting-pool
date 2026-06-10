import { Link } from '@inertiajs/react';
import {
    CalendarDays,
    EllipsisVertical,
    PencilLine,
    Trophy,
    Users,
} from 'lucide-react';
import { CountdownBand } from '@/components/countdown-band';
import { JoinPoolDialog } from '@/components/join-pool-dialog';
import { PoolInfoDialog } from '@/components/pool-info-dialog';
import { PrizePanel } from '@/components/prize-panel';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
    SheetTrigger,
} from '@/components/ui/sheet';
import { useDisplayTimeZone } from '@/hooks/use-timezone';
import { useTranslation } from '@/hooks/use-translation';
import { cn } from '@/lib/utils';
import pools from '@/routes/pools';
import type { PoolDetail, PoolStandings } from '@/types/pools';

/**
 * The compact pool header shown only below `md`, in place of the full {@link DashboardBanner}. The
 * floating pool switcher already carries the pool name and the bottom tab bar names the section, so
 * this keeps just the essentials — tournament status, player count, dates and the prediction-lock
 * countdown — plus a tight action cluster: the prize pot (a bottom sheet), the "how it works"
 * briefing, and an overflow menu holding "Edit predictions". Non-members get a prominent Join button.
 * Comparing players is handled by the floating {@link CompareFab}, not here.
 */
export function MobilePoolHeader({
    pool,
    standings,
    className,
}: {
    pool: PoolDetail;
    standings: PoolStandings;
    className?: string;
}) {
    const { t } = useTranslation();
    const tz = useDisplayTimeZone();
    const hasEntry = standings.me !== null;
    const isPaid = pool.pricing.entry_price > 0;

    const dates = pool.starts_on
        ? pool.ends_on
            ? `${pool.starts_on} – ${pool.ends_on}`
            : pool.starts_on
        : null;

    return (
        <header
            className={cn(
                'card-elevated flex flex-col gap-3 rounded-2xl p-4 md:hidden',
                className,
            )}
        >
            <div className="flex items-start justify-between gap-3">
                <span className="min-w-0 truncate font-display text-base font-semibold text-foreground">
                    {t(pool.tournament_name)}
                </span>
                <div className="flex shrink-0 items-center gap-2">
                    {isPaid && <PrizePotSheet pool={pool} />}
                    <PoolInfoDialog pool={pool} />
                    {hasEntry && <ActionsMenu pool={pool} />}
                </div>
            </div>

            <div className="flex flex-wrap items-center gap-x-3 gap-y-1 text-sm text-muted-foreground">
                <span className="inline-flex items-center rounded-full bg-muted px-2.5 py-0.5 text-xs font-semibold capitalize">
                    {t(pool.status.replace('_', ' '))}
                </span>
                <span className="inline-flex items-center gap-1.5">
                    <Users className="size-4" />
                    {standings.participants}{' '}
                    {standings.participants === 1 ? t('player') : t('players')}
                </span>
                {dates && (
                    <span className="inline-flex items-center gap-1.5 whitespace-nowrap">
                        <CalendarDays className="size-4" />
                        {dates}
                    </span>
                )}
            </div>

            <CountdownBand
                lockAt={pool.predictions_lock_at}
                tz={tz}
                joined={hasEntry}
                canJoin={pool.can_join}
                hasScores={standings.has_scores}
            />

            {!hasEntry && pool.can_join && (
                <JoinPoolDialog pool={pool} className="w-full" />
            )}
        </header>
    );
}

/** The prize pot opened as a bottom sheet, reusing the same breakdown shown in the desktop banner. */
function PrizePotSheet({ pool }: { pool: PoolDetail }) {
    const { t } = useTranslation();

    return (
        <Sheet>
            <SheetTrigger asChild>
                <Button variant="outline" size="sm" aria-label={t('Prize pot')}>
                    <Trophy className="size-4" />
                </Button>
            </SheetTrigger>
            <SheetContent
                side="bottom"
                className="rounded-t-3xl px-4 pt-5 pb-[calc(1rem+env(safe-area-inset-bottom,0px))]"
            >
                <SheetHeader className="p-0">
                    <SheetTitle className="font-display text-base">
                        {t('Prize pot')}
                    </SheetTitle>
                    <SheetDescription className="sr-only">
                        {t('The prize pot and how it is split.')}
                    </SheetDescription>
                </SheetHeader>
                <PrizePanel
                    pricing={pool.pricing}
                    className="border-0 bg-transparent p-0"
                />
            </SheetContent>
        </Sheet>
    );
}

/** The "⋯" overflow menu — just "Edit predictions" today, shown only once the viewer has joined. */
function ActionsMenu({ pool }: { pool: PoolDetail }) {
    const { t } = useTranslation();

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button
                    variant="outline"
                    size="sm"
                    aria-label={t('More actions')}
                >
                    <EllipsisVertical className="size-4" />
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent
                align="end"
                className="min-w-56 rounded-lg [&_[data-slot=dropdown-menu-item]]:py-3"
            >
                <DropdownMenuItem asChild>
                    <Link href={pools.predict.edit(pool.slug)}>
                        <PencilLine className="size-4" />
                        {t('Edit predictions')}
                    </Link>
                </DropdownMenuItem>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
