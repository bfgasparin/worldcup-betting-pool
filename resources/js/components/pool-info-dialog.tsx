import {
    CalendarClock,
    Coins,
    Info,
    ListChecks,
    Medal,
    Scale,
    Trophy,
} from 'lucide-react';
import { formatLongDate } from '@/components/fixtures';
import { Button } from '@/components/ui/button';
import { Chip } from '@/components/ui/chip';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { useDisplayTimeZone } from '@/hooks/use-timezone';
import { useTranslation } from '@/hooks/use-translation';
import type { Translator } from '@/hooks/use-translation';
import { tiebreakRule } from '@/lib/leaderboards';
import type { ScoringRule } from '@/lib/scoring';
import { scoringRules } from '@/lib/scoring';
import type { PoolDetail } from '@/types/pools';

/** When predictions lock for this pool, phrased for the viewer's timezone. */
function lockLine(
    pool: PoolDetail,
    tz: string,
    t: Translator['t'],
): string | null {
    if (!pool.predictions_lock_at) {
        return null;
    }

    const isOpen = new Date(pool.predictions_lock_at).getTime() > Date.now();

    return isOpen
        ? t('Predictions lock on :date.', {
              date: formatLongDate(pool.predictions_lock_at, tz),
          })
        : t('Predictions are locked — your bracket is set.');
}

function Section({
    icon: Icon,
    title,
    children,
}: {
    icon: typeof Info;
    title: string;
    children: React.ReactNode;
}) {
    return (
        <section className="flex flex-col gap-2">
            <h3 className="flex items-center gap-2 font-display text-sm font-semibold tracking-tight">
                <Icon className="size-4 text-primary" />
                {title}
            </h3>
            {children}
        </section>
    );
}

function PointsRow({ label, rules }: { label: string; rules: ScoringRule[] }) {
    const { t } = useTranslation();

    if (rules.length === 0) {
        return null;
    }

    return (
        <div className="flex flex-col gap-1.5">
            <span className="text-[11px] font-bold tracking-[0.08em] text-muted-foreground uppercase">
                {label}
            </span>
            <div className="flex flex-wrap gap-1.5">
                {rules.map((rule) => (
                    <span
                        key={rule.label}
                        className="inline-flex items-center gap-1.5 rounded-full bg-secondary px-2.5 py-1 text-xs font-semibold text-secondary-foreground"
                    >
                        {t(rule.label)}
                        <b className="font-display text-primary">
                            +{rule.points}
                        </b>
                    </span>
                ))}
            </div>
        </div>
    );
}

/**
 * The trigger button that opens the {@link PoolBriefingDialog}. It carries no state of its own —
 * the dialog is mounted once per page (see `pools/show.tsx`) and shared, so this button can be
 * placed in several responsive headers (desktop hero, mobile header) without stacking dialogs.
 */
export function PoolInfoButton({ onClick }: { onClick: () => void }) {
    const { t } = useTranslation();

    return (
        <Button
            variant="outline"
            size="sm"
            onClick={onClick}
            className="gap-1.5"
        >
            <Info className="size-4" />
            <span className="hidden sm:inline">{t('How it works')}</span>
        </Button>
    );
}

/**
 * The "How this pool works" dialog: how & when to predict, the scoring strategy, and how
 * points are earned. It is a controlled dialog — the owning page holds the open state, auto-opens
 * it once per pool the first time this user opens it, and records "seen" server-side per user
 * (see `pools.briefing.seen`) so the briefing follows the user across devices. Mount it exactly
 * once per page so it never stacks (see `pools/show.tsx`).
 */
