import { Check } from 'lucide-react';
import { cn } from '@/lib/utils';

type Step = {
    key: string;
    label: string;
};

type Props = {
    steps: readonly Step[];
    currentIndex: number;
};

/**
 * Vertical step list for the onboarding wizard's desktop pitch panel — a numbered rail
 * that marks completed (✓), current and upcoming steps. The compact segmented
 * {@link OnboardingProgress} bar plays the same role on mobile.
 */
export default function OnboardingStepper({ steps, currentIndex }: Props) {
    return (
        <ol className="flex flex-col">
            {steps.map((step, index) => {
                const completed = index < currentIndex;
                const current = index === currentIndex;
                const isLast = index === steps.length - 1;

                return (
                    <li
                        key={step.key}
                        aria-current={current ? 'step' : undefined}
                        className="flex gap-3.5"
                    >
                        <div className="flex flex-col items-center">
                            <span
                                className={cn(
                                    'flex size-8 shrink-0 items-center justify-center rounded-full border text-[13px] font-semibold transition-colors',
                                    completed &&
                                        'bg-brand-gradient border-transparent text-white',
                                    current &&
                                        'border-primary bg-primary/10 text-primary',
                                    !completed &&
                                        !current &&
                                        'border-border text-muted-foreground',
                                )}
                            >
                                {completed ? (
                                    <Check className="size-4" />
                                ) : (
                                    index + 1
                                )}
                            </span>
                            {!isLast && (
                                <span
                                    className={cn(
                                        'mt-1 w-px flex-1',
                                        completed
                                            ? 'bg-primary/40'
                                            : 'bg-border',
                                    )}
                                />
                            )}
                        </div>
                        <span
                            className={cn(
                                'text-sm leading-8 transition-colors',
                                !isLast && 'pb-6',
                                current
                                    ? 'font-semibold text-foreground'
                                    : completed
                                      ? 'text-foreground'
                                      : 'text-muted-foreground',
                            )}
                        >
                            {step.label}
                        </span>
                    </li>
                );
            })}
        </ol>
    );
}
