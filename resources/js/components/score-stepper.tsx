import { cn } from '@/lib/utils';

type StepperSize = 'sm' | 'md';

const SIZES: Record<StepperSize, { box: string; btn: string; gap: string }> = {
    sm: {
        box: 'size-10 rounded-lg text-lg',
        btn: 'size-7 text-base',
        gap: 'gap-1.5',
    },
    md: {
        box: 'size-12 rounded-xl text-2xl',
        btn: 'size-9 text-lg',
        gap: 'gap-2',
    },
};

const STEP_BUTTON =
    'inline-flex shrink-0 items-center justify-center rounded-full border-[1.5px] border-border bg-secondary font-display font-semibold leading-none text-foreground transition hover:border-primary hover:text-primary active:scale-90 disabled:cursor-not-allowed disabled:opacity-40 disabled:hover:border-border disabled:hover:text-foreground';

/**
 * Score entry for a single team — −/+ steppers around a large, tappable/typeable score
 * tile. Emits the same string contract as a plain numeric input ('' = not predicted), so it
 * drops into the prediction wizard's auto-save flow without payload changes.
 */
export function ScoreStepper({
    value,
    onChange,
    onCommit,
    disabled = false,
    label,
    size = 'sm',
}: {
    value: string;
    onChange: (value: string) => void;
    onCommit?: () => void;
    disabled?: boolean;
    label: string;
    size?: StepperSize;
}) {
    const s = SIZES[size];

    const step = (delta: number): void => {
        // Don't fabricate a score from an unpredicted field on decrement; '' stays '' until
        // the user adds a goal (or types one), preserving the "'' = not predicted" contract.
        if (value === '' && delta < 0) {
            return;
        }

        const current = value === '' ? 0 : Number(value);
        const next = Math.max(0, Math.min(99, current + delta));
        onChange(String(next));
    };

    return (
        <div className={cn('flex items-center', s.gap)}>
            <button
                type="button"
                tabIndex={-1}
                aria-label={`Decrease ${label}`}
                disabled={disabled}
                onClick={() => step(-1)}
                className={cn(STEP_BUTTON, s.btn)}
            >
                −
            </button>
            <input
                type="text"
                inputMode="numeric"
                role="spinbutton"
                aria-label={label}
                aria-valuemin={0}
                aria-valuemax={99}
                aria-valuenow={value === '' ? undefined : Number(value)}
                value={value}
                disabled={disabled}
                onChange={(event) =>
                    onChange(
                        event.target.value.replace(/[^0-9]/g, '').slice(0, 2),
                    )
                }
                onBlur={onCommit}
                className={cn(
                    'bg-foreground text-center font-display font-semibold text-background tabular-nums caret-primary transition outline-none focus-visible:ring-2 focus-visible:ring-primary/60 disabled:opacity-60',
                    s.box,
                )}
            />
            <button
                type="button"
                tabIndex={-1}
                aria-label={`Increase ${label}`}
                disabled={disabled}
                onClick={() => step(1)}
                className={cn(STEP_BUTTON, s.btn)}
            >
                +
            </button>
        </div>
    );
}
