import type { Translator } from '@/hooks/use-translation';

/**
 * A localized tie-break rule for a board, built from its own stat labels so the copy always
 * matches how the board actually ranks (value, then the secondary stat, then entry order). Shared by
 * the Leaderboards page and the "How this pool works" dialog. Boards with no secondary stat (Overall)
 * fall back to entry order. Takes `t` from `useTranslation()` since it's a plain helper, not a hook.
 */
export function tiebreakRule(
    board: {
        primary_stat_label: string;
        secondary_stat_label: string | null;
    },
    t: Translator['t'],
): string {
    const primary = board.primary_stat_label.toLowerCase();

    if (board.secondary_stat_label === null) {
        return t('Tied on :stat? Whoever joined the pool first ranks higher.', {
            stat: primary,
        });
    }

    return t('Tied on :stat? Whoever has more :secondary ranks higher.', {
        stat: primary,
        secondary: board.secondary_stat_label.toLowerCase(),
    });
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
