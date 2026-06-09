/**
 * Localize a knockout placeholder label stored in English on `fixtures.home/away_placeholder_label`
 * (e.g. "Winner Group A", "3rd Group A/B/C/D", "Winner R32-1", "Loser SF-1"). The closed template
 * set is parsed and rebuilt from the `brackets` lang words; slot codes (R32-1, SF-1, …) stay raw,
 * and an unrecognized label is returned unchanged.
 *
 * Pass a `tBracket` resolver (from `useTranslation()`); kept dependency-free so it can be unit- or
 * snapshot-tested without React.
 */
export function formatPlaceholderLabel(
    label: string,
    tBracket: (word: string) => string,
): string {
    const groupSlot = /^(Winner|Runner-up) Group ([A-L])$/.exec(label);

    if (groupSlot) {
        const word = groupSlot[1] === 'Winner' ? 'winner' : 'runner_up';

        return `${tBracket(word)} ${tBracket('group')} ${groupSlot[2]}`;
    }

    const thirdSlot = /^3rd Group (.+)$/.exec(label);

    if (thirdSlot) {
        return `${tBracket('third')} ${tBracket('group')} ${thirdSlot[1]}`;
    }

    const feederSlot = /^(Winner|Loser) (.+)$/.exec(label);

    if (feederSlot) {
        const word = feederSlot[1] === 'Winner' ? 'winner' : 'loser';

        return `${tBracket(word)} ${feederSlot[2]}`;
    }

    return label;
}
