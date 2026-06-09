/**
 * The browser-tab title for a pool page. Leads with the pool's (verbatim) name; an optional
 * `source` is appended to tell apart pools that share a name, e.g. "Bolão Copa - FF&A · Wagner
 * Figueiredo". An optional `prefix` names the section, e.g. "Predict — Bolão Copa - FF&A · Wagner
 * Figueiredo".
 */
export function poolTitle(
    name: string,
    source?: string,
    prefix?: string,
): string {
    const identity = source ? `${name} · ${source}` : name;

    return prefix ? `${prefix} — ${identity}` : identity;
}
