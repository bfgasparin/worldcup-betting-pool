import { ordinal } from '@/lib/leaderboards';

/** 🥇🥈🥉 for the podium, an ordinal ("4th") for anything deeper. */
const MEDALS = ['🥇', '🥈', '🥉'];

/**
 * A place's emblem: a medal for the podium, an ordinal label ("4th") beyond it. Shared by the
 * games-list {@link PrizeSplit} teaser and the full {@link PrizePanel} breakdown.
 */
export function placeBadge(place: number): string {
    return MEDALS[place - 1] ?? ordinal(place);
}
