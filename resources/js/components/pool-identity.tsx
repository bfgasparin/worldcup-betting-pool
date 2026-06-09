import { useTranslation } from '@/hooks/use-translation';
import { resolveAccent, sourceMonogram } from '@/lib/accents';
import { cn } from '@/lib/utils';

interface PoolIdentityProps {
    /** The group/source that runs the pool, e.g. "Wagner Figueiredo" — secondary context, shown verbatim. */
    source: string;
    /**
     * The pool's own name — the headline. Shown verbatim (like the brand and source), never
     * translated. Omit it on surfaces that already render the name in their own title, so this
     * lockup carries only the secondary context.
     */
    name?: string;
    /** The tournament the pool is played over; canonical English from the DB, translated here. */
    tournament?: string;
    /** Short scoring-style label, e.g. "Upfront Bracket". */
    scoringLabel?: string;
    /** The pool's stored accent key; defaults to the house pitch when absent. */
    accent?: string | null;
    /** `banner` is the larger lockup for a page hero; `compact` sits beneath an existing title. */
    variant?: 'banner' | 'compact';
    className?: string;
}

/**
 * The pool identity lockup shown on every in-pool surface: a source monogram in the pool's accent
 * colour, the pool's (verbatim) name as the headline, and the source · tournament · scoring style as
 * a muted subline that tells sibling pools — played over the same tournament — apart. Pass `name`
 * where this lockup is the title; omit it where the surface already shows the name and only the
 * secondary context is needed.
 */
export function PoolIdentity({
    source,
    name,
    tournament,
    scoringLabel,
    accent,
    variant = 'compact',
    className,
}: PoolIdentityProps) {
    const { t } = useTranslation();
    const kit = resolveAccent(accent);
    // The source is verbatim (like the brand); the tournament name is canonical English from the
    // DB, translated at display time; scoringLabel is already server-resolved.
    const subline = [
        source,
        tournament ? t(tournament) : undefined,
        scoringLabel,
    ]
        .filter(Boolean)
        .join(' · ');

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
                {name && (
                    <span
                        className={cn(
                            'truncate font-display font-semibold text-foreground',
                            variant === 'banner' ? 'text-xl' : 'text-base',
                        )}
                    >
                        {name}
                    </span>
                )}
                {subline && (
                    <span className="truncate font-display text-sm font-semibold text-muted-foreground">
                        {subline}
                    </span>
                )}
            </div>
        </div>
    );
}
