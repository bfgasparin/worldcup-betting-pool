import { usePage } from '@inertiajs/react';

const COOKIE = 'timezone';

function browserTimeZone(): string {
    try {
        return Intl.DateTimeFormat().resolvedOptions().timeZone || 'UTC';
    } catch {
        return 'UTC';
    }
}

const setCookie = (name: string, value: string, days = 365): void => {
    if (typeof document === 'undefined') {
        return;
    }

    const maxAge = days * 24 * 60 * 60;
    document.cookie = `${name}=${value};path=/;max-age=${maxAge};SameSite=Lax`;
};

/**
 * Persist the browser's timezone in a (server-readable) cookie, so the server — including a
 * future SSR render — formats kick-off times in the same zone the client will. Mirrors the
 * appearance cookie; call once on load alongside initializeTheme().
 */
export function initializeTimezone(): void {
    if (typeof window === 'undefined') {
        return;
    }

    setCookie(COOKIE, browserTimeZone());
}

/**
 * The timezone fixture times should render in: the cookie-derived shared value (set on the
 * server, so SSR and client agree), falling back to the browser's zone before the cookie exists.
 */
export function useDisplayTimeZone(): string {
    const { timezone } = usePage().props;

    return timezone ?? browserTimeZone();
}
