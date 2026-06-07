import { NoFeeBadge } from '@/components/no-fee-badge';
import { ordinal } from '@/lib/leaderboards';
import { formatMoney } from '@/lib/money';
import { placeBadge } from '@/lib/prizes';
import { cn } from '@/lib/utils';
import type { PoolPricing } from '@/types/pools';

/**
 * The full money breakdown for a pool's page: the prize pot, each place's share (the raw amount net
 * of the house fee, kept next to its percentage so the split stays clear), the buy-in, and the
 * organizer's cut. Before anyone has joined the pool is empty, so the shares show as percentages
 * under a "grows as players join" header. The lighter pools-list teaser is {@link PrizeSplit}.
 */
export function PrizePanel({
    pricing,
    className,
}: {
    pricing: PoolPricing;
    className?: string;
}) {
    const hasMoney = pricing.net > 0;

    return (
        <div
            className={cn(
                'flex flex-col gap-4 rounded-2xl border border-border bg-card/80 p-5',
                className,
            )}
        >
            <div className="flex items-baseline justify-between gap-3">
                <span className="text-[0.65rem] font-bold tracking-[0.12em] text-muted-foreground uppercase">
                    Prize pot
                </span>
                <span className="font-display text-xl font-bold text-foreground">
                    {hasMoney
                        ? formatMoney(pricing.pot, pricing.currency)
                        : 'Grows as players join'}
                </span>
            </div>

            <p className="-mt-2 text-xs text-muted-foreground">
                Awarded to the top of the{' '}
                <span className="font-semibold text-foreground">Overall</span>{' '}
                leaderboard.
            </p>

            {/* Mobile/tablet (full-width): an even 3-up grid keeps the places aligned with no gutters. */}
            <div className="grid grid-cols-3 gap-2 lg:hidden">
                {pricing.prizes.map((prize) => (
                    <div
                        key={prize.place}
                        className="flex flex-col items-center gap-0.5 rounded-xl border border-border bg-card/60 px-2 py-2.5 text-center"
                    >
                        <span aria-hidden className="text-base leading-none">
                            {placeBadge(prize.place)}
                        </span>
                        <span className="font-display text-sm font-bold text-[#8a5a00] dark:text-amber-300">
                            {hasMoney
                                ? formatMoney(prize.amount, pricing.currency)
                                : `${prize.percentage}%`}
                        </span>
                        {hasMoney && (
                            <span className="text-[11px] font-semibold text-muted-foreground">
                                {prize.percentage}%
                            </span>
                        )}
                    </div>
                ))}
            </div>

            {/* Desktop (narrow 22rem column): the labelled vertical list. */}
            <div className="hidden flex-col gap-2 lg:flex">
                {pricing.prizes.map((prize) => (
                    <div
                        key={prize.place}
                        className="flex items-center justify-between gap-3 text-sm"
                    >
                        <span className="inline-flex items-center gap-2 font-medium text-foreground">
                            <span aria-hidden>{placeBadge(prize.place)}</span>
                            {ordinal(prize.place)} place
                        </span>
                        <span className="font-display font-bold text-[#8a5a00] dark:text-amber-300">
                            {hasMoney ? (
                                <>
                                    {formatMoney(
                                        prize.amount,
                                        pricing.currency,
                                    )}
                                    <span className="ml-1.5 text-xs font-semibold text-muted-foreground">
                                        {prize.percentage}%
                                    </span>
                                </>
                            ) : (
                                `${prize.percentage}%`
                            )}
                        </span>
                    </div>
                ))}
            </div>

            <div className="flex flex-wrap items-center justify-between gap-2 border-t border-border pt-3 text-sm">
                <span className="text-foreground">
                    <span className="text-muted-foreground">Buy-in</span>{' '}
                    <b className="font-display">
                        {formatMoney(pricing.entry_price, pricing.currency)}
                    </b>
                </span>
                {pricing.house_fee_percentage > 0 ? (
                    <span className="text-muted-foreground">
                        Organizer fee {pricing.house_fee_percentage}%
                    </span>
                ) : (
                    <NoFeeBadge />
                )}
            </div>
        </div>
    );
}
