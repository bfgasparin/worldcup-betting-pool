import type { LeaderboardEntry } from '@/components/leaderboard-row';
import { StandingsList } from '@/components/standings-list';
import { prizeForPlace } from '@/lib/prizes';
import type { BoardRow, LeaderboardBoard, PoolPricing } from '@/types/pools';

/** The board's secondary stat, formatted with its label (e.g. "27 team goals"). */
function secondaryStat(
    board: LeaderboardBoard,
    value: number | null,
): string | null {
    if (board.secondary_stat_label === null || value === null) {
        return null;
    }

    return `${value} ${board.secondary_stat_label.toLowerCase()}`;
}

/**
 * The official pool standings for a board. Maps the persisted rows to the shared {@see StandingsList}
 * (lazy reveal + "jump to me"), folding in the per-place prize when the board awards one.
 *
 * Remount this (via a `key` on the active board + matchday) so the revealed count resets when the
 * viewer switches board or travels to another matchday.
 */
export function LeaderboardStandings({
    rows,
    board,
    showPrizes,
    pricing,
}: {
    rows: BoardRow[];
    board: LeaderboardBoard;
    showPrizes: boolean;
    pricing: PoolPricing;
}) {
    const entries: LeaderboardEntry[] = rows.map((row) => ({
        rank: row.rank,
        name: row.name,
        initials: row.initials,
        avatar: row.avatar,
        primary: row.primary_value,
        secondary: secondaryStat(board, row.secondary_value),
        isMe: row.is_me,
        movement: row.movement,
        movementDelta: row.movement_delta,
        prize: showPrizes ? prizeForPlace(pricing, row.rank) : undefined,
    }));

    return (
        <StandingsList
            entries={entries}
            primaryStatLabel={board.primary_stat_label}
        />
    );
}
