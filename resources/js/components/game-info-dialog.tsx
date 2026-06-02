import {
    CalendarClock,
    Coins,
    Info,
    ListChecks,
    Medal,
    Scale,
    Trophy,
} from 'lucide-react';
import { useState } from 'react';
import { formatLongDate } from '@/components/fixtures';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
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
import type { GameDetail } from '@/types/games';

const dismissKey = (slug: string): string =>
    `bbp:how-it-works-dismissed:${slug}`;

/** When predictions lock for this game, phrased for the viewer's timezone. */
function lockLine(game: GameDetail, tz: string): string | null {
    if (!game.predictions_lock_at) {
        return null;
    }

    const isOpen = new Date(game.predictions_lock_at).getTime() > Date.now();

    return isOpen
        ? `Predictions lock on ${formatLongDate(game.predictions_lock_at, tz)}.`
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
 * The "How this game works" dialog: how & when to predict, the scoring strategy, and how
 * points are earned. It renders its own header trigger button and auto-opens once per game
 * (dismissible, stored client-side) so newcomers always get the briefing on first visit.
 */
export function GameInfoDialog({ game }: { game: GameDetail }) {
    const tz = useDisplayTimeZone();
    // Auto-open on first visit. The lazy initializer is SSR-safe (no window on the server, so
    // the dialog starts closed and its portal — client-only — matches on hydration).
    const [open, setOpen] = useState(
        () =>
            typeof window !== 'undefined' &&
            localStorage.getItem(dismissKey(game.slug)) === null,
    );
    const [dontShowAgain, setDontShowAgain] = useState(false);

    const handleOpenChange = (next: boolean) => {
        if (!next && dontShowAgain) {
            localStorage.setItem(dismissKey(game.slug), '1');
        }

        setOpen(next);
    };

    const lock = lockLine(game, tz);
    const groupRules = scoringRules(game.scoring_config, 'group');
    const knockoutRules = scoringRules(game.scoring_config, 'knockout');

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

            <Dialog open={open} onOpenChange={handleOpenChange}>
                <DialogContent className="max-h-[85vh] overflow-y-auto">
                    <DialogHeader>
                        <DialogTitle className="font-display text-xl">
                            How this game works
                        </DialogTitle>
                        <DialogDescription>
                            {game.how_to_play.summary}
                        </DialogDescription>
                    </DialogHeader>

                    <div className="flex flex-col gap-6 py-2">
                        <Section
                            icon={ListChecks}
                            title="How & when to predict"
                        >
                            <ol className="flex flex-col gap-2 text-sm text-muted-foreground">
                                {game.how_to_play.steps.map((step, index) => (
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
                                {game.scoring_label}
                            </Chip>
                            <p className="text-sm text-muted-foreground">
                                {game.scoring_description}
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

                        {game.leaderboards.length > 0 && (
                            <Section icon={Medal} title="Leaderboards">
                                <p className="text-sm text-muted-foreground">
                                    Compete on every board at once — your
                                    position updates as results land.
                                </p>
                                <div className="mt-1 flex flex-col gap-2.5">
                                    {game.leaderboards.map((board) => (
                                        <div
                                            key={board.key}
                                            className="flex flex-col gap-0.5"
                                        >
                                            <span className="font-display text-sm font-semibold">
                                                {board.label}
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

                    <label className="flex cursor-pointer items-center gap-2 border-t border-border pt-4 text-sm text-muted-foreground select-none">
                        <Checkbox
                            checked={dontShowAgain}
                            onCheckedChange={(checked) =>
                                setDontShowAgain(checked === true)
                            }
                        />
                        Don&apos;t show this again
                    </label>
                </DialogContent>
            </Dialog>
        </>
    );
}
