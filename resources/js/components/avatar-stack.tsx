import PlayerAvatar from '@/components/player-avatar';
import { cn } from '@/lib/utils';

export interface StackPlayer {
    id: number;
    name: string;
    initials: string;
    avatar?: string | null;
    isMe?: boolean;
}

/**
 * Up to three overlapping player avatars — used to show a tie on a stat card (e.g. "6 players tied").
 * A single player renders as one avatar (the same look as a lone board-leader). Pass the leaders in
 * display order; extras beyond three are dropped (the caller shows the real count alongside).
 */
export function AvatarStack({
    players,
    className,
}: {
    players: StackPlayer[];
    className?: string;
}) {
    return (
        <div className={cn('flex shrink-0', className)}>
            {players.slice(0, 3).map((player, index) => (
                <PlayerAvatar
                    key={player.id}
                    name={player.name}
                    initials={player.initials}
                    src={player.avatar}
                    fallbackClassName={
                        player.isMe
                            ? 'bg-pitch-deep text-white'
                            : 'bg-brand-gradient text-white'
                    }
                    ringClassName="ring-2 ring-card"
                    className={cn('size-9', index > 0 && '-ml-2')}
                />
            ))}
        </div>
    );
}
