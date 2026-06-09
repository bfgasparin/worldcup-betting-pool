<?php

namespace App\Concerns;

use App\Models\User;
use App\Support\LocaleResolver;

/**
 * Shared `--locale` handling for the user-creation commands: validates the option against the
 * supported locales so an organizer can pre-assign a user's language (which then drives their
 * login-code email via {@see User::preferredLocale()}).
 */
trait ResolvesLocaleOption
{
    /**
     * The validated --locale option: null when omitted (follow the device language), a supported
     * locale string, or false when an unsupported value was given (an error is printed).
     */
    private function resolveLocale(): string|false|null
    {
        $locale = trim((string) $this->option('locale')) ?: null;

        if ($locale === null) {
            return null;
        }

        $supported = app(LocaleResolver::class)->supported();

        if (! in_array($locale, $supported, true)) {
            $this->components->error("Unsupported --locale '{$locale}'. Supported: ".implode(', ', $supported).'.');

            return false;
        }

        return $locale;
    }
}
