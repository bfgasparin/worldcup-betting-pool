import { Slot } from '@radix-ui/react-slot';
import { cva, type VariantProps } from 'class-variance-authority';
import * as React from 'react';

import { cn } from '@/lib/utils';

const chipVariants = cva(
    'inline-flex items-center gap-2 rounded-full border-[1.5px] px-4 py-1.5 font-display text-sm font-semibold transition-colors outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50',
    {
        variants: {
            variant: {
                // Resting filter chip
                default:
                    'border-transparent bg-secondary text-secondary-foreground hover:border-border',
                // Selected filter chip — deep pitch fill (AA-safe with white text)
                active: 'border-transparent bg-pitch-deep text-white',
                // Outline / neutral
                outline: 'border-border bg-transparent text-foreground',
                // Points & scoring — gold tint, brand-amber figures
                points: 'border-transparent bg-accent/15 text-[#8a5a00] dark:text-amber-300 [&_b]:font-semibold [&_b]:text-amber dark:[&_b]:text-amber-400',
                // Pitch gradient — streaks / live highlights
                pitch: 'border-transparent bg-brand-gradient text-white',
            },
        },
        defaultVariants: {
            variant: 'default',
        },
    },
);

function Chip({
    className,
    variant,
    asChild = false,
    ...props
}: React.ComponentProps<'span'> &
    VariantProps<typeof chipVariants> & {
        asChild?: boolean;
    }) {
    const Comp = asChild ? Slot : 'span';

    return (
        <Comp
            data-slot="chip"
            className={cn(chipVariants({ variant, className }))}
            {...props}
        />
    );
}

export { Chip, chipVariants };
