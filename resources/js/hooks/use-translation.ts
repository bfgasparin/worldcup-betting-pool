import { usePage } from '@inertiajs/react';
import { useMemo } from 'react';
import { toBcp47 } from '@/lib/locale';
import type { TranslationReplacements } from '@/types/i18n';

/** Replace `:placeholder` tokens in a translation line with the given values. */
function interpolate(
    line: string,
    replacements?: TranslationReplacements,
): string {
    if (!replacements) {
        return line;
    }

    return line.replace(/:(\w+)/g, (match, token: string) =>
        token in replacements ? String(replacements[token]) : match,
    );
}

export interface Translator {
    /** Translate a UI string by its English source key, interpolating `:placeholders`. */
    t: (key: string, replacements?: TranslationReplacements) => string;
    /** Pick a singular/plural form ("one|other") by count, auto-injecting `:count`. */
    tChoice: (
        key: string,
        count: number,
        replacements?: TranslationReplacements,
    ) => string;
    /** Localized country name for a team code, falling back to the canonical English name. */
    tCountry: (code: string | null | undefined, fallbackName: string) => string;
    /** Localized phase name for a `PhaseKey` value, falling back to the canonical English name. */
    tPhase: (phaseKey: string, fallbackName?: string) => string;
    /** Localized venue label, falling back to the English label minus the " Stadium" suffix. */
    tVenue: (venue: string) => string;
    /** Localized knockout-placeholder word (winner/runner_up/loser/third/group). */
    tBracket: (word: string) => string;
    /** The active locale as a BCP-47 tag (e.g. "pt-BR"). */
    locale: string;
}

/**
 * Read the shared translation bag and expose the localization helpers. A missing key returns the
 * English source verbatim (the keys are the English strings), so the UI degrades to English rather
 * than showing a raw key.
 */
export function useTranslation(): Translator {
    const { translations, locale } = usePage().props;

    return useMemo<Translator>(() => {
        const ui = translations.ui;

        return {
            t: (key, replacements) => interpolate(ui[key] ?? key, replacements),
            tChoice: (key, count, replacements) => {
                const [one, other = one] = (ui[key] ?? key).split('|');

                return interpolate(count === 1 ? one : other, {
                    count,
                    ...replacements,
                });
            },
            tCountry: (code, fallbackName) =>
                code
                    ? (translations.countries[code] ?? fallbackName)
                    : fallbackName,
            tPhase: (phaseKey, fallbackName) =>
                translations.phases[phaseKey] ?? fallbackName ?? phaseKey,
            tVenue: (venue) =>
                translations.venues[venue] ?? venue.replace(/\s+Stadium$/, ''),
            tBracket: (word) => translations.brackets[word] ?? word,
            locale: toBcp47(locale),
        };
    }, [translations, locale]);
}

/** The active locale as a BCP-47 tag, for components that format dates/numbers via Intl. */
export function useDisplayLocale(): string {
    return toBcp47(usePage().props.locale);
}
