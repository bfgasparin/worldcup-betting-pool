import { Link, usePage } from '@inertiajs/react';
import { LayoutGrid } from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { tournamentNavItems } from '@/components/nav-tournament';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarGroup,
    SidebarGroupLabel,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuBadge,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarMenuSub,
    SidebarMenuSubButton,
    SidebarMenuSubItem,
} from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { resolveAccent, sourceMonogram } from '@/lib/accents';
import { cn } from '@/lib/utils';
import gamesRoutes, { index as games } from '@/routes/games';
import type { JoinedGame, TournamentNavInfo } from '@/types/navigation';

/** A row in the "Your games" list — a joined game, or the game currently in context. */
type GameRow = Pick<
    JoinedGame,
    'slug' | 'name' | 'source' | 'needs_attention'
> & {
    accent?: string | null;
};

export function AppSidebar() {
    const { props } = usePage<{
        game?: TournamentNavInfo;
        joinedGames?: JoinedGame[];
    }>();
    const activeGame = props.game;
    const joined = props.joinedGames ?? [];

    // The game in context that the player hasn't joined still gets its row + sub-nav, so the
    // sidebar never dead-ends on a game you're only previewing. Prepend it when it isn't listed.
    const previewing =
        activeGame && !joined.some((game) => game.slug === activeGame.slug)
            ? activeGame
            : null;

    const rows: GameRow[] = [
        ...(previewing ? [{ ...previewing, needs_attention: false }] : []),
        ...joined,
    ];

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
                <SidebarGroup className="px-2 py-0">
                    <SidebarMenu>
                        <SidebarMenuItem>
                            <SidebarMenuButton
                                asChild
                                isActive={!activeGame}
                                tooltip={{ children: 'All games' }}
                            >
                                <Link href={games()} prefetch>
                                    <LayoutGrid />
                                    <span>All games</span>
                                </Link>
                            </SidebarMenuButton>
                        </SidebarMenuItem>
                    </SidebarMenu>
                </SidebarGroup>

                {rows.length > 0 && (
                    <SidebarGroup className="px-2 py-0">
                        <SidebarGroupLabel>Your games</SidebarGroupLabel>
                        <SidebarMenu>
                            {rows.map((row) => (
                                <GameNavItem
                                    key={row.slug}
                                    row={row}
                                    active={row.slug === activeGame?.slug}
                                />
                            ))}
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

/**
 * One game in the "Your games" list: a monogram badge in the game's kit accent, the game name, a
 * gold "needs attention" dot when there's prediction work to do, and — for the game in context —
 * an expanded sub-nav (Overview / Predict / Leaderboards).
 */
function GameNavItem({ row, active }: { row: GameRow; active: boolean }) {
    const kit = resolveAccent(row.accent);
    const { isCurrentUrl } = useCurrentUrl();
    const items = tournamentNavItems(row.slug);

    return (
        <SidebarMenuItem>
            <SidebarMenuButton
                asChild
                isActive={active}
                tooltip={{ children: row.name }}
            >
                <Link href={gamesRoutes.show(row.slug).url} prefetch>
                    <span
                        className={cn(
                            'flex size-5 shrink-0 items-center justify-center rounded-md font-display text-[0.6rem] leading-none font-bold',
                            kit.railClass,
                            kit.textClass,
                        )}
                    >
                        {sourceMonogram(row.source)}
                    </span>
                    <span>{row.name}</span>
                </Link>
            </SidebarMenuButton>

            {row.needs_attention && (
                <SidebarMenuBadge>
                    <span
                        className="bg-gold-gradient size-2 rounded-full shadow-[var(--sh-sm)]"
                        aria-hidden
                    />
                    <span className="sr-only">Needs your attention</span>
                </SidebarMenuBadge>
            )}

            {active && (
                <SidebarMenuSub>
                    {items.map((item) => (
                        <SidebarMenuSubItem key={item.title}>
                            <SidebarMenuSubButton
                                asChild
                                isActive={isCurrentUrl(item.href)}
                            >
                                <Link href={item.href} prefetch>
                                    <item.icon />
                                    <span>{item.title}</span>
                                </Link>
                            </SidebarMenuSubButton>
                        </SidebarMenuSubItem>
                    ))}
                </SidebarMenuSub>
            )}
        </SidebarMenuItem>
    );
}
