import { LayoutDashboard, ListOrdered, PencilLine } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import games from '@/routes/games';

export type TournamentNavItem = {
    title: string;
    href: string;
    icon: LucideIcon;
};

/**
 * The per-game nav: the three screens a game in context exposes. Shared by the sidebar, which
 * expands these beneath the active game so every joined game can reach Overview, Predict and
 * Leaderboards without leaving the persistent "Your games" list.
 */
export function tournamentNavItems(slug: string): TournamentNavItem[] {
    return [
        {
            title: 'Overview',
            href: games.show(slug).url,
            icon: LayoutDashboard,
        },
        {
            title: 'Predict',
            href: games.predict.edit(slug).url,
            icon: PencilLine,
        },
        {
            title: 'Leaderboards',
            href: games.leaderboard(slug).url,
            icon: ListOrdered,
        },
    ];
}
