import type { LucideIcon } from 'lucide-react';
import { type ReactNode, useEffect, useRef, useState } from 'react';
import { cn } from '@/lib/utils';

export type SegmentedTabItem<T extends string> = {
    value: T;
    label: ReactNode;
    icon?: LucideIcon;
    /** A subtle trailing count (e.g. fixtures left to predict in a phase). */
    count?: number;
    disabled?: boolean;
};

const SIZE_CLASSES = {
    sm: 'px-3.5 py-1.5 text-xs',
    md: 'px-4 py-2 text-sm',
} as const;

/**
 * The one standard for every tab/filter strip. When the segments fit their container they fill it
 * as an equal-width control; when they don't, the row becomes a single-line horizontal scroller
 * with an edge-fade "peek". Mode is decided by a `scrollWidth > clientWidth` measure mirrored into
 * state via a `ResizeObserver` — flex items keep their content min-width in both modes, so the test
 * stays consistent and never reads a ref during render (React-Compiler safe).
 */
export function SegmentedTabs<T extends string>({
    items,
    value,
    onChange,
    size = 'md',
    disabledStyle = 'dim',
    className,
    'aria-label': ariaLabel,
}: {
    items: SegmentedTabItem<T>[];
    value: T;
    onChange: (value: T) => void;
    size?: 'sm' | 'md';
    /** How disabled segments read: dimmed (default) or a dashed "not yet" outline. */
    disabledStyle?: 'dim' | 'dashed';
    className?: string;
    'aria-label'?: string;
}) {
    const trackRef = useRef<HTMLDivElement | null>(null);
    const [overflowing, setOverflowing] = useState(true);

    // Re-measure when the segment set changes — content width can shift without a container resize.
    const signature = items.map((item) => item.value).join('|');

    useEffect(() => {
        const el = trackRef.current;
        if (!el) {
            return;
        }

        const measure = () =>
            setOverflowing(el.scrollWidth > el.clientWidth + 1);

        measure();
        const observer = new ResizeObserver(measure);
        observer.observe(el);

        return () => observer.disconnect();
    }, [signature]);

    return (
        <div
            ref={trackRef}
            role="tablist"
            aria-label={ariaLabel}
            className={cn(
                'flex gap-2',
                overflowing &&
                    'edge-fade-x overflow-x-auto [scrollbar-width:none] [&::-webkit-scrollbar]:hidden',
                className,
            )}
        >
            {items.map((item) => {
                const on = item.value === value;
                const isDisabled = Boolean(item.disabled);
                const Icon = item.icon;

                return (
                    <button
                        key={item.value}
                        type="button"
                        role="tab"
                        aria-selected={on}
                        disabled={isDisabled}
                        onClick={() => {
                            if (!isDisabled) {
                                onChange(item.value);
                            }
                        }}
                        className={cn(
                            'press inline-flex items-center justify-center gap-2 rounded-full border-[1.5px] font-display font-semibold whitespace-nowrap transition-colors outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50',
                            SIZE_CLASSES[size],
                            overflowing ? 'shrink-0' : 'flex-1',
                            on
                                ? 'border-transparent bg-pitch-deep text-white'
                                : isDisabled && disabledStyle === 'dashed'
                                  ? 'cursor-not-allowed border-dashed border-border/70 text-muted-foreground/50'
                                  : 'border-transparent bg-secondary text-secondary-foreground hover:border-border',
                            isDisabled &&
                                disabledStyle === 'dim' &&
                                'cursor-not-allowed opacity-40 hover:border-transparent',
                        )}
                    >
                        {Icon ? <Icon className="size-4" /> : null}
                        {item.label}
                        {item.count != null ? (
                            <span
                                className={cn(
                                    'text-[11px] font-bold',
                                    on ? 'text-white/70' : 'text-muted-foreground',
                                )}
                            >
                                {item.count}
                            </span>
                        ) : null}
                    </button>
                );
            })}
        </div>
    );
}
