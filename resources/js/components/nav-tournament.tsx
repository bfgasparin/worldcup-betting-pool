import { Link } from '@inertiajs/react';
import { ChevronLeft, Network, Users } from 'lucide-react';
import {
    SidebarGroup,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { index as games } from '@/routes/games';

const sections = [
    { title: 'Groups', href: '#groups', icon: Users },
    { title: 'Bracket', href: '#bracket', icon: Network },
];

export function NavTournament({ name }: { name: string }) {
    return (
        <SidebarGroup className="px-2 py-0">
            <SidebarGroupLabel className="truncate">{name}</SidebarGroupLabel>
            <SidebarMenu>
                {sections.map((section) => (
                    <SidebarMenuItem key={section.title}>
                        <SidebarMenuButton
                            asChild
                            tooltip={{ children: section.title }}
                        >
                            <a href={section.href}>
                                <section.icon />
                                <span>{section.title}</span>
                            </a>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                ))}
                <SidebarMenuItem>
                    <SidebarMenuButton
                        asChild
                        tooltip={{ children: 'All tournaments' }}
                    >
                        <Link href={games()} prefetch>
                            <ChevronLeft />
                            <span>All tournaments</span>
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarGroup>
    );
}
