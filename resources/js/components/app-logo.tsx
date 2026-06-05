import AppLogoIcon from '@/components/app-logo-icon';

export default function AppLogo() {
    return (
        <>
            <div className="app-icon size-9 shrink-0 rounded-xl shadow-[var(--sh-sm)]">
                <AppLogoIcon className="size-5 text-white" />
            </div>
            <div className="ml-2 grid flex-1 text-left leading-none">
                <span className="font-display text-[15px] font-semibold tracking-tight">
                    <span className="text-grad">Brothers</span>
                </span>
                <span className="mt-0.5 text-[9px] font-bold tracking-[0.22em] text-amber uppercase">
                    Bets
                </span>
            </div>
        </>
    );
}
