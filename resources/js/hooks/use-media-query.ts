import { useCallback, useSyncExternalStore } from 'react';

/** Shared MediaQueryList per query so every subscriber listens to one source. */
const cache = new Map<string, MediaQueryList>();

function getMediaQueryList(query: string): MediaQueryList | undefined {
    if (typeof window === 'undefined') {
        return undefined;
    }

    let mql = cache.get(query);

    if (!mql) {
        mql = window.matchMedia(query);
        cache.set(query, mql);
    }

    return mql;
}

/**
 * Subscribe to a CSS media query. SSR-safe: the server and the first client
 * render report `false`, then the live value takes over after hydration. Use it
 * for behavioural gating (e.g. enabling a touch gesture only on small screens);
 * for show/hide prefer CSS `sm:`/`md:` so there is no pre-hydration flash.
 */
export function useMediaQuery(query: string): boolean {
    const subscribe = useCallback(
        (onChange: () => void) => {
            const mql = getMediaQueryList(query);

            if (!mql) {
                return () => {};
            }

            mql.addEventListener('change', onChange);

            return () => {
                mql.removeEventListener('change', onChange);
            };
        },
        [query],
    );

    const getSnapshot = useCallback(
        () => getMediaQueryList(query)?.matches ?? false,
        [query],
    );

    return useSyncExternalStore(subscribe, getSnapshot, () => false);
}
