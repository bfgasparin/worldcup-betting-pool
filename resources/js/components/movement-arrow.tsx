import { ArrowDown, ArrowUp, Minus } from 'lucide-react';
import { cn } from '@/lib/utils';
import type { RankMovement } from '@/types/pools';

/**
 * A compact indicator of how an entry moved on the pool table since the last approved results:
 * an up/down arrow, a neutral dash, a "New" tag on first appearance, or nothing before any
 * results have landed.
 */
export function MovementArrow({
    movement,
    className,
}: {
    movement: RankMovement | null;
    className?: string;
}) {
    if (movement === null) {
        return null;
    }

    if (movement === 'new') {
        return (
            <span
                className={cn(
                    'font-display text-[10px] font-bold tracking-wide text-primary uppercase',
                    className,
                )}
            >
                New
            </span>
        );
    }

    if (movement === 'same') {
        return (
            <Minus
                className={cn('size-3.5 text-muted-foreground/50', className)}
                aria-label="No change"
            />
        );
    }

    const up = movement === 'up';
    const Icon = up ? ArrowUp : ArrowDown;

    return (
        <Icon
            className={cn(
                'size-3.5',
                up ? 'text-pitch-deep dark:text-primary' : 'text-destructive',
                className,
            )}
            aria-label={up ? 'Moved up' : 'Moved down'}
        />
    );
}
