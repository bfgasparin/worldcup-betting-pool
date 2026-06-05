import { LayoutDashboard, ListOrdered, PencilLine } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import pools from '@/routes/pools';

export type TournamentNavItem = {
    title: string;
    href: string;
    icon: LucideIcon;
    /** The screen that owns prediction work, so the sidebar can mark it when picks are unfinished. */
    predict?: boolean;
};

/**
 * The per-pool nav: the three screens a pool in context exposes. Shared by the sidebar, which
 * expands these beneath the active pool so every joined pool can reach Overview, Predict and
 * Leaderboards without leaving the persistent "Your pools" list.
 */
export function tournamentNavItems(slug: string): TournamentNavItem[] {
    return [
        {
            title: 'Overview',
            href: pools.show(slug).url,
            icon: LayoutDashboard,
        },
        {
            title: 'Predict',
            href: pools.predict.edit(slug).url,
            icon: PencilLine,
            predict: true,
        },
        {
            title: 'Leaderboards',
            href: pools.leaderboard(slug).url,
            icon: ListOrdered,
        },
    ];
}
