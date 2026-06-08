import { router } from '@inertiajs/react';
import { useEffect } from 'react';

/**
 * Polls the Live Center while matches are in play, partial-reloading only the live-changing props.
 * The interval pauses when the tab is hidden and stops on unmount or once nothing is live. The
 * refresh is a single isolated call, so a future Laravel Echo listener can drive the same reload in
 * place of the interval (Reverb-ready) without touching the page.
 */
export function useLivePoll({
    intervalMs,
    active,
    only,
}: {
    intervalMs: number;
    active: boolean;
    /** Stable reference (declare at module scope) — the partial-reload prop keys. */
    only: string[];
}) {
    useEffect(() => {
        if (!active) {
            return;
        }

        let timer: ReturnType<typeof setInterval> | null = null;

        const refresh = () => router.reload({ only });

        const start = () => {
            timer ??= setInterval(refresh, intervalMs);
        };

        const stop = () => {
            if (timer !== null) {
                clearInterval(timer);
                timer = null;
            }
        };

        const onVisibilityChange = () => {
            if (document.hidden) {
                stop();

                return;
            }

            refresh();
            start();
        };

        if (!document.hidden) {
            start();
        }

        document.addEventListener('visibilitychange', onVisibilityChange);

        return () => {
            stop();
            document.removeEventListener(
                'visibilitychange',
                onVisibilityChange,
            );
        };
    }, [active, intervalMs, only]);
}
