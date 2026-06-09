import { Share, SquarePlus } from 'lucide-react';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { useTranslation } from '@/hooks/use-translation';

/**
 * iOS has no programmatic install prompt — Safari only offers a manual Share → "Add to Home
 * Screen" flow. This dialog walks the user through it. Reused by both the install banner and the
 * Settings "Install app" card. The shared {@link Dialog} renders as a bottom sheet on mobile.
 */
export function IosInstallInstructions({
    open,
    onOpenChange,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    const { t } = useTranslation();

    const steps: { icon: typeof Share | null; label: string }[] = [
        { icon: Share, label: t('Tap the Share button') },
        { icon: SquarePlus, label: t('Choose "Add to Home Screen"') },
        { icon: null, label: t('Tap "Add"') },
    ];

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-w-sm">
                <DialogHeader>
                    <DialogTitle className="font-display text-xl">
                        {t('Add to Home Screen')}
                    </DialogTitle>
                    <DialogDescription>
                        {t(
                            'Install Brothers Bets for a full-screen, app-like experience.',
                        )}
                    </DialogDescription>
                </DialogHeader>

                <ol className="flex flex-col gap-3 py-2 text-sm">
                    {steps.map((step, index) => (
                        <li key={index} className="flex items-center gap-3">
                            <span className="inline-flex size-6 shrink-0 items-center justify-center rounded-full bg-secondary font-display text-xs font-bold text-secondary-foreground">
                                {index + 1}
                            </span>
                            <span className="flex items-center gap-1.5 text-foreground">
                                {step.label}
                                {step.icon && (
                                    <step.icon className="size-4 text-primary" />
                                )}
                            </span>
                        </li>
                    ))}
                </ol>
            </DialogContent>
        </Dialog>
    );
}
