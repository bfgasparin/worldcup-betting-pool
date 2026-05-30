import { useRef } from 'react';
import type { ClipboardEvent, KeyboardEvent } from 'react';
import { cn } from '@/lib/utils';

type Props = {
    value: string;
    onChange: (value: string) => void;
    length?: number;
    disabled?: boolean;
    autoFocus?: boolean;
    invalid?: boolean;
    name?: string;
    'aria-label'?: string;
};

/**
 * A segmented one-time-code input. Renders `length` single-character cells
 * that behave like a single numeric field with paste, arrow-key and
 * backspace support. The full value is mirrored into a hidden input so it can
 * be submitted as part of an Inertia <Form>.
 */
export default function OtpInput({
    value,
    onChange,
    length = 6,
    disabled = false,
    autoFocus = false,
    invalid = false,
    name,
    'aria-label': ariaLabel = 'Verification code',
}: Props) {
    const inputs = useRef<Array<HTMLInputElement | null>>([]);

    const focusCell = (index: number): void => {
        const target = inputs.current[index];

        if (target) {
            target.focus();
            target.select();
        }
    };

    const setCharAt = (index: number, char: string): void => {
        const chars = value.split('');
        chars[index] = char;
        onChange(chars.join('').slice(0, length));
    };

    const handleChange = (index: number, raw: string): void => {
        const digits = raw.replace(/\D/g, '');

        if (digits.length === 0) {
            setCharAt(index, '');

            return;
        }

        // Typing/pasting multiple digits into a cell fills subsequent cells.
        const chars = value.split('');

        for (
            let offset = 0;
            offset < digits.length && index + offset < length;
            offset++
        ) {
            chars[index + offset] = digits[offset];
        }

        onChange(chars.join('').slice(0, length));

        const nextIndex = Math.min(index + digits.length, length - 1);
        focusCell(nextIndex);
    };

    const handleKeyDown = (
        index: number,
        event: KeyboardEvent<HTMLInputElement>,
    ): void => {
        if (event.key === 'Backspace') {
            event.preventDefault();

            if (value[index]) {
                setCharAt(index, '');
            } else if (index > 0) {
                setCharAt(index - 1, '');
                focusCell(index - 1);
            }

            return;
        }

        if (event.key === 'ArrowLeft' && index > 0) {
            event.preventDefault();
            focusCell(index - 1);
        }

        if (event.key === 'ArrowRight' && index < length - 1) {
            event.preventDefault();
            focusCell(index + 1);
        }
    };

    const handlePaste = (event: ClipboardEvent<HTMLInputElement>): void => {
        event.preventDefault();
        const digits = event.clipboardData
            .getData('text')
            .replace(/\D/g, '')
            .slice(0, length);

        if (digits.length > 0) {
            onChange(digits);
            focusCell(Math.min(digits.length, length - 1));
        }
    };

    return (
        <div
            className="flex items-center justify-between gap-2"
            role="group"
            aria-label={ariaLabel}
        >
            {Array.from({ length }).map((_, index) => (
                <input
                    key={index}
                    ref={(element) => {
                        inputs.current[index] = element;
                    }}
                    type="text"
                    inputMode="numeric"
                    autoComplete={index === 0 ? 'one-time-code' : 'off'}
                    pattern="[0-9]*"
                    maxLength={1}
                    disabled={disabled}
                    autoFocus={autoFocus && index === 0}
                    value={value[index] ?? ''}
                    aria-invalid={invalid}
                    aria-label={`${ariaLabel} digit ${index + 1}`}
                    onChange={(event) =>
                        handleChange(index, event.target.value)
                    }
                    onKeyDown={(event) => handleKeyDown(index, event)}
                    onPaste={handlePaste}
                    onFocus={(event) => event.target.select()}
                    className={cn(
                        'h-12 w-full min-w-0 rounded-md border border-input bg-transparent text-center text-lg font-semibold shadow-xs transition-[color,box-shadow] outline-none',
                        'focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50',
                        'disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50',
                        invalid &&
                            'border-destructive ring-destructive/20 dark:ring-destructive/40',
                    )}
                />
            ))}

            {name && <input type="hidden" name={name} value={value} />}
        </div>
    );
}
