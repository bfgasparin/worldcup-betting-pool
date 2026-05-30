import { Link, usePage } from '@inertiajs/react';
import { Moon, Sun } from 'lucide-react';
import type { ReactNode } from 'react';
import AppLogo from '@/components/app-logo';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { UserInfo } from '@/components/user-info';
import { UserMenuContent } from '@/components/user-menu-content';
import { useAppearance } from '@/hooks/use-appearance';
import { index as games } from '@/routes/games';

function AppearanceToggle() {
    const { resolvedAppearance, updateAppearance } = useAppearance();
    const isDark = resolvedAppearance === 'dark';

    return (
        <Button
            variant="ghost"
            size="icon"
            onClick={() => updateAppearance(isDark ? 'light' : 'dark')}
            aria-label="Toggle theme"
        >
            {isDark ? <Sun className="size-5" /> : <Moon className="size-5" />}
        </Button>
    );
}

export default function HubLayout({ children }: { children: ReactNode }) {
    const { auth } = usePage().props;

    return (
        <div className="relative flex min-h-screen flex-col bg-background text-foreground">
            <header className="sticky top-0 z-20 border-b border-border/60 bg-background/80 backdrop-blur">
                <div className="mx-auto flex w-full max-w-6xl items-center justify-between gap-4 px-6 py-3">
                    <Link href={games()} prefetch className="flex items-center">
                        <AppLogo />
                    </Link>
                    <div className="flex items-center gap-1">
                        <AppearanceToggle />
                        {auth.user && (
                            <DropdownMenu>
                                <DropdownMenuTrigger asChild>
                                    <Button
                                        variant="ghost"
                                        className="h-auto gap-2 px-2 py-1.5"
                                    >
                                        <UserInfo user={auth.user} />
                                    </Button>
                                </DropdownMenuTrigger>
                                <DropdownMenuContent
                                    align="end"
                                    className="min-w-56 rounded-lg"
                                >
                                    <UserMenuContent user={auth.user} />
                                </DropdownMenuContent>
                            </DropdownMenu>
                        )}
                    </div>
                </div>
            </header>
            <main className="relative flex-1">{children}</main>
        </div>
    );
}
