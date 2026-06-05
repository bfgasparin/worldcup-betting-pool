import type { InertiaLinkProps } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';

export type BreadcrumbItem = {
    title: string;
    href: NonNullable<InertiaLinkProps['href']>;
};

export type NavItem = {
    title: string;
    href: NonNullable<InertiaLinkProps['href']>;
    icon?: LucideIcon | null;
    isActive?: boolean;
};

/**
 * The subset of the page-level `game` prop the sidebar reads to render tournament-context nav.
 * `source`/`accent` let the sidebar show which game is in context (sibling games share the name).
 */
export type TournamentNavInfo = {
    slug: string;
    name: string;
    status: string;
    source: string;
    accent?: string | null;
};

/**
 * A game the viewer has joined, as shared globally for the sidebar's "Your games" list.
 * `needs_attention` is true when the prediction window is open and the player's picks are
 * unfinished — surfaced as a gold dot so they can see at a glance where there's work to do.
 */
export type JoinedGame = {
    slug: string;
    name: string;
    source: string;
    accent?: string | null;
    needs_attention: boolean;
};
