/**
 * The browser-tab title for a game page. Leads with the source so games over the same tournament
 * (which share a name) are distinguishable in the tab strip, e.g. "FF&A · World Cup 2026". An
 * optional `prefix` names the section, e.g. "Predict — FF&A · World Cup 2026".
 */
export function gameTitle(
    source: string,
    name: string,
    prefix?: string,
): string {
    const identity = `${source} · ${name}`;

    return prefix ? `${prefix} — ${identity}` : identity;
}
