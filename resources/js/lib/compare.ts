import type { ComparePlayer, PredictionWindowStatus } from '@/types/games';

/**
 * Up to four visually distinct comparison lanes. Lane 0 (the viewer) is the house pitch; the three
 * opponents reuse the existing kit accents (teal, gold, violet) so the palette stays on-brand and
 * no fifth colour is needed. Each lane pairs a colour with the player's initials everywhere it is
 * shown, so the comparison never relies on colour alone (colour-blind safe).
 */
export interface Lane {
    key: string;
    /** Solid identity swatch / dot. */
    dot: string;
    /** Gradient avatar fill + its text colour. */
    avatar: string;
    /** Accent text colour for figures on a light surface. */
    text: string;
    /** Ring tint for the lane's avatar. */
    ring: string;
}

const LANES: readonly Lane[] = [
    {
        key: 'pitch',
        dot: 'bg-pitch',
        avatar: 'bg-brand-gradient text-white',
        text: 'text-pitch-deep dark:text-primary',
        ring: 'ring-pitch/40',
    },
    {
        key: 'teal',
        dot: 'bg-[#1d8aa6]',
        avatar: 'bg-teal-gradient text-white',
        text: 'text-[#1d8aa6] dark:text-[#5cc9dd]',
        ring: 'ring-[#2bb3c9]/40',
    },
    {
        key: 'gold',
        dot: 'bg-amber',
        avatar: 'bg-gold-gradient text-[#3a2600]',
        text: 'text-[#8a5a00] dark:text-amber-300',
        ring: 'ring-amber/40',
    },
    {
        key: 'violet',
        dot: 'bg-[#6a5fd6]',
        avatar: 'bg-violet-gradient text-white',
        text: 'text-[#6a5fd6] dark:text-[#a99cff]',
        ring: 'ring-[#7c6cf0]/40',
    },
];

/** The lane styling for the player at a given 0-based position (viewer first). */
export function lane(index: number): Lane {
    return LANES[index % LANES.length];
}

/** The most opponents a viewer can compare against at once (4 lanes including themselves). */
export const COMPARE_LIMIT = 3;

/**
 * Whether a player's prediction for a fixture in the given window is currently shown. The viewer's
 * own lane always is; an opponent's only once that prediction window has locked. Mirrors the
 * server-side gate, used purely to pick the right empty state (a lock chip vs a "no pick" dash).
 */
export function isRevealed(
    player: ComparePlayer,
    windowStatus: PredictionWindowStatus | undefined,
): boolean {
    return player.is_viewer || windowStatus === 'locked';
}

/** A player's short lane label — "You" for the viewer, otherwise their initials. */
export function laneLabel(player: ComparePlayer): string {
    return player.is_viewer ? 'You' : player.initials;
}
