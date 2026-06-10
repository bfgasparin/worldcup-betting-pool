import { Camera } from 'lucide-react';
import { useEffect, useMemo, useRef } from 'react';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { useTranslation } from '@/hooks/use-translation';
import { cn } from '@/lib/utils';

type Props = {
    /** URL of the avatar already stored for the user, if any. */
    currentUrl?: string | null;
    /** Initials shown when there is no photo. */
    fallbackInitials: string;
    /** The file currently chosen (controlled by the parent). */
    file: File | null;
    /** Called with the newly chosen file, or null when cleared. */
    onSelect: (file: File | null) => void;
    disabled?: boolean;
    className?: string;
};

/**
 * Square avatar picker with a live preview. Purely presentational — the parent owns the
 * selected `file` and decides how/when to upload it. Shared by the onboarding wizard and the
 * profile settings page.
 */
export default function AvatarUploadField({
    currentUrl,
    fallbackInitials,
    file,
    onSelect,
    disabled,
    className,
}: Props) {
    const { t } = useTranslation();
    const inputRef = useRef<HTMLInputElement>(null);

    const previewUrl = useMemo(
        () => (file ? URL.createObjectURL(file) : null),
        [file],
    );

    useEffect(() => {
        if (!previewUrl) {
            return;
        }

        return () => URL.revokeObjectURL(previewUrl);
    }, [previewUrl]);

    const shown = previewUrl ?? currentUrl ?? undefined;

    const clear = () => {
        onSelect(null);

        if (inputRef.current) {
            inputRef.current.value = '';
        }
    };

    return (
        <div className={cn('flex flex-col items-center gap-4', className)}>
            <button
                type="button"
                disabled={disabled}
                onClick={() => inputRef.current?.click()}
                className="press group relative rounded-full outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:pointer-events-none"
                aria-label={shown ? t('Change photo') : t('Choose photo')}
            >
                <Avatar className="size-28 shadow-[var(--sh-md)] ring-4 ring-card">
                    <AvatarImage src={shown} alt="" className="object-cover" />
                    <AvatarFallback className="bg-brand-gradient font-display text-2xl font-semibold text-white">
                        {fallbackInitials}
                    </AvatarFallback>
                </Avatar>
                <span className="absolute inset-0 flex items-center justify-center rounded-full bg-foreground/45 text-white opacity-0 transition-opacity duration-150 group-hover:opacity-100">
                    <Camera className="size-6" />
                </span>
            </button>

            <input
                ref={inputRef}
                type="file"
                accept="image/png,image/jpeg,image/webp"
                className="sr-only"
                disabled={disabled}
                onChange={(event) => onSelect(event.target.files?.[0] ?? null)}
            />

            <div className="flex items-center gap-2">
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    disabled={disabled}
                    onClick={() => inputRef.current?.click()}
                >
                    {shown ? t('Change photo') : t('Choose photo')}
                </Button>

                {file && (
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        disabled={disabled}
                        onClick={clear}
                    >
                        {t('Clear')}
                    </Button>
                )}
            </div>

            <p className="text-xs text-muted-foreground">
                {t('JPG, PNG or WEBP · up to :size', { size: '4 MB' })}
            </p>
        </div>
    );
}
