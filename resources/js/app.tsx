import { createInertiaApp, router } from '@inertiajs/react';
import { Toaster } from '@/components/ui/sonner';
import { TooltipProvider } from '@/components/ui/tooltip';
import { initializeTheme } from '@/hooks/use-appearance';
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

// This will set light / dark mode on load...
initializeTheme();

// Persist the browser timezone in a cookie so the server can render times in it (SSR-ready)...
initializeTimezone();

// Capture the active locale from the server-rendered <html lang> so Intl date/number formatting
// matches the app language without threading the locale through every formatter call site.
initializeLocale();
