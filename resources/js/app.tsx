import { createInertiaApp, router } from '@inertiajs/react';
import { Toaster } from '@/components/ui/sonner';
import { TooltipProvider } from '@/components/ui/tooltip';
import { initializeTheme } from '@/hooks/use-appearance';
import { initializeInstallPrompt } from '@/hooks/use-install-prompt';
import { initializeTimezone } from '@/hooks/use-timezone';
import AppLayout from '@/layouts/app-layout';
import AuthLayout from '@/layouts/auth-layout';
import SettingsLayout from '@/layouts/settings/layout';
import { initializeLocale } from '@/lib/locale';

const appName = import.meta.env.VITE_APP_NAME || 'Brothers Bets';

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    layout: (name) => {
        switch (true) {
            case name === 'welcome':
            case name === 'onboarding/wizard':
                return null;
            case name.startsWith('auth/'):
                return AuthLayout;
            case name.startsWith('settings/'):
                return [AppLayout, SettingsLayout];
            default:
                return AppLayout;
        }
    },
    strictMode: true,
    withApp(app) {
        return (
            <TooltipProvider delayDuration={0}>
                {app}
                <Toaster />
            </TooltipProvider>
        );
    },
    progress: {
        color: '#4B5563',
    },
});

// Prefetch caches store whole responses, including shared props (joinedPools, the
// needs_attention dot). Flush them after each navigation so the sidebar never renders
// a stale snapshot captured before a join or a prediction change.
router.on('navigate', () => router.flushAll());

// Touch devices have no :hover, so every tappable element gets a visible press animation via the
// `.press` utility (see app.css). iOS Safari only fires `:active` on non-button elements (e.g. Radix
// menu/select items, which render as role-divs) when a touch listener exists on the document — this
// passive no-op is the canonical enabler. Harmless on devices that already fire `:active` natively.
document.addEventListener('touchstart', () => {}, { passive: true });

// This will set light / dark mode on load...
initializeTheme();

// Persist the browser timezone in a cookie so the server can render times in it (SSR-ready)...
initializeTimezone();

// Capture the active locale from the server-rendered <html lang> so Intl date/number formatting
// matches the app language without threading the locale through every formatter call site.
initializeLocale();

// Listen for the browser's install signals so we can offer "Add to home screen" at the right moment.
initializeInstallPrompt();

// Register the install-only service worker so the app is installable. Production + secure-context
// only (localhost counts as secure, so dev installability still works over http://localhost); we
// skip it under `npm run dev` because a service worker fights Vite's HMR. A failed registration must
// never break the app, so errors are swallowed.
if (
    typeof window !== 'undefined' &&
    'serviceWorker' in navigator &&
    import.meta.env.PROD &&
    window.isSecureContext
) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js').catch(() => {});
    });
}
