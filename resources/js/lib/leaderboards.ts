/**
 * A plain-English tie-break rule for a board, built from its own stat labels so the copy always
 * matches how the board actually ranks (value, then the secondary stat, then entry order). Shared by
 * the Leaderboards page and the "How this game works" dialog. Boards with no secondary stat (Overall)
 * fall back to entry order.
 */
export function tiebreakRule(board: {
    primary_stat_label: string;
    secondary_stat_label: string | null;
}): string {
    const primary = board.primary_stat_label.toLowerCase();

    if (board.secondary_stat_label === null) {
        return `Tied on ${primary}? Whoever joined the pool first ranks higher.`;
    }

    return `Tied on ${primary}? Whoever has more ${board.secondary_stat_label.toLowerCase()} ranks higher.`;
}

/** Format a rank as an English ordinal: 1 → "1st", 2 → "2nd", 3 → "3rd", 11 → "11th". */
export function ordinal(n: number): string {
    const mod100 = n % 100;
    const mod10 = n % 10;
    const suffix =
        mod100 >= 11 && mod100 <= 13
            ? 'th'
            : mod10 === 1
              ? 'st'
              : mod10 === 2
                ? 'nd'
                : mod10 === 3
                  ? 'rd'
                  : 'th';

    return `${n}${suffix}`;
}
