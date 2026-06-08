import { cn } from '@/lib/utils';

/**
 * The signature "live" pulse: a red dot with an expanding ping ring. Red is the one deliberate
 * off-brand accent in the app — it reads instantly as a live broadcast against the pitch-green and
 * gold. Shared by the Live Center, the nav indicator, and the in-play marker on fixtures.
 */
export function LivePulse({ className }: { className?: string }) {
    return (
        <span className={cn('relative flex size-2.5', className)} aria-hidden>
            <span className="absolute inline-flex h-full w-full animate-ping rounded-full bg-red-500 opacity-75" />
            <span className="relative inline-flex size-full rounded-full bg-red-500" />
        </span>
    );
}

/** A pulsing "LIVE" pill. Use `tone="ft"` for the muted full-time / awaiting-result variant. */
export function LiveBadge({
    label = 'LIVE',
    tone = 'live',
    className,
}: {
    label?: string;
    tone?: 'live' | 'ft';
    className?: string;
}) {
    if (tone === 'ft') {
        return (
            <span
                className={cn(
                    'inline-flex items-center gap-1.5 rounded-full bg-muted px-2.5 py-1 font-display text-[0.7rem] font-bold tracking-wide text-muted-foreground uppercase',
                    className,
                )}
            >
                {label}
            </span>
        );
    }

    return (
        <span
            className={cn(
                'inline-flex items-center gap-1.5 rounded-full bg-red-500/12 px-2.5 py-1 font-display text-[0.7rem] font-bold tracking-wide text-red-600 uppercase dark:text-red-400',
                className,
            )}
        >
            <LivePulse />
            {label}
        </span>
    );
}
