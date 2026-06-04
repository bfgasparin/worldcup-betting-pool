import { cn } from '@/lib/utils';
import type { TeamRef } from '@/types/games';

const PLACEHOLDER_FLAG = '/flags/_placeholder.svg';

/**
 * A team's national flag, with a generic fallback for unknown qualifiers or missing assets.
 * Flags are served from public/flags (see the `flags:import` Artisan command).
 */
export function Flag({
    team,
    className,
}: {
    team: TeamRef | null | undefined;
    className?: string;
}) {
    const source = team?.flag_url ?? PLACEHOLDER_FLAG;

    return (
        <img
            src={source}
            alt={team ? `${team.name} flag` : ''}
            title={team ? team.name : undefined}
            loading="lazy"
            onError={(event) => {
                const image = event.currentTarget;

                if (!image.src.endsWith(PLACEHOLDER_FLAG)) {
                    image.src = PLACEHOLDER_FLAG;
                }
            }}
            className={cn(
                'inline-block h-3.5 w-5 shrink-0 rounded-[2px] object-cover ring-1 ring-border/60',
                className,
            )}
        />
    );
}
