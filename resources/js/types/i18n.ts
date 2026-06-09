/**
 * The translation payload shared on every Inertia request (see HandleInertiaRequests). `ui` is the
 * string-keyed bag (English source string → localized copy); the rest are domain dictionaries the
 * client resolves at display time.
 */
export interface Translations {
    /** UI / flash / notification copy, keyed by the English source string. */
    ui: Record<string, string>;
    /** Country names keyed by team code (e.g. `BRA` → `Brasil`). */
    countries: Record<string, string>;
    /** Phase display names keyed by `PhaseKey` value (e.g. `round_of_16`). */
    phases: Record<string, string>;
    /** Venue labels keyed by the full English venue string. */
    venues: Record<string, string>;
    /** Knockout placeholder words (`winner`, `runner_up`, `loser`, `third`, `group`). */
    brackets: Record<string, string>;
}

/** Replacement values interpolated into a translation string's `:placeholder` tokens. */
export type TranslationReplacements = Record<string, string | number>;
