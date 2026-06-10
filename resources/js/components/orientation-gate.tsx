import { Smartphone } from 'lucide-react';
import { useInstallPrompt } from '@/hooks/use-install-prompt';
import { useMediaQuery } from '@/hooks/use-media-query';
import { useTranslation } from '@/hooks/use-translation';

/**
 * Forces the installed PWA to behave as portrait-only.
 *
 * Android already hard-locks installed PWAs to portrait via the manifest's `orientation`
 * field, but iOS ignores it and has no `screen.orientation.lock()`. So when the app runs
 * standalone on a phone held in landscape, we cover the screen with a "rotate to portrait"
 * prompt — the standard portrait-only-app pattern. Rotating back dismisses it automatically.
 *
 * Scoped to phones via `max-height: 600px` (phone landscape height ≈ 360–480; tablets ≈ 744+),
 * so a desktop-installed PWA and an iPad in landscape stay usable. On Android the manifest
 * already prevents landscape, so this never appears there — it's a harmless safety net.
 */
export function OrientationGate() {
    const { isStandalone } = useInstallPrompt();
    const isLandscapePhone = useMediaQuery(
        '(orientation: landscape) and (max-height: 600px)',
    );
    const { t } = useTranslation();

    if (!isStandalone || !isLandscapePhone) {
        return null;
    }

    return (
        <div
            role="alertdialog"
            aria-modal="true"
            className="bg-brand-gradient fixed inset-0 z-[100] flex flex-col items-center justify-center gap-4 p-8 text-center text-white"
        >
            <Smartphone className="size-12 opacity-90" aria-hidden />
            <p className="font-display text-xl font-semibold">
                {t('Rotate your device to portrait')}
            </p>
            <p className="max-w-xs text-sm text-white/80">
                {t('Brothers Bets works best held upright.')}
            </p>
        </div>
    );
}
