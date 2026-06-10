<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" @class(['dark' => ($appearance ?? 'system') == 'dark'])>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">

        {{-- Inline script to detect system dark mode preference and apply it immediately --}}
        <script>
            (function() {
                const appearance = '{{ $appearance ?? "system" }}';

                if (appearance === 'system') {
                    const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;

                    if (prefersDark) {
                        document.documentElement.classList.add('dark');
                    }
                }
            })();
        </script>

        {{-- Inline style to set the HTML background color based on our theme in app.css --}}
        <style>
            html {
                background-color: #fafbf8;
            }

            html.dark {
                background-color: #0b1410;
            }
        </style>

        <link rel="icon" href="/favicon.ico" sizes="any">
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">

        {{-- PWA: makes the app installable to the home screen and launch standalone --}}
        <link rel="manifest" href="/manifest.webmanifest">
        <meta name="theme-color" content="#0fa968">
        <meta name="mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
        <meta name="apple-mobile-web-app-title" content="Brothers Bets">

        @fonts

        @viteReactRefresh
        @vite(['resources/css/app.css', 'resources/js/app.tsx', "resources/js/pages/{$page['component']}.tsx"])
        <x-inertia::head>
            <title>{{ config('app.name', 'Laravel') }}</title>
        </x-inertia::head>
    </head>
    <body class="font-sans antialiased">
        <x-inertia::app />

        {{-- Portrait-only PWA gate. iOS ignores the manifest `orientation` lock, so when the
             installed app is held in landscape on a phone we cover the screen with this notice.
             Pure CSS show/hide (see the `pwa-landscape` variant in app.css) — no JS, so it works
             before hydration and on every page. Hidden by default and in normal browser tabs. --}}
        <div class="orientation-gate hidden pwa-landscape:flex bg-brand-gradient fixed inset-0 z-[100] flex-col items-center justify-center gap-4 p-8 text-center text-white">
            <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none"
                 stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                 class="opacity-90" aria-hidden="true">
                <rect width="14" height="20" x="5" y="2" rx="2" ry="2"/><path d="M12 18h.01"/>
            </svg>
            <p class="font-display text-xl font-semibold">{{ __('Rotate your device to portrait') }}</p>
            <p class="max-w-xs text-sm text-white/80">{{ __('Brothers Bets works best held upright.') }}</p>
        </div>
    </body>
</html>
