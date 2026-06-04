import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { cn } from '@/lib/utils';

type Props = {
    name: string;
    initials: string;
    /** The player's photo URL, or null/undefined to fall back to coloured initials. */
    src?: string | null;
    /** Coloured fill shown behind the initials when there is no photo. */
    fallbackClassName?: string;
    /**
     * Identity/rank colour carried as a ring around the avatar (e.g. `ring-2 ring-amber/60`).
     * Stays visible whether or not a photo is shown, so colour-based tracking survives photos.
     */
    ringClassName?: string;
    /** Size and any extra classes for the avatar root (e.g. `size-9`). */
    className?: string;
};

/**
 * A player's avatar: their photo when set, otherwise coloured initials. An optional coloured ring
 * carries the identity/rank colour (podium on leaderboards, lane in compare mode) so the colour cue
 * is preserved even once a photo replaces the coloured fill. Shared by the leaderboard rows, the
 * compare lane cards, and the board-leader cards.
 */
export default function PlayerAvatar({
    name,
    initials,
    src,
    fallbackClassName,
    ringClassName,
    className,
}: Props) {
    return (
        <Avatar className={cn('shrink-0', ringClassName, className)}>
            <AvatarImage
                src={src ?? undefined}
                alt={name}
                className="object-cover"
            />
            <AvatarFallback
                className={cn(
                    'rounded-full font-display text-sm font-semibold',
                    fallbackClassName,
                )}
            >
                {initials}
            </AvatarFallback>
        </Avatar>
    );
}
