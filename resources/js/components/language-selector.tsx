import { router } from '@inertiajs/react';
import { Check, Languages } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from '@/hooks/use-translation';
import { cn } from '@/lib/utils';
import { update } from '@/routes/language';

/** The sentinel value for "no explicit choice — follow the device language". */
const DEVICE = 'device';

/**
 * The language picker. "Device language" (the sentinel) means follow the browser; the other
 * options are the supported locales, each shown in its own language (autonym, never translated).
 * Persisting a choice needs the server to re-resolve the locale and re-share the translation bag,
 * so on success we do a full reload — that also refreshes `<html lang>` and the Intl date locale.
 */
export default function LanguageSelector({
    supported,
    current,
}: {
    supported: Record<string, string>;
    current: string | null;
}) {
    const { t } = useTranslation();
    const [saving, setSaving] = useState(false);
    const selected = current ?? DEVICE;

    const options = [
        { value: DEVICE, label: t('Device language') },
        ...Object.entries(supported).map(([value, label]) => ({
            value,
            label,
        })),
    ];

    const choose = (value: string) => {
        if (value === selected || saving) {
            return;
        }

        setSaving(true);
        router.patch(
            update().url,
            { locale: value },
            {
                preserveScroll: true,
                onSuccess: () => window.location.reload(),
                onError: () => setSaving(false),
            },
        );
    };

    return (
        <div className="flex max-w-md flex-col gap-2">
            {options.map(({ value, label }) => {
                const active = value === selected;

                return (
                    <button
                        key={value}
                        type="button"
                        onClick={() => choose(value)}
                        disabled={saving}
                        className={cn(
                            'flex items-center justify-between rounded-xl border px-4 py-3 text-left text-sm transition-colors disabled:opacity-60',
                            active
                                ? 'border-primary bg-primary/5 font-semibold text-foreground'
                                : 'border-border text-muted-foreground hover:bg-muted',
                        )}
                    >
                        <span className="flex items-center gap-2.5">
                            <Languages className="size-4" />
                            {label}
                        </span>
                        {active && <Check className="size-4 text-primary" />}
                    </button>
                );
            })}
        </div>
    );
}
