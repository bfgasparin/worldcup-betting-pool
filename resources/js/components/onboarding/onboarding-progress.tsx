import { cn } from '@/lib/utils';

type Step = {
    key: string;
    label: string;
};

type Props = {
    steps: readonly Step[];
    currentIndex: number;
};

/** Minimal segmented progress bar + step counter for the onboarding wizard. */
export default function OnboardingProgress({ steps, currentIndex }: Props) {
    return (
        <div className="flex flex-col gap-2">
            <div className="flex items-center gap-1.5">
                {steps.map((step, index) => (
                    <span
                        key={step.key}
                        className={cn(
                            'h-1.5 flex-1 rounded-full transition-all duration-500',
                            index <= currentIndex
                                ? 'bg-brand-gradient'
                                : 'bg-muted',
                        )}
                    />
                ))}
            </div>
            <p className="text-xs font-medium text-muted-foreground">
                Step {currentIndex + 1} of {steps.length} ·{' '}
                <span className="text-foreground">
                    {steps[currentIndex].label}
                </span>
            </p>
        </div>
    );
}
