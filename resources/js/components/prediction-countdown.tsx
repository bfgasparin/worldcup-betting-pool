import { Lock, Timer } from 'lucide-react';
import { formatLongDate, formatMatchTime } from '@/components/fixtures';
import { useCountdown } from '@/hooks/use-countdown';
import type { Countdown } from '@/hooks/use-countdown';
import { cn } from '@/lib/utils';

/**
 * The remaining time as a compact label: the two most-significant non-zero tiers plus the tier
 * below, e.g. "2d 14h 30m" with days left, or "14h 30m 05s" under a day so it visibly ticks near
 * the deadline while staying calm when far out.
 */
function countdownLabel({ days, hours, minutes, seconds }: Countdown): string {
    const parts: string[] = [];

    if (days > 0) {
        parts.push(`${days}d`);
    }

    if (days > 0 || hours > 0) {
        parts.push(`${hours}h`);
    }

    parts.push(`${minutes}m`);

    if (days === 0) {
        parts.push(`${String(seconds).padStart(2, '0')}s`);
    }

    return parts.join(' ');
}

/**
 * The hero's prediction-lock state for an entered player. Counts down live to the lock moment,
 * then flips to the locked note once the window closes — without a reload. Falls back to the
 * formatted lock date + time on the first (pre-mount/SSR) render, so there's no hydration jump.
 */
export function PredictionCountdown({
    lockAt,
    tz,
    className,
}: {
    lockAt: string | null;
    tz: string;
    className?: string;
}) {
    const countdown = useCountdown(lockAt);

    // Locked: no lock configured, or the deadline has been reached/passed.
    if (lockAt === null || countdown?.isComplete) {
        return (
            <p
                className={cn(
                    'mt-2 inline-flex items-center gap-1.5 text-sm font-semibold text-foreground',
                    className,
                )}
            >
                <Lock className="size-4 text-primary" />
                Locked in — points unlock as results land.
            </p>
        );
    }

    // Pre-mount (SSR + first client render): a deterministic date + time, no live ticking yet.
    if (countdown === null) {
        return (
            <p
                className={cn(
                    'mt-2 inline-flex items-center gap-1.5 text-sm font-medium text-foreground',
                    className,
                )}
            >
                <Timer className="size-4 text-primary" />
                Predictions lock {formatLongDate(lockAt, tz)} at{' '}
                {formatMatchTime(lockAt, tz)}.
            </p>
        );
    }

    return (
        <p
            className={cn(
                'mt-2 inline-flex items-center gap-1.5 text-sm font-semibold text-foreground',
                className,
            )}
        >
            <Timer className="size-4 text-primary" />
            Predictions lock in{' '}
            <span className="font-display tabular-nums">
                {countdownLabel(countdown)}
            </span>
        </p>
    );
}
