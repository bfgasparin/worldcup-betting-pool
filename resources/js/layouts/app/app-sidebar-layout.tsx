import { usePage } from '@inertiajs/react';
import { AppContent } from '@/components/app-content';
import { AppShell } from '@/components/app-shell';
import { AppSidebar } from '@/components/app-sidebar';
import { AppSidebarHeader } from '@/components/app-sidebar-header';
import { MobileTopNav } from '@/components/mobile-top-nav';
import { PoolTabBar } from '@/components/pool-tab-bar';
import { cn } from '@/lib/utils';
import type { AppLayoutProps } from '@/types';
import type { TournamentNavInfo } from '@/types/navigation';

export default function AppSidebarLayout({
    children,
    breadcrumbs = [],
}: AppLayoutProps) {
    // In-pool pages share the `pool` prop; only then do we mount the mobile bottom tab bar and
    // reserve the strip it occupies (`--pool-tab-bar-h` is 0 off-mobile, so desktop is untouched).
    const inPool = Boolean(usePage<{ pool?: TournamentNavInfo }>().props.pool);

    return (
        <AppShell variant="sidebar">
            <AppSidebar />
            <AppContent
                variant="sidebar"
                className={cn(
                    'has-floating-nav overflow-x-hidden pt-[var(--top-nav-h)]',
                    inPool && 'has-pool-tab-bar pb-[var(--pool-tab-bar-h)]',
                )}
            >
                <AppSidebarHeader breadcrumbs={breadcrumbs} />
                {children}
                <MobileTopNav />
                <PoolTabBar />
            </AppContent>
        </AppShell>
    );
}
