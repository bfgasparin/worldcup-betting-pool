import { Check, Download, Share } from 'lucide-react';
import { useState } from 'react';
import Heading from '@/components/heading';
import { IosInstallInstructions } from '@/components/ios-install-instructions';
import { Button } from '@/components/ui/button';
import { useInstallPrompt } from '@/hooks/use-install-prompt';
import { useTranslation } from '@/hooks/use-translation';

/**
 * Shows the right install action for the current device. Unlike the floating banner, this entry
 * is always available (it ignores the dismissal cooldown), so a user who waved the banner away can
 * still install from Settings later.
 */
function InstallAction() {
    const { t } = useTranslation();
    const { canInstall, isStandalone, isInstalled, isIOS, promptInstall } =
        useInstallPrompt();
    const [iosOpen, setIosOpen] = useState(false);

    if (isStandalone || isInstalled) {
        return (
            <p className="inline-flex items-center gap-2 text-sm font-medium text-primary">
                <Check className="size-4" />
                {t('Installed')}
            </p>
        );
    }

    if (canInstall) {
        return (
            <Button onClick={() => promptInstall()} className="w-fit">
                <Download className="size-4" />
                {t('Install app')}
            </Button>
        );
    }

    if (isIOS) {
        return (
            <>
                <Button onClick={() => setIosOpen(true)} className="w-fit">
                    <Share className="size-4" />
                    {t('Add to Home Screen')}
                </Button>
                <IosInstallInstructions
                    open={iosOpen}
                    onOpenChange={setIosOpen}
                />
            </>
        );
    }

    return (
        <p className="text-sm text-muted-foreground">
            {t("Your browser doesn't support installing this app.")}
        </p>
    );
}

/** The "Install app" block for the Settings → Appearance page. */
export function InstallAppSection() {
    const { t } = useTranslation();

    return (
        <section className="space-y-3">
            <Heading
                variant="small"
                title={t('Install app')}
                description={t(
                    'Add Brothers Bets to your home screen for quick access.',
                )}
            />
            <InstallAction />
        </section>
    );
}
