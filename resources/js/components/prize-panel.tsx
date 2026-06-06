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

            <div className="flex flex-col gap-2">
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
