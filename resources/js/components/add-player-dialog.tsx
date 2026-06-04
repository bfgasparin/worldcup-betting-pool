import { Check, Search } from 'lucide-react';
import { useState } from 'react';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { ordinal } from '@/lib/leaderboards';
import { cn } from '@/lib/utils';
import type { PlayerDirectoryEntry } from '@/types/games';

/**
 * A searchable picker over the whole pool, for adding players that aren't in the on-page leaderboard
 * preview. Selection is a controlled toggle so it stays in sync with the row "+" affordances and the
 * tray; the viewer's own row is excluded, and the list locks once the cap is reached.
 */
export function AddPlayerDialog({
    open,
    onOpenChange,
    directory,
    selectedIds,
    onToggle,
    limit,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    directory: PlayerDirectoryEntry[];
    selectedIds: number[];
    onToggle: (entryId: number) => void;
    limit: number;
}) {
    const [query, setQuery] = useState('');

    // Start each opening from a clean search rather than the previous session's query. Adjusting
    // state during render (the React-recommended pattern) avoids a set-state-in-effect round-trip.
    const [wasOpen, setWasOpen] = useState(open);

    if (open !== wasOpen) {
        setWasOpen(open);

        if (open) {
            setQuery('');
        }
    }

    const q = query.trim().toLowerCase();
    const candidates = directory.filter((player) => !player.is_me);
    const filtered = q
        ? candidates.filter((player) => player.name.toLowerCase().includes(q))
        : candidates;
    const atLimit = selectedIds.length >= limit;

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-w-md">
                <DialogHeader>
                    <DialogTitle>Add players to compare</DialogTitle>
                    <DialogDescription>
                        Pick up to {limit} players to line up against you.
                    </DialogDescription>
                </DialogHeader>

                <div className="relative">
                    <Search className="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                    <Input
                        value={query}
                        onChange={(event) => setQuery(event.target.value)}
                        placeholder="Search players…"
                        aria-label="Search players"
                        className="pl-9"
                        autoFocus
                    />
                </div>

                <div className="-mx-1 max-h-[320px] overflow-y-auto px-1">
                    {filtered.length === 0 ? (
                        <p className="py-8 text-center text-sm text-muted-foreground">
                            No players found.
                        </p>
                    ) : (
                        <ul className="flex flex-col gap-1">
                            {filtered.map((player) => {
                                const selected = selectedIds.includes(
                                    player.entry_id,
                                );
                                const disabled = !selected && atLimit;

                                return (
                                    <li key={player.entry_id}>
                                        <button
                                            type="button"
                                            disabled={disabled}
                                            onClick={() =>
                                                onToggle(player.entry_id)
                                            }
                                            aria-pressed={selected}
                                            className={cn(
                                                'flex w-full cursor-pointer items-center gap-3 rounded-xl border px-3 py-2 text-left transition-colors',
                                                selected
                                                    ? 'border-primary/40 bg-primary/[0.06]'
                                                    : 'border-transparent hover:bg-muted',
                                                disabled &&
                                                    'cursor-not-allowed opacity-50',
                                            )}
                                        >
                                            <span className="bg-brand-gradient grid size-9 shrink-0 place-items-center rounded-full font-display text-sm font-semibold text-white">
                                                {player.initials}
                                            </span>
                                            <span className="min-w-0 flex-1">
                                                <span className="block truncate font-display font-semibold">
                                                    {player.name}
                                                </span>
                                                <span className="block text-xs text-muted-foreground">
                                                    {ordinal(player.rank)}
                                                    {player.points != null
                                                        ? ` · ${player.points} pts`
                                                        : ''}
                                                </span>
                                            </span>
                                            <span
                                                className={cn(
                                                    'grid size-5 shrink-0 place-items-center rounded-full border',
                                                    selected
                                                        ? 'border-primary bg-primary text-white'
                                                        : 'border-border',
                                                )}
                                            >
                                                {selected && (
                                                    <Check className="size-3.5" />
                                                )}
                                            </span>
                                        </button>
                                    </li>
                                );
                            })}
                        </ul>
                    )}
                </div>

                {atLimit && (
                    <p className="text-center text-xs font-medium text-muted-foreground">
                        Maximum of {limit} players reached.
                    </p>
                )}
            </DialogContent>
        </Dialog>
    );
}
