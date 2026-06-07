import { Link, usePage } from '@inertiajs/react';
import { tournamentNavItems } from '@/components/nav-tournament';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { useIsMobile } from '@/hooks/use-mobile';
import { cn } from '@/lib/utils';
import type { JoinedPool, TournamentNavInfo } from '@/types/navigation';

/**
 * Fixed bottom navigation for a pool's three primary screens (Overview / Predict / Leaderboards),
 * shown only on mobile. The hamburger drawer still owns switching pools and settings; this keeps a
 * pool's main screens one thumb-tap away instead of two. It renders nothing on desktop (the left
 * sidebar covers that) or outside a pool (no `pool` prop). A gold dot marks Predict when the pool
 * has unfinished picks, mirroring the sidebar's "needs attention" cue.
 */
export function PoolTabBar() {
    const { props } = usePage<{
        pool?: TournamentNavInfo;
        joinedPools?: JoinedPool[];
    }>();
    const { isCurrentUrl } = useCurrentUrl();
    const isMobile = useIsMobile();

    const pool = props.pool;

    if (!pool || !isMobile) {
        return null;
    }

    const items = tournamentNavItems(pool.slug);
    const needsAttention = (props.joinedPools ?? []).some(
        (joined) => joined.slug === pool.slug && joined.needs_attention,
    );

    return (
        <nav
            aria-label="Pool sections"
            className="fixed inset-x-0 bottom-0 z-50 grid grid-cols-3 border-t border-border bg-background/95 backdrop-blur md:hidden"
            style={{ paddingBottom: 'env(safe-area-inset-bottom, 0px)' }}
        >
            {items.map((item) => {
                const active = isCurrentUrl(item.href);
                const Icon = item.icon;

                return (
                    <Link
                        key={item.title}
                        href={item.href}
                        prefetch
                        aria-current={active ? 'page' : undefined}
                        className={cn(
                            'flex h-14 flex-col items-center justify-center gap-1 text-[11px] font-semibold transition-colors',
                            active ? 'text-primary' : 'text-muted-foreground',
                        )}
                    >
                        <span className="relative">
                            <Icon className="size-5" />
                            {item.predict && needsAttention && (
                                <span
                                    className="bg-gold-gradient absolute -top-0.5 -right-1.5 size-2 rounded-full shadow-[var(--sh-sm)]"
                                    aria-hidden
                                />
                            )}
                        </span>
                        {item.title}
                    </Link>
                );
            })}
        </nav>
    );
}
