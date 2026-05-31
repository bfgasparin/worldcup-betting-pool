import { Link } from '@inertiajs/react';
import {
    ChevronLeft,
    LayoutDashboard,
    ListOrdered,
    Network,
    PencilLine,
    Users,
} from 'lucide-react';
import type { ComponentType } from 'react';
import {
    SidebarGroup,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import games from '@/routes/games';

interface NavItem {
    title: string;
    href: string;
    icon: ComponentType<{ className?: string }>;
    /** Internal Inertia route (prefetched) vs. an in-page anchor. */
    route?: boolean;
}

export function NavTournament({ slug, name }: { slug: string; name: string }) {
    const showUrl = games.show(slug).url;

    const items: NavItem[] = [
        {
            title: 'Overview',
            href: showUrl,
            icon: LayoutDashboard,
            route: true,
        },
        {
            title: 'Predict',
            href: games.predict.edit(slug).url,
            icon: PencilLine,
            route: true,
        },
        { title: 'Groups', href: `${showUrl}#groups`, icon: Users },
        { title: 'Bracket', href: `${showUrl}#bracket`, icon: Network },
        {
            title: 'Pool table',
            href: games.leaderboard(slug).url,
            icon: ListOrdered,
            route: true,
        },
    ];

    return (
        <SidebarGroup className="px-2 py-0">
            <SidebarGroupLabel className="truncate">{name}</SidebarGroupLabel>
            <SidebarMenu>
                {items.map((item) => (
                    <SidebarMenuItem key={item.title}>
                        <SidebarMenuButton
                            asChild
                            tooltip={{ children: item.title }}
                        >
                            {item.route ? (
                                <Link href={item.href} prefetch>
                                    <item.icon />
                                    <span>{item.title}</span>
                                </Link>
                            ) : (
                                <a href={item.href}>
                                    <item.icon />
                                    <span>{item.title}</span>
                                </a>
                            )}
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                ))}
                <SidebarMenuItem>
                    <SidebarMenuButton
                        asChild
                        tooltip={{ children: 'All tournaments' }}
                    >
                        <Link href={games.index()} prefetch>
                            <ChevronLeft />
                            <span>All tournaments</span>
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarGroup>
    );
}
