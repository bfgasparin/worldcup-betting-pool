import { CheckCircle2, CircleAlert } from 'lucide-react';
import { TieOrderList } from '@/components/tie-order-list';
import { cn } from '@/lib/utils';
import type { TeamRef } from '@/types/games';

/** A tied set to order, plus whether a saved ordering already resolves it. */
export interface TieCluster {
    teams: TeamRef[];
    resolved: boolean;
}

/**
 * The "there's a tie — please order these" panel. Renders one {@see TieOrderList} per tied cluster
 * (each saving via the shared route + payload), and shifts from an amber "action needed" look to a
 * green "Resolved" tag once every cluster has a saved ordering — so the user can see the system
 * read their choice. Shared by the prediction wizard and the admin score-review screen.
 */
export function TieResolutionPanel({
    title,
    description,
    clusters,
    editable,
    url,
    payloadFor,
}: {
    title: string;
    description: string;
    clusters: TieCluster[];
    editable: boolean;
    url: string;
    payloadFor: (orderedTeamIds: number[]) => Record<string, string | number[]>;
}) {
    if (clusters.length === 0) {
        return null;
    }

    const allResolved = clusters.every((cluster) => cluster.resolved);

    return (
        <div
            className={cn(
                'rounded-2xl border p-4 transition-colors',
                allResolved
                    ? 'border-primary/40 bg-primary/[0.06]'
                    : 'border-amber/40 bg-amber/[0.07]',
            )}
        >
            <div className="mb-1.5 flex items-center gap-2">
                {allResolved ? (
                    <CheckCircle2
                        className="size-4 shrink-0 text-primary"
                        aria-hidden
                    />
                ) : (
                    <CircleAlert
                        className="size-4 shrink-0 text-amber"
                        aria-hidden
                    />
                )}
                <h4
                    className={cn(
                        'font-display text-xs font-bold tracking-wide uppercase',
                        allResolved ? 'text-primary' : 'text-amber',
                    )}
                >
                    {title}
                </h4>
                {allResolved && (
                    <span className="ml-auto inline-flex items-center rounded-full bg-primary/15 px-2 py-0.5 text-[10px] font-bold tracking-wide text-primary uppercase">
                        Resolved
                    </span>
                )}
            </div>
            <p className="mb-3 text-sm text-muted-foreground">{description}</p>
            <div className="flex flex-col gap-3">
                {clusters.map((cluster) => (
                    <TieOrderList
                        key={cluster.teams.map((team) => team.id).join('-')}
                        teams={cluster.teams}
                        resolved={cluster.resolved}
                        editable={editable}
                        url={url}
                        payloadFor={payloadFor}
                    />
                ))}
            </div>
        </div>
    );
}
