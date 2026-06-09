import { Link, usePage } from '@inertiajs/react';
import { manageNavItems, manageSlugFromUrl } from '@/components/nav-manage';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { useIsMobile } from '@/hooks/use-mobile';
import { useTranslation } from '@/hooks/use-translation';
import { cn } from '@/lib/utils';

/**
 * Fixed bottom navigation for a tournament's three admin screens (Live / Scores / Schedule), shown
 * only on mobile when an admin is inside a `/manage/{slug}` section. It mirrors {@link PoolTabBar}:
 * the top-right menu still owns returning to the Manage hub to switch tournaments, while this keeps
 * the sections one thumb-tap away. Renders nothing on desktop or outside a manage-tournament page.
 */
export function ManageTabBar() {
    const { t } = useTranslation();
    const { url } = usePage();
    const { isCurrentUrl } = useCurrentUrl();
    const isMobile = useIsMobile();

    const slug = manageSlugFromUrl(url);

    if (!slug || !isMobile) {
        return null;
    }

    const items = manageNavItems(slug);

    return (
        <div
            className="pointer-events-none fixed inset-x-0 bottom-0 z-40 flex justify-center px-3 md:hidden"
            style={{
                paddingBottom:
                    'calc(0.75rem + env(safe-area-inset-bottom, 0px))',
            }}
        >
            <nav
                aria-label={t('Tournament admin sections')}
                className="pointer-events-auto inline-flex items-stretch gap-1 rounded-2xl border border-border bg-card/95 p-1.5 shadow-[var(--sh-lg)] backdrop-blur"
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
                                'flex w-[4.75rem] flex-col items-center justify-center gap-0.5 rounded-xl py-1.5 text-[11px] font-semibold transition-colors',
                                active
                                    ? 'bg-primary/10 text-primary'
                                    : 'text-muted-foreground',
                            )}
                        >
                            <Icon className="size-5" />
                            {t(item.title)}
                        </Link>
                    );
                })}
            </nav>
        </div>
    );
}
