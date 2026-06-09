<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Resolves the active locale for a request. Priority: an authenticated user's explicit preference,
 * then the device language from the browser's `Accept-Language` header (mapped to a supported
 * locale), then the app's fallback locale. The supported set comes from
 * `config('app.supported_locales')`.
 */
class LocaleResolver
{
    /**
     * The locale codes the app can run in (the keys of `config('app.supported_locales')`).
     *
     * @return list<string>
     */
    public function supported(): array
    {
        return array_keys(config('app.supported_locales', ['en' => 'English']));
    }

    /**
     * The locale to use for this request: the user's stored choice (if a supported value), else the
     * browser's device language, else the configured fallback.
     */
    public function resolve(Request $request): string
    {
        $preferred = $request->user()?->locale;

        if ($preferred !== null && in_array($preferred, $this->supported(), true)) {
            return $preferred;
        }

        return $this->deviceLocale($request) ?? config('app.fallback_locale', 'en');
    }

    /**
     * The device language from `Accept-Language`, mapped to a supported locale, or null when none
     * match. Each requested language (most-preferred first) is matched by exact code, then by base
     * language — so "pt" / "pt_PT" resolve to "pt_BR", and "en_US" resolves to "en".
     */
    public function deviceLocale(Request $request): ?string
    {
        $supported = $this->supported();

        // First supported locale per base language, e.g. ['pt' => 'pt_BR', 'en' => 'en'].
        $byBase = [];
        foreach ($supported as $code) {
            $byBase[strtolower(explode('_', $code)[0])] ??= $code;
        }

        foreach ($request->getLanguages() as $language) {
            $normalized = str_replace('-', '_', $language);

            foreach ($supported as $code) {
                if (strcasecmp($normalized, $code) === 0) {
                    return $code;
                }
            }

            $base = strtolower(explode('_', $normalized)[0]);

            if (isset($byBase[$base])) {
                return $byBase[$base];
            }
        }

        return null;
    }
}
