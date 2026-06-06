import { ordinal } from '@/lib/leaderboards';
import { formatMoney } from '@/lib/money';
import type { PoolPricing } from '@/types/pools';

/** 🥇🥈🥉 for the podium, an ordinal ("4th") for anything deeper. */
const MEDALS = ['🥇', '🥈', '🥉'];

/**
 * A place's emblem: a medal for the podium, an ordinal label ("4th") beyond it. Shared by the
 * pools-list {@link PrizeSplit} teaser and the full {@link PrizePanel} breakdown.
 */
export function placeBadge(place: number): string {
    return MEDALS[place - 1] ?? ordinal(place);
}

/**
 * The formatted prize for a given finishing place (e.g. "R$ 400,00"), or null when the pot is
 * empty or that place doesn't pay. Drives the inline prize amounts on the Overall (prize) board.
 */
export function prizeForPlace(
    pricing: PoolPricing,
    place: number,
): string | null {
    if (pricing.net <= 0) {
        return null;
    }

    const prize = pricing.prizes.find((p) => p.place === place);

    return prize ? formatMoney(prize.amount, pricing.currency) : null;
}
