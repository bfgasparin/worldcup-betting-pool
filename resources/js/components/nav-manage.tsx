import { CalendarClock, ClipboardCheck, Radio } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import manage from '@/routes/manage';

export type ManageNavItem = {
    title: string;
    href: string;
    icon: LucideIcon;
};

/**
 * The per-tournament admin nav: the three management screens for one tournament. Shared by the
 * sidebar (a sub-nav beneath the "Manage" item on desktop) and the mobile bottom tab bar, so an
 * admin can move between Live Control, Score review and the Schedule without returning to the hub.
 */
export function manageNavItems(slug: string): ManageNavItem[] {
    return [
        {
            title: 'Live',
            href: manage.live.index(slug).url,
            icon: Radio,
        },
        {
            title: 'Scores',
            href: manage.scores.review(slug).url,
            icon: ClipboardCheck,
        },
        {
            title: 'Schedule',
            href: manage.schedule.index(slug).url,
            icon: CalendarClock,
        },
    ];
}

/** The tournament slug of the manage section the URL is in, or null on the hub / off /manage. */
export function manageSlugFromUrl(url: string): string | null {
    return (
        url.match(/^\/manage\/([^/?#]+)\/(?:live|scores|schedule)/)?.[1] ?? null
    );
}
