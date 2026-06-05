import { Check } from 'lucide-react';
import { cn } from '@/lib/utils';

/**
 * The "no organizer fee" selling point: when a game takes no house cut, the whole buy-in goes into
 * the pot. Shown on the games-list card and the game page wherever the organizer fee would
 * otherwise be noted — a positive, pitch-green signal (distinct from the gold prize figures).
 */
export function NoFeeBadge({ className }: { className?: string }) {
    return (
        <span
            className={cn(
                'inline-flex w-fit items-center gap-1 rounded-full bg-primary/10 px-2.5 py-1 font-display text-[0.7rem] font-bold text-primary',
                className,
            )}
        >
            <Check className="size-3" />
            100% to players
        </span>
    );
}