export function PoolBriefingDialog({
    pool,
    open,
    onOpenChange,
}: {
    pool: PoolDetail;
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    const { t } = useTranslation();
    const tz = useDisplayTimeZone();

    const lock = lockLine(pool, tz, t);
    const groupRules = scoringRules(pool.scoring_config, 'group');
    const knockoutRules = scoringRules(pool.scoring_config, 'knockout');
    const isPaid = pool.pricing.entry_price > 0;

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-h-[85vh] overflow-y-auto">
                <DialogHeader>
                    <DialogTitle className="font-display text-xl">
                        {t('How this pool works')}
                    </DialogTitle>
                    <DialogDescription>
                        {pool.how_to_play.summary}
                    </DialogDescription>
                </DialogHeader>

                <div className="flex flex-col gap-6 py-2">
                    <Section
                        icon={ListChecks}
                        title={t('How & when to predict')}
                    >
                        <ol className="flex flex-col gap-2 text-sm text-muted-foreground">
                            {pool.how_to_play.steps.map((step, index) => (
                                <li key={index} className="flex gap-2.5">
                                    <span className="mt-0.5 inline-flex size-5 shrink-0 items-center justify-center rounded-full bg-secondary font-display text-[11px] font-bold text-secondary-foreground">
                                        {index + 1}
                                    </span>
                                    <span>{step}</span>
                                </li>
                            ))}
                        </ol>
                        {lock && (
                            <p className="mt-1 inline-flex items-center gap-1.5 text-sm font-medium text-foreground">
                                <CalendarClock className="size-4 text-primary" />
                                {lock}
                            </p>
                        )}
                    </Section>

                    <Section icon={Trophy} title={t('Scoring strategy')}>
                        <Chip
                            variant="points"
                            className="w-fit px-3 py-1 text-xs"
                        >
                            {pool.scoring_label}
                        </Chip>
                        <p className="text-sm text-muted-foreground">
                            {pool.scoring_description}
                        </p>
                    </Section>

                    <Section icon={Coins} title={t('How points are earned')}>
                        <div className="flex flex-col gap-3">
                            <PointsRow
                                label={t('Group stage')}
                                rules={groupRules}
                            />
                            <PointsRow
                                label={t('Knockouts')}
                                rules={knockoutRules}
                            />
                            <p className="text-sm text-muted-foreground">
                                {t(
                                    'Knockout matches are judged on the final score, extra time included — penalties only decide who goes through.',
                                )}
                            </p>
                        </div>
                    </Section>

                    {pool.leaderboards.length > 0 && (
                        <Section icon={Medal} title={t('Leaderboards')}>
                            <p className="text-sm text-muted-foreground">
                                {t(
                                    'Compete on every board at once — your position updates as results land.',
                                )}
                                {isPaid && (
                                    <>
                                        {' '}
                                        {t('Only the')}{' '}
                                        <span className="font-semibold text-foreground">
                                            {t('Overall')}
                                        </span>{' '}
                                        {t(
                                            'board pays out the prize pot; the rest are for bragging rights.',
                                        )}
                                    </>
                                )}
                            </p>
                            <div className="mt-1 flex flex-col gap-2.5">
                                {pool.leaderboards.map((board) => (
                                    <div
                                        key={board.key}
                                        className="flex flex-col gap-0.5"
                                    >
                                        <span className="flex items-center gap-2 font-display text-sm font-semibold">
                                            {board.label}
                                            {isPaid && board.awards_prizes && (
                                                <span className="bg-gold-gradient inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[10px] font-bold tracking-wide text-[#3a2600] uppercase">
                                                    <Trophy className="size-2.5" />
                                                    {t('Prize board')}
                                                </span>
                                            )}
                                        </span>
                                        <span className="text-sm font-medium text-foreground">
                                            {board.description}
                                        </span>
                                        <span className="text-sm leading-relaxed text-muted-foreground">
                                            {board.how_it_scores}
                                        </span>
                                        <span className="mt-0.5 inline-flex items-start gap-1 text-xs text-muted-foreground/80">
                                            <Scale className="mt-px size-3 shrink-0 text-primary/70" />
                                            {tiebreakRule(board, t)}
                                        </span>
                                    </div>
                                ))}
                            </div>
                        </Section>
                    )}
                </div>
            </DialogContent>
        </Dialog>
    );
}
