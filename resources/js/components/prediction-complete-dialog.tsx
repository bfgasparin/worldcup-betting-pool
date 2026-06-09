import { Link } from '@inertiajs/react';
import { CalendarClock, PartyPopper } from 'lucide-react';
import { formatMatchDate, formatMatchTime } from '@/components/fixtures';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { useDisplayTimeZone } from '@/hooks/use-timezone';
import { useTranslation } from '@/hooks/use-translation';
import pools from '@/routes/pools';
import type { CompletionWindow } from '@/types/pools';

/**
 * The celebration shown the instant a player fills the last prediction in an open window: their work
 * is done and there's nothing left but to wait for the official scores. Controlled by the wizard,
 * which opens it on the incomplete→complete transition (not on every visit — see {@link AllSetBanner}
 * for the calm persistent state). Lists each finished window's lock time so it's clear edits are
 * still possible until then.
 */
export function PredictionCompleteDialog({
    open,
    onOpenChange,
    poolSlug,
    windows,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    poolSlug: string;
    windows: CompletionWindow[];
}) {
    const { t } = useTranslation();
    const tz = useDisplayTimeZone();

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="text-center sm:text-center">
                <DialogHeader className="items-center text-center sm:text-center">
                    <span className="bg-gold-gradient mb-1 flex size-14 items-center justify-center rounded-full text-[#3a2600] shadow-[var(--sh-md)]">
                        <PartyPopper className="size-7" />
                    </span>
                    <DialogTitle className="font-display text-2xl">
                        {t("You're all set!")}
                    </DialogTitle>
                    <DialogDescription>
                        {t(
                            "Every prediction is in. There's nothing left to do but sit back and wait for the matches to play out — we'll score your picks as the official results land.",
                        )}
                    </DialogDescription>
                </DialogHeader>

                {windows.some((window) => window.deadline) && (
                    <ul className="flex flex-col gap-1.5 rounded-2xl bg-muted/50 px-4 py-3 text-sm">
                        {windows
                            .filter((window) => window.deadline)
                            .map((window) => (
                                <li
                                    key={window.phase_key}
                                    className="flex items-center justify-center gap-1.5 text-muted-foreground"
                                >
                                    <CalendarClock className="size-4 shrink-0 text-primary" />
                                    <span>
                                        <span className="font-medium text-foreground">
                                            {t(window.label)}
                                        </span>{' '}
                                        {t(
                                            'locks :date, :time — you can still tweak until then.',
                                            {
                                                date: formatMatchDate(
                                                    window.deadline!,
                                                    tz,
                                                ),
                                                time: formatMatchTime(
                                                    window.deadline!,
                                                    tz,
                                                ),
                                            },
                                        )}
                                    </span>
                                </li>
                            ))}
                    </ul>
                )}

                <DialogFooter className="sm:justify-center">
                    <Button
                        variant="outline"
                        onClick={() => onOpenChange(false)}
                    >
                        {t('Done')}
                    </Button>
                    <Button variant="gold" asChild>
                        <Link href={pools.leaderboard(poolSlug)}>
                            {t('View leaderboard')}
                        </Link>
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
