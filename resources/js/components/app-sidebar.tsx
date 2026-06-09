import { Link, usePage } from '@inertiajs/react';
import { LayoutGrid, Radio, Wrench } from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { LivePulse } from '@/components/live-badge';
import { manageNavItems, manageSlugFromUrl } from '@/components/nav-manage';
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
import { useTranslation } from '@/hooks/use-translation';
import { resolveAccent, sourceMonogram } from '@/lib/accents';
import { cn } from '@/lib/utils';
import live from '@/routes/live';
import manage from '@/routes/manage';
import poolsRoutes, { index as pools } from '@/routes/pools';
import type { Auth } from '@/types/auth';
import type { JoinedPool, TournamentNavInfo } from '@/types/navigation';

/** A row in the "Your pools" list — a joined pool, or the pool currently in context. */
type PoolRow = Pick<
    JoinedPool,
    'slug' | 'name' | 'source' | 'needs_attention'
> & {
    accent?: string | null;
};

export function AppSidebar() {
    const page = usePage<{
        pool?: TournamentNavInfo;
        joinedPools?: JoinedPool[];
        hasLiveMatches?: boolean;
        auth: Auth;
    }>();
    const { props } = page;
    const activePool = props.pool;
    const joined = props.joinedPools ?? [];
    const onLive = page.url.startsWith('/live');
    const onManage = page.url.startsWith('/manage');
    const isAdmin = props.auth.isAdmin;
    const manageSlug = manageSlugFromUrl(page.url);
    const { isCurrentUrl } = useCurrentUrl();
    const { t } = useTranslation();

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
                                isActive={!activePool && !onLive}
                                tooltip={{ children: t('All pools') }}
                            >
                                <Link href={pools()} prefetch>
                                    <LayoutGrid />
                                    <span>{t('All pools')}</span>
                                </Link>
                            </SidebarMenuButton>
                        </SidebarMenuItem>
                        <SidebarMenuItem>
                            <SidebarMenuButton
                                asChild
                                isActive={onLive}
                                tooltip={{ children: t('Live') }}
                            >
                                <Link href={live.index()}>
                                    <Radio />
                                    <span>{t('Live')}</span>
                                </Link>
                            </SidebarMenuButton>
                            {props.hasLiveMatches && (
                                <SidebarMenuBadge>
                                    <LivePulse />
                                    <span className="sr-only">
                                        {t('Matches are live')}
                                    </span>
                                </SidebarMenuBadge>
                            )}
                        </SidebarMenuItem>
                        {isAdmin && (
                            <SidebarMenuItem>
                                <SidebarMenuButton
                                    asChild
                                    isActive={onManage}
                                    tooltip={{ children: t('Manage') }}
                                >
                                    <Link href={manage.index()}>
                                        <Wrench />
                                        <span>{t('Manage')}</span>
                                    </Link>
                                </SidebarMenuButton>

                                {manageSlug && (
                                    <SidebarMenuSub>
                                        {manageNavItems(manageSlug).map(
                                            (item) => (
                                                <SidebarMenuSubItem
                                                    key={item.title}
                                                >
                                                    <SidebarMenuSubButton
                                                        asChild
                                                        isActive={isCurrentUrl(
                                                            item.href,
                                                        )}
                                                    >
                                                        <Link
                                                            href={item.href}
                                                            prefetch
                                                        >
                                                            <item.icon />
                                                            <span>
                                                                {t(item.title)}
                                                            </span>
                                                        </Link>
                                                    </SidebarMenuSubButton>
                                                </SidebarMenuSubItem>
                                            ),
                                        )}
                                    </SidebarMenuSub>
                                )}
                            </SidebarMenuItem>
                        )}
                    </SidebarMenu>
                </SidebarGroup>

                {rows.length > 0 && (
                    <SidebarGroup className="px-2 py-0">
                        <SidebarGroupLabel>{t('Your pools')}</SidebarGroupLabel>
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
    const { t } = useTranslation();
    const items = tournamentNavItems(row.slug);

    return (
        <SidebarMenuItem>
            <SidebarMenuButton
                asChild
                isActive={active}
                tooltip={{ children: t(row.name) }}
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
                    <span>{t(row.name)}</span>
                </Link>
            </SidebarMenuButton>

            {row.needs_attention && (
                <SidebarMenuBadge>
                    <span
                        className="bg-gold-gradient size-2 rounded-full shadow-[var(--sh-sm)]"
                        aria-hidden
                    />
                    <span className="sr-only">{t('Needs your attention')}</span>
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
                                    <span>{t(item.title)}</span>
                                </Link>
                            </SidebarMenuSubButton>

                            {/* The same gold dot the pool name carries, pinned to its source: the
                                prediction work all lives behind the Predict screen. */}
                            {row.needs_attention && item.predict && (
                                <SidebarMenuBadge className="top-1">
                                    <span
                                        className="bg-gold-gradient size-2 rounded-full shadow-[var(--sh-sm)]"
                                        aria-hidden
                                    />
                                    <span className="sr-only">
                                        {t('Predictions need your attention')}
                                    </span>
                                </SidebarMenuBadge>
                            )}
                        </SidebarMenuSubItem>
                    ))}
                </SidebarMenuSub>
            )}
        </SidebarMenuItem>
    );
}
