/**
 * The active locale as a BCP-47 tag (e.g. "pt-BR"), used by Intl date/number formatting. Mirrors
 * the theme/timezone init: it's captured once on load from the server-rendered `<html lang>`, so
 * pure formatters — which can't read React context — localize correctly without threading a param
 * through every call site.
 */
let activeLocale = 'pt-BR';

/** Normalize a Laravel locale ("pt_BR") to a BCP-47 tag ("pt-BR") for Intl APIs. */
export function toBcp47(locale: string): string {
    return locale.replace('_', '-');
}

/**
 * Capture the active locale from the server-rendered `<html lang>` attribute. Call once on load,
 * alongside initializeTheme() and initializeTimezone().
 */
export function initializeLocale(): void {
    if (typeof document === 'undefined') {
        return;
    }

    const lang = document.documentElement.lang;

    if (lang) {
        activeLocale = lang;
    }
}

/** The active BCP-47 locale for Intl formatting. */
export function getActiveLocale(): string {
    return activeLocale;
}
