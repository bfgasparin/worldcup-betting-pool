import { useHttp } from '@inertiajs/react';
import {
    CalendarClock,
    Coins,
    Info,
    ListChecks,
    Medal,
    Scale,
    Trophy,
} from 'lucide-react';
import { useEffect, useState } from 'react';
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
import { tiebreakRule } from '@/lib/leaderboards';
import type { ScoringRule } from '@/lib/scoring';
import { scoringRules } from '@/lib/scoring';
import pools from '@/routes/pools';
import type { PoolDetail } from '@/types/pools';

/** When predictions lock for this pool, phrased for the viewer's timezone. */
function lockLine(pool: PoolDetail, tz: string): string | null {
    if (!pool.predictions_lock_at) {
        return null;
    }

    const isOpen = new Date(pool.predictions_lock_at).getTime() > Date.now();

    return isOpen
        ? `Predictions lock on ${formatLongDate(pool.predictions_lock_at, tz)}.`
        : 'Predictions are locked — your bracket is set.';
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
                        {rule.label}
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
 * The "How this pool works" dialog: how & when to predict, the scoring strategy, and how
 * points are earned. It renders its own header trigger button and auto-opens once per pool, the
 * first time this user opens it, so newcomers always get the briefing. "Seen" is tracked
 * server-side per user (see {@link pools.briefing.seen}) so it follows the user across devices.
 */
export function PoolInfoDialog({ pool }: { pool: PoolDetail }) {
    const tz = useDisplayTimeZone();
    const { post } = useHttp();
    // Auto-open the first time this user opens the pool; the server tells us whether they've seen it.
    const [open, setOpen] = useState(!pool.has_seen_briefing);

    // Record the briefing as seen so it never auto-opens again for this user — even if they just
    // close it. Fire-and-forget standalone request (not a page visit); the unique index keeps it
    // idempotent, so React StrictMode's double-invoke in dev is harmless.
    useEffect(() => {
        if (!pool.has_seen_briefing) {
            post(pools.briefing.seen(pool.slug).url);
        }
        // `post` is stable; re-run only when switching pools.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [pool.slug]);

    const lock = lockLine(pool, tz);
    const groupRules = scoringRules(pool.scoring_config, 'group');
    const knockoutRules = scoringRules(pool.scoring_config, 'knockout');
    const isPaid = pool.pricing.entry_price > 0;

    return (
        <>
            <Button
                variant="outline"
                size="sm"
                onClick={() => setOpen(true)}
                className="gap-1.5"
            >
                <Info className="size-4" />
                How it works
            </Button>

            <Dialog open={open} onOpenChange={setOpen}>
                <DialogContent className="max-h-[85vh] overflow-y-auto">
                    <DialogHeader>
                        <DialogTitle className="font-display text-xl">
                            How this pool works
                        </DialogTitle>
                        <DialogDescription>
                            {pool.how_to_play.summary}
                        </DialogDescription>
                    </DialogHeader>

                    <div className="flex flex-col gap-6 py-2">
                        <Section
                            icon={ListChecks}
                            title="How & when to predict"
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

                        <Section icon={Trophy} title="Scoring strategy">
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

                        <Section icon={Coins} title="How points are earned">
                            <div className="flex flex-col gap-3">
                                <PointsRow
                                    label="Group stage"
                                    rules={groupRules}
                                />
                                <PointsRow
                                    label="Knockouts"
                                    rules={knockoutRules}
                                />
                            </div>
                        </Section>

                        {pool.leaderboards.length > 0 && (
                            <Section icon={Medal} title="Leaderboards">
                                <p className="text-sm text-muted-foreground">
                                    Compete on every board at once — your
                                    position updates as results land.
                                    {isPaid && (
                                        <>
                                            {' '}
                                            Only the{' '}
                                            <span className="font-semibold text-foreground">
                                                Overall
                                            </span>{' '}
                                            board pays out the prize pot; the
                                            rest are for bragging rights.
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
                                                {isPaid &&
                                                    board.awards_prizes && (
                                                        <span className="bg-gold-gradient inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[10px] font-bold tracking-wide text-[#3a2600] uppercase">
                                                            <Trophy className="size-2.5" />
                                                            Prize board
                                                        </span>
                                                    )}
                                            </span>
                                            <span className="text-sm text-muted-foreground">
                                                {board.description}
                                            </span>
                                            <span className="inline-flex items-start gap-1 text-xs text-muted-foreground/80">
                                                <Scale className="mt-px size-3 shrink-0 text-primary/70" />
                                                {tiebreakRule(board)}
                                            </span>
                                        </div>
                                    ))}
                                </div>
                            </Section>
                        )}
                    </div>
                </DialogContent>
            </Dialog>
        </>
    );
}
