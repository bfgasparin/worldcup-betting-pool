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
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { resolveAccent, sourceMonogram } from '@/lib/accents';
import { cn } from '@/lib/utils';
import games from '@/routes/games';

interface NavItem {
    title: string;
    href: string;
    icon: ComponentType<{ className?: string }>;
}

export function NavTournament({
    slug,
    name,
    source,
    accent,
}: {
    slug: string;
    name: string;
    source: string;
    accent?: string | null;
}) {
    const kit = resolveAccent(accent);
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
            title: 'Leaderboards',
            href: games.leaderboard(slug).url,
            icon: ListOrdered,
        },
    ];

    return (
        <SidebarGroup className="px-2 py-0">
            <div className="flex items-center gap-2 px-2 pt-1 pb-2 group-data-[collapsible=icon]:hidden">
                <span
                    className={cn(
                        'flex size-7 shrink-0 items-center justify-center rounded-lg font-display text-xs leading-none font-bold',
                        kit.railClass,
                        kit.textClass,
                    )}
                >
                    {sourceMonogram(source)}
                </span>
                <div className="flex min-w-0 flex-col">
                    <span className="truncate text-sm font-semibold text-sidebar-foreground">
                        {name}
                    </span>
                    <span className="truncate text-[0.65rem] font-bold tracking-[0.12em] text-sidebar-foreground/60 uppercase">
                        {source}
                    </span>
                </div>
            </div>
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
                        tooltip={{ children: 'All games' }}
                    >
                        <Link href={games.index()} prefetch>
                            <ChevronLeft />
                            <span>All games</span>
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarGroup>
    );
}
