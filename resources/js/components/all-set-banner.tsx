import { CheckCircle2 } from 'lucide-react';
import { formatMatchDate, formatMatchTime } from '@/components/fixtures';
import { useDisplayTimeZone } from '@/hooks/use-timezone';
import type { CompletionWindow } from '@/types/pools';

/**
 * The calm, persistent counterpart to {@link PredictionCompleteDialog}: shown whenever the viewer
 * has finished every prediction in an open window, so a later visit reassures them their work is done
 * without re-popping the modal. Mirrors the pool page's reminder banner, inverted to a settled tone.
 */
export function AllSetBanner({ windows }: { windows: CompletionWindow[] }) {
    const tz = useDisplayTimeZone();

    return (
        <section
            role="status"
            className="card-elevated flex flex-col gap-4 rounded-3xl border border-border bg-card p-5 sm:flex-row sm:items-center sm:gap-3 sm:p-6"
        >
            <span className="mt-0.5 flex size-9 shrink-0 items-center justify-center rounded-full bg-primary text-primary-foreground shadow-[var(--sh-sm)]">
                <CheckCircle2 className="size-4" />
            </span>
            <div className="space-y-1.5">
                <p className="font-semibold text-foreground">
                    All set — waiting for official scores
                </p>
                <p className="text-sm text-muted-foreground">
                    Your predictions are complete. We'll score them as the
                    results come in.
                </p>
                {windows.some((window) => window.deadline) && (
                    <ul className="space-y-1 text-xs text-muted-foreground">
                        {windows
                            .filter((window) => window.deadline)
                            .map((window) => (
                                <li key={window.phase_key}>
                                    <span className="font-medium text-foreground">
                                        {window.label}
                                    </span>{' '}
                                    locks{' '}
                                    {formatMatchDate(window.deadline!, tz)},{' '}
                                    {formatMatchTime(window.deadline!, tz)} —
                                    you can still tweak until then.
                                </li>
                            ))}
                    </ul>
                )}
            </div>
        </section>
    );
}
