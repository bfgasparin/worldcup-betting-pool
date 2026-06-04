import { Slot } from '@radix-ui/react-slot';
import { cva, type VariantProps } from 'class-variance-authority';
import * as React from 'react';

import { cn } from '@/lib/utils';

const buttonVariants = cva(
    "inline-flex cursor-pointer items-center justify-center gap-2 whitespace-nowrap rounded-full font-display font-semibold transition-[transform,box-shadow,background-color,color,border-color] duration-150 active:translate-y-px active:scale-[.99] disabled:pointer-events-none disabled:opacity-50 [&_svg]:pointer-events-none [&_svg:not([class*='size-'])]:size-4 [&_svg]:shrink-0 outline-none focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/30 aria-invalid:border-destructive",
    {
        variants: {
            variant: {
                // Pitch gradient — the one action that matters
                default:
                    'bg-brand-gradient text-white shadow-glow hover:-translate-y-0.5',
                // Gold gradient — points & wins
                gold: 'bg-gold-gradient text-[#3a2600] hover:-translate-y-0.5 hover:shadow-glow-accent',
                // Ink solid — secondary affirmative
                solid: 'bg-foreground text-background hover:-translate-y-0.5 hover:shadow-[var(--sh-md)]',
                secondary:
                    'bg-secondary text-secondary-foreground hover:bg-secondary/80',
                outline:
                    'border-[1.5px] border-border bg-transparent text-foreground hover:border-foreground',
                ghost: 'hover:bg-muted hover:text-foreground',
                link: 'rounded-none text-primary underline-offset-4 hover:underline',
                destructive:
                    'bg-destructive text-white hover:-translate-y-0.5 hover:shadow-[var(--sh-md)]',
            },
            size: {
                default: 'h-11 px-6 text-[15px] has-[>svg]:px-5',
                sm: 'h-9 px-4 text-sm has-[>svg]:px-3.5',
                lg: 'h-12 px-8 text-base has-[>svg]:px-6',
                icon: 'size-10 rounded-full',
            },
        },
        defaultVariants: {
            variant: 'default',
            size: 'default',
        },
    },
);

function Button({
    className,
    variant,
    size,
    asChild = false,
    ...props
}: React.ComponentProps<'button'> &
    VariantProps<typeof buttonVariants> & {
        asChild?: boolean;
    }) {
    const Comp = asChild ? Slot : 'button';

    return (
        <Comp
            data-slot="button"
            className={cn(buttonVariants({ variant, size, className }))}
            {...props}
        />
    );
}

export { Button, buttonVariants };
