import { Link } from '@inertiajs/react';
import {
    ChevronLeft,
    LayoutDashboard,
    ListOrdered,
    PencilLine,
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
}

export function NavTournament({ slug, name }: { slug: string; name: string }) {
    const items: NavItem[] = [
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
            title: 'Pool table',
            href: games.leaderboard(slug).url,
            icon: ListOrdered,
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
                            <Link href={item.href} prefetch>
                                <item.icon />
                                <span>{item.title}</span>
                            </Link>
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
