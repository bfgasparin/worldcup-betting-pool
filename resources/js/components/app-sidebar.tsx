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
import poolsRoutes, { index as pools } from '@/routes/pools';
import type { JoinedPool, TournamentNavInfo } from '@/types/navigation';

/** A row in the "Your pools" list — a joined pool, or the pool currently in context. */
type PoolRow = Pick<
    JoinedPool,
    'slug' | 'name' | 'source' | 'needs_attention'
> & {
    accent?: string | null;
};

export function AppSidebar() {
    const { props } = usePage<{
        pool?: TournamentNavInfo;
        joinedPools?: JoinedPool[];
    }>();
    const activePool = props.pool;
    const joined = props.joinedPools ?? [];

    // The pool in context that the player hasn't joined still gets its row + sub-nav, so the
    // sidebar never dead-ends on a pool you're only previewing. Prepend it when it isn't listed.
    const previewing =
        activePool && !joined.some((pool) => pool.slug === activePool.slug)
            ? activePool
            : null;

    const rows: PoolRow[] = [
        ...(previewing ? [{ ...previewing, needs_attention: false }] : []),
        ...joined,
    ];

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={pools()} prefetch>
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
                                isActive={!activePool}
                                tooltip={{ children: 'All pools' }}
                            >
                                <Link href={pools()} prefetch>
                                    <LayoutGrid />
                                    <span>All pools</span>
                                </Link>
                            </SidebarMenuButton>
                        </SidebarMenuItem>
                    </SidebarMenu>
                </SidebarGroup>

                {rows.length > 0 && (
                    <SidebarGroup className="px-2 py-0">
                        <SidebarGroupLabel>Your pools</SidebarGroupLabel>
                        <SidebarMenu>
                            {rows.map((row) => (
                                <PoolNavItem
                                    key={row.slug}
                                    row={row}
                                    active={row.slug === activePool?.slug}
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
 * One pool in the "Your pools" list: a monogram badge in the pool's kit accent, the pool name, a
 * gold "needs attention" dot when there's prediction work to do, and — for the pool in context —
 * an expanded sub-nav (Overview / Predict / Leaderboards).
 */
function PoolNavItem({ row, active }: { row: PoolRow; active: boolean }) {
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
                <Link href={poolsRoutes.show(row.slug).url} prefetch>
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
