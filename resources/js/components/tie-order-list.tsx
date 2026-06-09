import { router } from '@inertiajs/react';
import {
    Check,
    ChevronDown,
    ChevronUp,
    GripVertical,
    Loader2,
} from 'lucide-react';
import { useRef, useState } from 'react';
import { Flag } from '@/components/flag';
import { Button } from '@/components/ui/button';
import { useTranslation } from '@/hooks/use-translation';
import { cn } from '@/lib/utils';
import type { TeamRef } from '@/types/pools';

type SaveStatus = 'idle' | 'saving' | 'saved' | 'error';

/**
 * One tied set the engine could not separate, which a human orders by hand (a within-group cluster
 * or the best-thirds cut). Rows reorder by native drag-and-drop or up/down buttons; every change is
 * saved immediately, and a "Confirm order" button lets the user lock in the shown order without
 * dragging. The footer feeds back what the system read: "Saving…", then a persistent green "Order
 * saved" once a matching ordering resolves the tie.
 */
export function TieOrderList({
    teams,
    resolved,
    editable,
    url,
    payloadFor,
}: {
    teams: TeamRef[];
    resolved: boolean;
    editable: boolean;
    url: string;
    payloadFor: (orderedTeamIds: number[]) => Record<string, string | number[]>;
}) {
    const { t, tCountry } = useTranslation();
    const [order, setOrder] = useState<TeamRef[]>(teams);
    const [status, setStatus] = useState<SaveStatus>('idle');

    // Re-seed local order when the server hands us a different set/order (derived state, no refs).
    const signature = teams.map((team) => team.id).join(',');
    const [syncedSignature, setSyncedSignature] = useState(signature);

    if (syncedSignature !== signature) {
        setSyncedSignature(signature);
        setOrder(teams);
    }

    const dragIndex = useRef<number | null>(null);

    const save = (next: TeamRef[]): void => {
        router.put(url, payloadFor(next.map((team) => team.id)), {
            preserveScroll: true,
            preserveState: true,
            onStart: () => setStatus('saving'),
            onSuccess: () => setStatus('saved'),
            onError: () => setStatus('error'),
        });
    };

    const move = (from: number, to: number): void => {
        if (to < 0 || to >= order.length || from === to) {
            return;
        }

        const next = order.slice();
        const [moved] = next.splice(from, 1);
        next.splice(to, 0, moved);

        setOrder(next);
        save(next);
    };

    return (
        <div className="flex flex-col gap-2">
            <ul className="flex flex-col gap-1.5">
                {order.map((team, index) => (
                    <li
                        key={team.id}
                        draggable={editable}
                        onDragStart={() => {
                            dragIndex.current = index;
                        }}
                        onDragOver={(event) => {
                            if (editable) {
                                event.preventDefault();
                            }
                        }}
                        onDrop={() => {
                            if (dragIndex.current !== null) {
                                move(dragIndex.current, index);
                            }

                            dragIndex.current = null;
                        }}
                        className={cn(
                            'flex items-center gap-2 rounded-xl border border-border bg-background px-3 py-2',
                            editable && 'cursor-grab active:cursor-grabbing',
                        )}
                    >
                        <span className="w-4 text-center font-display text-sm text-muted-foreground">
                            {index + 1}
                        </span>
                        {editable && (
                            <GripVertical
                                className="size-4 shrink-0 text-muted-foreground"
                                aria-hidden
                            />
                        )}
                        <Flag team={team} className="h-4 w-6 shrink-0" />
                        <span className="flex-1 truncate text-sm font-bold">
                            {tCountry(team.code, team.name)}
                        </span>
                        {editable && (
                            <span className="flex shrink-0 items-center gap-1">
                                <button
                                    type="button"
                                    aria-label={t('Move :team up', {
                                        team: tCountry(team.code, team.name),
                                    })}
                                    disabled={index === 0}
                                    onClick={() => move(index, index - 1)}
                                    className="rounded-md p-1 text-muted-foreground hover:bg-muted disabled:opacity-30"
                                >
                                    <ChevronUp className="size-4" />
                                </button>
                                <button
                                    type="button"
                                    aria-label={t('Move :team down', {
                                        team: tCountry(team.code, team.name),
                                    })}
                                    disabled={index === order.length - 1}
                                    onClick={() => move(index, index + 1)}
                                    className="rounded-md p-1 text-muted-foreground hover:bg-muted disabled:opacity-30"
                                >
                                    <ChevronDown className="size-4" />
                                </button>
                            </span>
                        )}
                    </li>
                ))}
            </ul>

            {editable && (
                <div className="flex items-center justify-between gap-2">
                    <TieStatus status={status} resolved={resolved} />
                    {/* Only needed to lock in the shown order before it's first resolved — every
                        drag/arrow move autosaves, so once resolved the order edits in place. */}
                    {!resolved && status !== 'saving' && (
                        <Button
                            type="button"
                            size="sm"
                            variant="outline"
                            onClick={() => save(order)}
                        >
                            {t('Confirm order')}
                        </Button>
                    )}
                </div>
            )}
        </div>
    );
}

function TieStatus({
    status,
    resolved,
}: {
    status: SaveStatus;
    resolved: boolean;
}) {
    const { t } = useTranslation();

    if (status === 'saving') {
        return (
            <span className="inline-flex items-center gap-1.5 text-xs font-semibold text-muted-foreground">
                <Loader2 className="size-3.5 animate-spin" />
                {t('Saving your order…')}
            </span>
        );
    }

    if (status === 'error') {
        return (
            <span className="text-xs font-semibold text-destructive">
                {t("Couldn't save — try again.")}
            </span>
        );
    }

    if (resolved || status === 'saved') {
        return (
            <span className="inline-flex items-center gap-1.5 text-xs font-semibold text-pitch-deep dark:text-primary">
                <Check className="size-3.5" />
                {t('Order saved')}
            </span>
        );
    }

    return (
        <span className="text-xs font-semibold text-muted-foreground">
            {t('Drag to set the order, then confirm.')}
        </span>
    );
}
