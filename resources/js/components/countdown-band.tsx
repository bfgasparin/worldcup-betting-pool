import { Lock, Timer } from 'lucide-react';
import type { ReactNode } from 'react';
import { formatMatchDate, formatMatchTime } from '@/components/fixtures';
import { useCountdown } from '@/hooks/use-countdown';
import type { Countdown } from '@/hooks/use-countdown';
import { cn } from '@/lib/utils';

/** Urgency tier from the live countdown: calm far out, amber under a day, coral under an hour. */
type Tier = 'far' | 'soon' | 'urgent';

function tier(countdown: Countdown): Tier {
    if (countdown.days > 0) {
        return 'far';
    }

    if (countdown.hours >= 1) {
        return 'soon';
    }

    return 'urgent';
}

/** The eyebrow + icon colour per tier; the digits stay `text-foreground` at every tier. */
const TIER_ACCENT: Record<Tier, string> = {
    far: 'text-primary',
    soon: 'text-amber',
    urgent: 'text-coral',
};

/** The tile border colour per tier. */
const TIER_BORDER: Record<Tier, string> = {
    far: 'border-border',
    soon: 'border-amber/40',
    urgent: 'border-coral',
};

function pad(value: number): string {
    return String(value).padStart(2, '0');
}

function TimeTile({
    value,
    label,
    borderClass = 'border-border',
    muted,
}: {
    value: string;
    label: string;
    borderClass?: string;
    muted?: boolean;
}) {
    return (
        <div
            className={cn(
                'rounded-lg border bg-card/80 px-2 py-1 text-center',
                borderClass,
            )}
        >
            <div
                className={cn(
                    'font-display text-lg font-bold tabular-nums sm:text-xl',
                    muted ? 'text-muted-foreground/50' : 'text-foreground',
                )}
            >
                {value}
            </div>
            <div className="text-[0.55rem] font-bold tracking-[0.1em] text-muted-foreground uppercase">
                {label}
            </div>
        </div>
    );
}

function TileRow({
    days,
    hours,
    minutes,
    seconds,
    borderClass,
    muted,
    urgent,
}: {
    days: string;
    hours: string;
    minutes: string;
    seconds: string;
    borderClass?: string;
    muted?: boolean;
    urgent?: boolean;
}) {
    return (
        <div className="relative w-fit">
            {urgent && (
                <span
                    aria-hidden
                    className="pointer-events-none absolute -inset-1 rounded-xl ring-2 ring-coral/50 motion-safe:animate-pulse"
                />
            )}
            <div className="grid grid-cols-4 gap-1.5">
                <TimeTile
                    value={days}
                    label="D"
                    borderClass={borderClass}
                    muted={muted}
                />
                <TimeTile
                    value={hours}
                    label="H"
                    borderClass={borderClass}
                    muted={muted}
                />
                <TimeTile
                    value={minutes}
                    label="M"
                    borderClass={borderClass}
                    muted={muted}
                />
                <TimeTile
                    value={seconds}
                    label="S"
                    borderClass={borderClass}
                    muted={muted}
                />
            </div>
        </div>
    );
}

/** The compact strip frame: a tier-coloured eyebrow, the tile clock (or children), and a sub-line. */
function Strip({
    accentClass,
    eyebrow,
    subline,
    className,
    children,
}: {
    accentClass: string;
    eyebrow: string;
    subline: string;
    className?: string;
    children: ReactNode;
}) {
    return (
        <div className={cn('flex flex-col gap-1.5', className)}>
            <span
                className={cn(
                    'inline-flex items-center gap-1.5 text-[0.7rem] font-bold tracking-[0.12em] uppercase',
                    accentClass,
                )}
            >
                <Timer className="size-3.5" />
                {eyebrow}
            </span>
            {children}
            <p className="text-xs font-medium text-muted-foreground">
                {subline}
            </p>
        </div>
    );
}

/** The calm, resolved one-liner shown once the window is closed (never shouts in coral). */
function LockedNote({
    joined,
    className,
}: {
    joined: boolean;
    className?: string;
}) {
    return (
        <p
            className={cn(
                'inline-flex items-center gap-1.5 text-sm font-semibold text-foreground',
                className,
            )}
        >
            <Lock className="size-4 shrink-0 text-primary" />
            {joined
                ? 'Locked in — points unlock as results land.'
                : 'Joining has closed — predictions are locked.'}
        </p>
    );
}

/**
 * The hero's prediction-lock strip: a compact segmented countdown to the group-stage lock that sits
 * directly above the primary CTA. It escalates from calm pitch to amber (under a day) to coral
 * (under an hour, with a pulsing ring while the digits stay solid), then collapses to a calm
 * "locked" note once the window closes — without a reload. Non-members who can still join see the
 * same strip with a "join before it locks" eyebrow (the Join button sits in the actions row beneath
 * it). Falls back to the formatted lock date on the first (pre-mount/SSR) render, so there's no
 * hydration jump. Renders nothing once results land.
 */
export function CountdownBand({
    lockAt,
    tz,
    joined,
    canJoin,
    hasScores,
    className,
}: {
    lockAt: string | null;
    tz: string;
    joined: boolean;
    canJoin: boolean;
    hasScores: boolean;
    className?: string;
}) {
    const countdown = useCountdown(lockAt);

    // Once official results are landing the standings/leaderboard carry the context; the hero's
    // lock strip has done its job and steps aside.
    if (hasScores) {
        return null;
    }

    // No derivable lock at all — a deterministic closed state, safe to render on the server.
    if (lockAt === null) {
        return <LockedNote joined={joined} className={className} />;
    }

    const inviteToJoin = !joined && canJoin;
    const eyebrow = inviteToJoin
        ? 'Join before predictions lock'
        : 'Predictions lock in';
    const subline = `Locks ${formatMatchDate(lockAt, tz)} · ${formatMatchTime(lockAt, tz)}`;

    // Pre-mount / SSR: the live value isn't available yet. Render placeholder tiles (fixed height →
    // no hydration jump) with the deterministic lock date as the readable line.
    if (countdown === null) {
        return (
            <Strip
                accentClass="text-primary"
                eyebrow={eyebrow}
                subline={subline}
                className={className}
            >
                <TileRow days="––" hours="––" minutes="––" seconds="––" muted />
            </Strip>
        );
    }

    // The window has closed since mount — collapse to the calm locked note.
    if (countdown.isComplete) {
        return <LockedNote joined={joined} className={className} />;
    }

    // Live, open window: the segmented clock with its tier-based urgency skin.
    const currentTier = tier(countdown);

    return (
        <Strip
            accentClass={TIER_ACCENT[currentTier]}
            eyebrow={eyebrow}
            subline={subline}
            className={className}
        >
            <TileRow
                days={pad(countdown.days)}
                hours={pad(countdown.hours)}
                minutes={pad(countdown.minutes)}
                seconds={pad(countdown.seconds)}
                borderClass={TIER_BORDER[currentTier]}
                urgent={currentTier === 'urgent'}
            />
        </Strip>
    );
}
