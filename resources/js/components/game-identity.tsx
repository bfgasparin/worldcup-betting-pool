import { resolveAccent, sourceMonogram } from '@/lib/accents';
import { cn } from '@/lib/utils';

interface GameIdentityProps {
    /** The group/source that runs the pool, e.g. "FF&A" — the thing that differs from sibling games. */
    source: string;
    /** The (shared) game name, shown as secondary context next to the scoring style. */
    name?: string;
    /** Short scoring-style label, e.g. "Upfront Bracket". */
    scoringLabel?: string;
    /** The game's stored accent key; defaults to the house pitch when absent. */
    accent?: string | null;
    /** `banner` is the larger lockup for a page hero; `compact` sits beneath an existing title. */
    variant?: 'banner' | 'compact';
    className?: string;
}

/**
 * The source-led identity lockup shown on every in-game surface so a game is never mistaken for a
 * sibling played over the same tournament: a source monogram in the game's accent colour, the
 * "Game by {source}" eyebrow, and the (shared) name + scoring style as secondary context.
 */
export function GameIdentity({
    source,
    name,
    scoringLabel,
    accent,
    variant = 'compact',
    className,
}: GameIdentityProps) {
    const kit = resolveAccent(accent);
    const subline = [name, scoringLabel].filter(Boolean).join(' · ');

    return (
        <div className={cn('flex items-center gap-3', className)}>
            <span
                className={cn(
                    'flex shrink-0 items-center justify-center rounded-2xl font-display leading-none font-bold shadow-[var(--sh-sm)]',
                    kit.railClass,
                    kit.textClass,
                    variant === 'banner' ? 'size-12 text-xl' : 'size-9 text-sm',
                )}
            >
                {sourceMonogram(source)}
            </span>
            <div className="flex min-w-0 flex-col">
                <span className="inline-flex flex-wrap items-center gap-1.5 text-xs font-bold tracking-[0.14em] uppercase">
                    <span className="text-muted-foreground opacity-70">
                        Game by
                    </span>
                    <span className="text-foreground">{source}</span>
                </span>
                {subline && (
                    <span className="truncate font-display text-sm font-semibold text-muted-foreground">
                        {subline}
                    </span>
                )}
            </div>
        </div>
    );
}
