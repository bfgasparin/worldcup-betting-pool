import { Trophy } from 'lucide-react';
import { NoFeeBadge } from '@/components/no-fee-badge';
import { ordinal } from '@/lib/leaderboards';
import { formatMoney } from '@/lib/money';
import { placeBadge } from '@/lib/prizes';
import { cn } from '@/lib/utils';
import type { GamePricing } from '@/types/games';

/**
 * A place's tint on the split bar: a gentle gold→amber ramp that gets a touch deeper down the
 * podium so adjacent segments stay distinct, while the *width* — not the colour — carries which
 * place is biggest. Index-keyed (not place-keyed) so it degrades gracefully for 4+ places, the
 * softest shade repeating beyond the ramp. Both bases are theme-stable brand tokens, so contrast
 * holds in light and dark mode.
 */
function segmentColor(index: number): string {
    const RAMP = ['bg-gold', 'bg-amber', 'bg-amber/70', 'bg-amber/45'];

    return RAMP[index] ?? RAMP[RAMP.length - 1];
}

/**
 * The card-side prize teaser, shaped as a **distribution** rather than a hero number. A slim
 * horizontal bar is split into one segment per place, each segment's width proportional to that
 * place's percentage — so the *shape* of the split is legible at a glance: a 50/30/20 game reads
 * visibly flatter (more evenly shared) than a top-heavy 70/20/10, without any place being shouted
 * by a giant figure. A compact legend underneath names each place (medal/ordinal) and its value.
 *
 * The bar is **always sized by percentage** — the distribution shape is identical whether we show
 * percentages or amounts. The legend *values* follow the same rule the rest of the card uses: while
 * the pool is still filling (the game can be joined) each place reads as its **percentage** share,
 * with a "grows as players join" hint and the buy-in; once joining closes with a real pool, the
 * legend switches to the **raw amounts** and the buy-in drops away. The organizer's cut is noted
 * quietly in the footnote; with none, a "100% to players" badge is shown instead. The full
 * breakdown lives on the game page ({@link PrizePanel}).
 */
export function PrizeSplit({
    pricing,
    canJoin,
    className,
}: {
    pricing: GamePricing;
    canJoin: boolean;
    className?: string;
}) {
    // Raw amounts only once joining has closed and a pool actually exists; otherwise the percentage
    // share (which also avoids a row of R$0,00 before anyone has joined).
    const showRaw = !canJoin && pricing.net > 0;

    if (pricing.prizes.length === 0) {
        return null;
    }

    const prizeValue = (prize: {
        amount: number;
        percentage: number;
    }): string =>
        showRaw
            ? formatMoney(prize.amount, pricing.currency)
            : `${prize.percentage}%`;

    const baseFooter = showRaw
        ? `From a ${formatMoney(pricing.pool, pricing.currency)} prize pool`
        : canJoin
          ? 'Prize pool grows as players join'
          : 'A share of the prize pool';

    // The split is of the pool after the organizer's cut — surfaced quietly, for information, so a
    // player isn't surprised the percentages apply to the net pool.
    const footer =
        pricing.house_fee_percentage > 0
            ? `${baseFooter} · after ${pricing.house_fee_percentage}% organizer fee`
            : baseFooter;

    return (
        <div
            className={cn(
                'flex flex-col gap-3 rounded-2xl border border-border bg-secondary/40 p-4',
                className,
            )}
        >
            <span className="inline-flex items-center gap-1.5 text-[0.65rem] font-bold tracking-[0.14em] text-muted-foreground uppercase">
                <Trophy className="size-3.5 text-accent" />
                Prize split
            </span>

            {/* The split drawn proportionally. flexGrow keyed to the percentage (with flexBasis 0)
                keeps segments proportional independent of the gaps; min-w-[8%] stops a ~10% slice
                collapsing. Decorative — the legend below conveys the same info accessibly. */}
            <div className="flex h-2.5 gap-0.5" aria-hidden>
                {pricing.prizes.map((prize, index) => (
                    <span
                        key={prize.place}
                        className={cn(
                            'min-w-[8%] rounded-full',
                            segmentColor(index),
                        )}
                        style={{ flexGrow: prize.percentage, flexBasis: 0 }}
                    />
                ))}
            </div>

            <div className="flex flex-wrap gap-x-3 gap-y-1.5">
                {pricing.prizes.map((prize) => (
                    <span
                        key={prize.place}
                        className="inline-flex items-center gap-1.5 text-xs font-medium text-muted-foreground"
                    >
                        <span className="sr-only">
                            {ordinal(prize.place)} place:{' '}
                        </span>
                        <span aria-hidden>{placeBadge(prize.place)}</span>
                        <b className="font-display font-semibold text-[#8a5a00] dark:text-amber-300">
                            {prizeValue(prize)}
                        </b>
                    </span>
                ))}
            </div>

            <span className="text-[0.7rem] text-muted-foreground">
                {footer}
            </span>

            {pricing.house_fee_percentage === 0 && <NoFeeBadge />}

            {canJoin && (
                <div className="flex items-center justify-between gap-3 border-t border-border/60 pt-3">
                    <span className="text-[0.65rem] font-bold tracking-[0.12em] text-muted-foreground uppercase">
                        Buy-in
                    </span>
                    <span className="font-display text-base font-bold text-foreground">
                        {formatMoney(pricing.entry_price, pricing.currency)}
                    </span>
                </div>
            )}
        </div>
    );
}
