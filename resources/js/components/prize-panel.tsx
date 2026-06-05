import { Coins } from 'lucide-react';
import { ordinal } from '@/lib/leaderboards';
import { formatMoney } from '@/lib/money';
import { cn } from '@/lib/utils';
import type { GamePricing } from '@/types/games';

/** 🥇🥈🥉 for the podium, an ordinal ("4th") for anything deeper. */
const MEDALS = ['🥇', '🥈', '🥉'];

function placeBadge(place: number): string {
    return MEDALS[place - 1] ?? ordinal(place);
}

/** "after 15% organizer fee", or null when the organizer takes no cut. */
function feeNote(pricing: GamePricing): string | null {
    return pricing.house_fee_percentage > 0
        ? `after ${pricing.house_fee_percentage}% organizer fee`
        : null;
}

/**
 * The money side of a game: the buy-in and the per-place prizes (raw amounts, net of the house
 * fee). Before anyone has joined the pool is empty, so the split shows as percentages with a
 * "grows as players join" hint instead of a row of R$0,00. Shared by the games list card and the
 * game banner.
 */
export function PrizePanel({
    pricing,
    className,
}: {
    pricing: GamePricing;
    className?: string;
}) {
    const hasMoney = pricing.net > 0;
    const fee = feeNote(pricing);

    const footer = hasMoney
        ? [`From a ${formatMoney(pricing.pool, pricing.currency)} pool`, fee]
              .filter(Boolean)
              .join(' · ')
        : ['Prizes grow as players join', fee].filter(Boolean).join(' · ');

    return (
        <div
            className={cn(
                'flex flex-col gap-3 rounded-2xl border border-border bg-secondary/40 p-4',
                className,
            )}
        >
            <div className="flex items-center justify-between gap-3">
                <span className="text-[0.65rem] font-bold tracking-[0.12em] text-muted-foreground uppercase">
                    Buy-in
                </span>
                <span className="font-display text-lg font-bold text-foreground">
                    {formatMoney(pricing.entry_price, pricing.currency)}
                </span>
            </div>

            <div className="flex items-center gap-2">
                <Coins className="size-4 shrink-0 text-accent" />
                <div className="flex flex-wrap gap-1.5">
                    {pricing.prizes.map((prize) => (
                        <span
                            key={prize.place}
                            className="inline-flex items-center gap-1.5 rounded-full bg-card px-2.5 py-1 text-xs font-semibold shadow-[var(--sh-sm)]"
                        >
                            <span className="sr-only">
                                {ordinal(prize.place)} place:{' '}
                            </span>
                            <span aria-hidden>{placeBadge(prize.place)}</span>
                            <b className="font-display text-[#8a5a00] dark:text-amber-300">
                                {hasMoney
                                    ? formatMoney(prize.amount, pricing.currency)
                                    : `${prize.percentage}%`}
                            </b>
                        </span>
                    ))}
                </div>
            </div>

            {footer && (
                <span className="text-[0.7rem] text-muted-foreground">
                    {footer}
                </span>
            )}
        </div>
    );
}
