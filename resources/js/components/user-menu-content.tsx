import { Link, router, usePage } from '@inertiajs/react';
import { LogOut, Settings, Wrench } from 'lucide-react';
import {
    DropdownMenuGroup,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
} from '@/components/ui/dropdown-menu';
import { UserInfo } from '@/components/user-info';
import { useInstallPrompt } from '@/hooks/use-install-prompt';
import { useIsMobile } from '@/hooks/use-mobile';
import { useMobileNavigation } from '@/hooks/use-mobile-navigation';
import { useTranslation } from '@/hooks/use-translation';
import { logout } from '@/routes';
import manage from '@/routes/manage';
import { edit } from '@/routes/profile';
import type { User } from '@/types';

type Props = {
    user: User;
};

export function UserMenuContent({ user }: Props) {
    const cleanup = useMobileNavigation();
    const isAdmin = usePage().props.auth.isAdmin;
    const { isStandalone } = useInstallPrompt();
    // On touch, prefetch only starts at tap time — no head start, and it suppresses the
    // NavigationIndicator pill (prefetch-served visits never fire `start`). Keep it for desktop,
    // where hovering the menu item gives a genuine head start.
    const isMobile = useIsMobile();
    const { t } = useTranslation();

    const handleLogout = () => {
        cleanup();
        router.flushAll();
    };

    return (
        <>
            <DropdownMenuLabel className="p-0 font-normal">
                <div className="flex items-center gap-2 px-1 py-1.5 text-left text-sm">
                    <UserInfo user={user} showEmail={true} />
                </div>
            </DropdownMenuLabel>
            <DropdownMenuSeparator />
            <DropdownMenuGroup>
                {isAdmin && (
                    <DropdownMenuItem asChild>
                        <Link
                            className="block w-full cursor-pointer"
                            href={manage.index()}
                            onClick={cleanup}
                        >
                            <Wrench className="mr-2" />
                            {t('Manage')}
                        </Link>
                    </DropdownMenuItem>
                )}
                <DropdownMenuItem asChild>
                    <Link
                        className="block w-full cursor-pointer"
                        href={edit()}
                        prefetch={!isMobile}
                        onClick={cleanup}
                    >
                        <Settings className="mr-2" />
                        {t('Settings')}
                    </Link>
                </DropdownMenuItem>
            </DropdownMenuGroup>
            <DropdownMenuSeparator />
            <DropdownMenuItem asChild>
                <Link
                    className="block w-full cursor-pointer"
                    href={logout(
                        isStandalone ? { query: { pwa: 1 } } : undefined,
                    )}
                    as="button"
                    onClick={handleLogout}
                    data-test="logout-button"
                >
                    <LogOut className="mr-2" />
                    {t('Log out')}
                </Link>
            </DropdownMenuItem>
        </>
    );
}
