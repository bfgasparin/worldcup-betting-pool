import { ChevronDown, Lock, X } from 'lucide-react';
import PlayerAvatar from '@/components/player-avatar';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import { lane } from '@/lib/compare';
import { ordinal } from '@/lib/leaderboards';
import { cn } from '@/lib/utils';
import type {
    BoardDescriptor,
    ComparePlayer,
    PredictionWindowStatus,
} from '@/types/pools';

function LaneCard({
    player,
    index,
    onRemove,
}: {
    player: ComparePlayer;
    index: number;
    onRemove?: () => void;
}) {
    const kit = lane(index);

    return (
        <div className="relative min-w-[44%] flex-1 overflow-hidden rounded-2xl border border-border bg-card p-4 shadow-[var(--sh-sm)] sm:min-w-[148px]">
            <span
                aria-hidden
                className={cn('absolute inset-y-0 left-0 w-1.5', kit.rail)}
            />

            {onRemove && (
                <button
                    type="button"
                    onClick={onRemove}
                    aria-label={`Remove ${player.name} from the comparison`}
                    className="absolute top-2 right-2 grid size-6 cursor-pointer place-items-center rounded-full text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
                >
                    <X className="size-3.5" />
                </button>
            )}

            <div className="flex items-center gap-2.5">
                <PlayerAvatar
                    name={player.name}
                    initials={player.initials}
                    src={player.avatar}
                    fallbackClassName={kit.avatar}
                    className="size-9"
                />
                <span className="min-w-0">
                    <span className="block truncate font-display font-semibold">
                        {player.name}
                    </span>
                    <span className="block text-xs font-medium text-muted-foreground">
                        {player.rank != null
                            ? ordinal(player.rank)
                            : 'Unranked'}
                    </span>
                </span>
            </div>

            <div className="mt-3 flex items-baseline gap-1">
                <span className="font-display text-2xl font-semibold tabular-nums">
                    {player.total_points ?? '—'}
                </span>
                <span className="text-xs font-semibold text-muted-foreground">
                    pts
                </span>
            </div>
        </div>
    );
}

/** A compact matrix of every board's value per player, collapsed by default. */
function BoardTotals({
    players,
    boards,
}: {
    players: ComparePlayer[];
    boards: BoardDescriptor[];
}) {
    return (
        <Collapsible>
            <CollapsibleTrigger className="flex w-full cursor-pointer items-center justify-center gap-1.5 font-display text-xs font-semibold tracking-wide text-muted-foreground uppercase transition-colors outline-none hover:text-foreground focus-visible:text-foreground [&[data-state=open]>svg]:rotate-180">
                Board totals
                <ChevronDown className="size-4 transition-transform duration-200" />
            </CollapsibleTrigger>
            <CollapsibleContent className="pt-3">
                <div className="overflow-x-auto rounded-2xl border border-border">
                    <table className="w-full border-collapse text-sm tabular-nums">
                        <thead>
                            <tr className="[&>th]:px-3 [&>th]:py-2 [&>th]:text-left [&>th]:text-[11px] [&>th]:font-bold [&>th]:tracking-wide [&>th]:text-muted-foreground [&>th]:uppercase">
                                <th>Board</th>
                                {players.map((player, index) => (
                                    <th key={index} className="!text-right">
                                        <span
                                            className="inline-flex items-center gap-1.5"
                                            title={player.name}
                                        >
                                            <span
                                                className={cn(
                                                    'size-2 rounded-full',
                                                    lane(index).dot,
                                                )}
                                                aria-hidden
                                            />
                                            {player.is_viewer
                                                ? 'You'
                                                : player.initials}
                                        </span>
                                    </th>
                                ))}
                            </tr>
                        </thead>
                        <tbody>
                            {boards.map((board) => (
                                <tr
                                    key={board.key}
                                    className="[&>td]:border-t [&>td]:border-border [&>td]:px-3 [&>td]:py-2"
                                >
                                    <td className="font-semibold whitespace-nowrap">
                                        {board.label}
                                    </td>
                                    {players.map((player, index) => {
                                        const value = player.boards.find(
                                            (entry) => entry.key === board.key,
                                        )?.primary_value;

                                        return (
                                            <td
                                                key={index}
                                                className="text-right font-display font-semibold"
                                            >
                                                {value ?? '—'}
                                            </td>
                                        );
                                    })}
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </CollapsibleContent>
        </Collapsible>
    );
}

/**
 * The compare-mode scoreboard: the head-to-head lane cards (viewer first), a banner while opponents'
 * picks are still hidden by the lock, and a collapsed per-board totals matrix. It is display-only —
 * the edit/add/exit controls live in the always-visible {@see CompareDock}; only the per-lane quick
 * remove lives here on each card.
 */
export function CompareStrip({
    players,
    windows,
    boards,
    onRemove,
}: {
    players: ComparePlayer[];
    windows: Record<string, PredictionWindowStatus>;
    boards: BoardDescriptor[];
    onRemove: (entryId: number) => void;
}) {
    // Opponents' picks stay hidden until each phase's window locks; flag that so empty lanes read as
    // "revealed later" rather than "nobody predicted". Any still-open window means some are hidden.
    const picksHidden = Object.values(windows).some(
        (status) => status !== 'locked',
    );
    const anyScores = players.some((player) => player.total_points != null);

    return (
        <section className="flex flex-col gap-4 rounded-3xl border border-border bg-card/60 p-5 shadow-[var(--sh-sm)]">
            <h2 className="font-display text-xl font-semibold tracking-tight">
                Head-to-head
            </h2>

            <div className="flex [scrollbar-width:none] gap-3 overflow-x-auto pb-1 [&::-webkit-scrollbar]:hidden">
                {players.map((player, index) => (
                    <LaneCard
                        key={player.entry_id ?? `viewer-${index}`}
                        player={player}
                        index={index}
                        onRemove={
                            player.is_viewer || player.entry_id == null
                                ? undefined
                                : () => onRemove(player.entry_id as number)
                        }
                    />
                ))}
            </div>

            {picksHidden && (
                <p className="inline-flex items-center gap-2 rounded-2xl bg-muted/60 px-4 py-2.5 text-sm font-medium text-muted-foreground">
                    <Lock className="size-4 shrink-0" />
                    Other players' picks unlock once predictions lock. Points
                    and standings still compare now.
                </p>
            )}

            {anyScores && <BoardTotals players={players} boards={boards} />}
        </section>
    );
}
