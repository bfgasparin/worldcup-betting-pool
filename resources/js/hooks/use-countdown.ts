import { useCallback, useMemo, useSyncExternalStore } from 'react';

export interface Countdown {
    days: number;
    hours: number;
    minutes: number;
    seconds: number;
    /** Remaining milliseconds, clamped to >= 0. */
    total: number;
    /** True once the target is reached/passed (or the target is null/unparseable). */
    isComplete: boolean;
}

/** Break whole, non-negative seconds remaining into day/hour/minute/second parts. */
function partsFromSeconds(totalSeconds: number): Countdown {
    const seconds = Math.max(0, totalSeconds);

    return {
        total: seconds * 1000,
        days: Math.floor(seconds / 86400),
        hours: Math.floor((seconds % 86400) / 3600),
        minutes: Math.floor((seconds % 3600) / 60),
        seconds: seconds % 60,
        isComplete: seconds <= 0,
    };
}

/** Server + first client (hydration) render: null → the caller shows a static fallback. */
function getServerSnapshot(): number | null {
    return null;
}

/**
 * Live countdown to an ISO target, recomputed every second from `target - Date.now()`.
 *
 * Built on `useSyncExternalStore` so it's SSR-safe by construction: the server and the first
 * client (hydration) render both read `getServerSnapshot` (null → caller renders a deterministic
 * fallback), then the live value takes over post-hydration with no mismatch. The snapshot is
 * whole seconds remaining (clamped to >= 0), so it never goes negative and stays stable between
 * ticks; the interval stops the moment the target is reached. A null/unparseable target reads as
 * complete.
 */
export function useCountdown(target: string | null): Countdown | null {
    const targetMs = useMemo(
        () => (target !== null ? new Date(target).getTime() : NaN),
        [target],
    );

    const subscribe = useCallback(
        (onStoreChange: () => void) => {
            // Nothing to count toward (no lock, or already past) — no interval needed.
            if (Number.isNaN(targetMs) || targetMs <= Date.now()) {
                return () => {};
            }

            const id = setInterval(() => {
                onStoreChange();

                if (targetMs <= Date.now()) {
                    clearInterval(id);
                }
            }, 1000);

            return () => {
                clearInterval(id);
            };
        },
        [targetMs],
    );

    // Whole seconds remaining, clamped. Returning a primitive keeps the value stable between
    // ticks, so the store never loops. A null/unparseable target reads as 0 (complete).
    const getSnapshot = useCallback((): number | null => {
        if (Number.isNaN(targetMs)) {
            return 0;
        }

        return Math.max(0, Math.ceil((targetMs - Date.now()) / 1000));
    }, [targetMs]);

    const secondsRemaining = useSyncExternalStore(
        subscribe,
        getSnapshot,
        getServerSnapshot,
    );

    return useMemo(
        () =>
            secondsRemaining === null
                ? null
                : partsFromSeconds(secondsRemaining),
        [secondsRemaining],
    );
}
