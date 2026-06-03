import { Link, usePage } from '@inertiajs/react';
import { ChevronLeft } from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { NavTournament } from '@/components/nav-tournament';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarGroup,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { index as games } from '@/routes/games';
import type { TournamentNavInfo } from '@/types/navigation';

export function AppSidebar() {
    const game = usePage<{ game?: TournamentNavInfo }>().props.game;

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={games()} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                {game ? (
                    <NavTournament
                        slug={game.slug}
                        name={game.name}
                        source={game.source}
                        accent={game.accent}
                    />
                ) : (
                    <SidebarGroup className="px-2 py-0">
                        <SidebarMenu>
                            <SidebarMenuItem>
                                <SidebarMenuButton
                                    asChild
                                    tooltip={{ children: 'All games' }}
                                >
                                    <Link href={games()} prefetch>
                                        <ChevronLeft />
                                        <span>All games</span>
                                    </Link>
                                </SidebarMenuButton>
                            </SidebarMenuItem>
                        </SidebarMenu>
                    </SidebarGroup>
                )}
            </SidebarContent>

            <SidebarFooter>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
