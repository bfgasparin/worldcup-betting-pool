import { MovementArrow } from '@/components/movement-arrow';
import { cn } from '@/lib/utils';
import type { RankMovement } from '@/types/games';

export interface LeaderboardEntry {
    rank: number;
    name: string;
    initials: string;
    /** The board's headline number; null renders as "—" and suppresses podium styling. */
    primary: number | null;
    /** An optional secondary stat (e.g. the board's tie-break), already formatted with its label. */
    secondary?: string | null;
    subtitle?: string | null;
    isMe?: boolean;
    movement?: RankMovement | null;
}

const MEDALS: Record<number, string> = { 1: '🥇', 2: '🥈', 3: '🥉' };

/**
 * Avatar treatment. Podium colours (gold/silver/bronze) only apply once an entry is scored;
 * an unscored roster keeps the neutral brand gradient (or the deep-pitch fill for the viewer).
 */
function avatarGradient(rank: number, scored: boolean, isMe: boolean): string {
    if (scored && rank === 1) {
        return 'bg-gold-gradient text-[#3a2600]';
    }

    if (scored && rank === 2) {
        return 'bg-[linear-gradient(135deg,#C7D0C9,#8A958E)] text-[#1b2620]';
    }

    if (scored && rank === 3) {
        return 'bg-[linear-gradient(135deg,#D8A56B,#A66A38)] text-white';
    }

    if (isMe) {
        return 'bg-pitch-deep text-white';
    }

    return 'bg-brand-gradient text-white';
}

function formatValue(value: number | null): string {
    return value === null ? '—' : value.toLocaleString();
}

/**
 * A single ranked row for a leaderboard. Podium medals and avatar colours appear only once
 * the entry is scored; an unscored roster shows neutral row numbers.
 */
export function LeaderboardRow({
    entry,
    className,
}: {
    entry: LeaderboardEntry;
    className?: string;
}) {
    const scored = entry.primary !== null;
    const medal = scored ? MEDALS[entry.rank] : undefined;

    return (
        <div
            className={cn(
                'grid items-center gap-3 border-b border-border px-4 py-3 last:border-0 sm:px-5',
                entry.secondary
                    ? 'grid-cols-[40px_1fr_auto_auto] sm:gap-4'
                    : 'grid-cols-[36px_1fr_auto]',
                entry.isMe && 'bg-primary/[0.06]',
                className,
            )}
        >
            <div
                className={cn(
                    'text-center font-display font-semibold tabular-nums',
                    medal ? 'text-lg' : 'text-base',
                    !medal && !entry.isMe && 'text-muted-foreground',
                )}
            >
                {medal ?? entry.rank}
            </div>

            <div className="flex min-w-0 items-center gap-3">
                <span
                    className={cn(
                        'grid size-9 shrink-0 place-items-center rounded-full font-display text-sm font-semibold',
                        avatarGradient(entry.rank, scored, Boolean(entry.isMe)),
                    )}
                >
                    {entry.initials}
                </span>
                <span className="min-w-0">
                    <span className="block truncate font-display font-semibold">
                        {entry.name}
                    </span>
                    {entry.subtitle && (
                        <span className="block truncate text-xs font-medium text-muted-foreground">
                            {entry.subtitle}
                        </span>
                    )}
                </span>
            </div>

            {entry.secondary && (
                <span className="hidden rounded-full bg-primary/10 px-2.5 py-1 text-xs font-bold text-pitch-deep sm:inline-block dark:bg-primary/15 dark:text-primary">
                    {entry.secondary}
                </span>
            )}

            <div className="flex items-center justify-end gap-2">
                {entry.movement != null && (
                    <MovementArrow movement={entry.movement} />
                )}
                <span className="text-right font-display text-lg font-semibold tabular-nums">
                    {formatValue(entry.primary)}
                </span>
            </div>
        </div>
    );
}
