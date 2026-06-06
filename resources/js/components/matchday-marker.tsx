import { cn } from '@/lib/utils';

/**
 * The stripe + chip colour classes for a matchday, keyed by its descriptor key. The three group
 * rounds get distinct on-brand tints (pitch green / amber / teal) so a player can tell MD1 from MD2
 * at a glance; knockout matchdays — already identified by their own section/date — get a neutral
 * tone. Kept as a plain class map (not a CSS token) since it's a one-off affordance for these views.
 */
export function matchdayAccent(key: string | null): {
    stripe: string;
    chip: string;
} {
    switch (key) {
        case 'group-1':
            return {
                stripe: 'bg-pitch',
                chip: 'bg-pitch/15 text-pitch-deep dark:text-grass',
            };
        case 'group-2':
            return { stripe: 'bg-amber', chip: 'bg-amber/15 text-amber' };
        case 'group-3':
            return { stripe: 'bg-chart-3', chip: 'bg-chart-3/15 text-chart-3' };
        default:
            return {
                stripe: 'bg-border',
                chip: 'bg-secondary text-muted-foreground',
            };
    }
}

/** The short label for a group round ("group-2" → "MD2"); null for knockout matchdays or none. */
export function groupMatchdayLabel(key: string | null): string | null {
    const match = key?.match(/^group-(\d+)$/);

    return match ? `MD${match[1]}` : null;
}

/**
 * The thin coloured rail down the left edge of a match row, marking which matchday it belongs to.
 * Render inside a `relative` row that reserves room for it (e.g. `pl-3`).
 */
export function MatchdayStripe({
    matchdayKey,
    className,
}: {
    matchdayKey: string | null;
    className?: string;
}) {
    return (
        <span
            aria-hidden
            className={cn(
                'absolute top-1.5 bottom-1.5 left-0 w-1 rounded-full',
                matchdayAccent(matchdayKey).stripe,
                className,
            )}
        />
    );
}

/**
 * A tiny "MD1/MD2/MD3" pill naming a group-stage match's matchday. Renders nothing for knockout
 * matchdays (their round is already obvious from the section/date), keeping the views uncluttered.
 */
export function MatchdayChip({
    matchdayKey,
    className,
}: {
    matchdayKey: string | null;
    className?: string;
}) {
    const label = groupMatchdayLabel(matchdayKey);

    if (label === null) {
        return null;
    }

    return (
        <span
            className={cn(
                'inline-flex items-center rounded-full px-1.5 py-0.5 font-display text-[9px] font-bold tracking-wide uppercase',
                matchdayAccent(matchdayKey).chip,
                className,
            )}
        >
            {label}
        </span>
    );
}
