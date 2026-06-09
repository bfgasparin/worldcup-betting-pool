<?php

namespace App\Http\Middleware;

use App\Services\Live\HasLiveMatches;
use App\Services\Pools\JoinedPools;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Lang;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $request->user(),
                'isAdmin' => (bool) $request->user()?->isAdmin(),
            ],
            'timezone' => $request->cookie('timezone'),
            // The active locale and its translation bag, read by the frontend `useTranslation()`
            // hook. Domain data (countries/phases/venues/brackets) is translated client-side at
            // display time, so its dictionaries ride along with the UI string bag.
            'locale' => app()->getLocale(),
            'translations' => $this->translations(),
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            // Resolved from the container so the per-request {@see PredictionAttention} is shared
            // with the pool page and a player's bracket is never resolved twice in one request.
            'joinedPools' => app(JoinedPools::class)->forUser($request->user()),
            // Drives the navigation's animated "Live" indicator: true when the user has a live match
            // to follow in a tournament they've joined.
            'hasLiveMatches' => app(HasLiveMatches::class)->forUser($request->user()),
        ];
    }

    /**
     * The active locale's translation payload shared with the frontend: the string-keyed UI bag
     * (lang/{locale}.json) plus the domain-data dictionaries (countries/phases/venues/brackets)
     * the client resolves at display time. Static per locale, so it is cached and rebuilt only
     * when a lang file's modified time changes — which keys the cache, so edits invalidate it
     * automatically with no manual `cache:clear`.
     *
     * @return array{ui: array<string, string>, countries: array<string, string>, phases: array<string, string>, venues: array<string, string>, brackets: array<string, string>}
     */
    private function translations(): array
    {
        $locale = app()->getLocale();

        return Cache::rememberForever("inertia.translations.{$locale}.{$this->langSignature($locale)}", function () use ($locale): array {
            $jsonPath = lang_path("{$locale}.json");

            return [
                'ui' => File::exists($jsonPath) ? (json_decode(File::get($jsonPath), true) ?? []) : [],
                'countries' => $this->namespacedTranslations('countries', $locale),
                'phases' => $this->namespacedTranslations('phases', $locale),
                'venues' => $this->namespacedTranslations('venues', $locale),
                'brackets' => $this->namespacedTranslations('brackets', $locale),
            ];
        });
    }

    /**
     * Load a namespaced PHP lang file as a flat dictionary, or an empty array when the file does
     * not exist for the locale (e.g. English, where the canonical DB strings are the fallback).
     *
     * @return array<string, string>
     */
    private function namespacedTranslations(string $key, string $locale): array
    {
        $lines = Lang::get($key, [], $locale);

        return is_array($lines) ? $lines : [];
    }

    /**
     * A cache-busting signature for the locale's lang files: the newest modified time across the
     * UI bag and the domain namespaces. Editing any of them bumps the signature, so the cached
     * payload rebuilds on the next request.
     */
    private function langSignature(string $locale): string
    {
        $paths = [
            lang_path("{$locale}.json"),
            lang_path("{$locale}/countries.php"),
            lang_path("{$locale}/phases.php"),
            lang_path("{$locale}/venues.php"),
            lang_path("{$locale}/brackets.php"),
        ];

        $mtimes = array_map(fn (string $path): int => File::exists($path) ? File::lastModified($path) : 0, $paths);

        return (string) max($mtimes);
    }
}
