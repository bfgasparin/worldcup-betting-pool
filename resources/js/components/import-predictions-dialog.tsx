import { router } from '@inertiajs/react';
import { Download, Loader2 } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { useTranslation } from '@/hooks/use-translation';
import { cn } from '@/lib/utils';
import pools from '@/routes/pools';
import type { ImportSource } from '@/types/pools';

interface ImportPredictionsDialogProps {
    poolSlug: string;
    sources: ImportSource[];
    open: boolean;
    onOpenChange: (open: boolean) => void;
    /** Fired just before the import request, e.g. to cancel a pending auto-save. */
    onBeforeImport?: () => void;
}

/**
 * Lets the user copy their picks from a sibling pool into this one. Posts to the import endpoint,
 * which overwrites the open window(s) and redirects back to the wizard; the visit re-seeds the
 * wizard state from the imported predictions (no manual local-state surgery).
 */
export function ImportPredictionsDialog({
    poolSlug,
    sources,
    open,
    onOpenChange,
    onBeforeImport,
}: ImportPredictionsDialogProps) {
    const { t } = useTranslation();
    const [selected, setSelected] = useState<string | null>(
        sources[0]?.slug ?? null,
    );
    const [processing, setProcessing] = useState(false);

    const chosen = sources.find((source) => source.slug === selected) ?? null;

    function submit(): void {
        if (chosen === null) {
            return;
        }

        onBeforeImport?.();
        setProcessing(true);

        router.post(
            pools.predict.import(poolSlug).url,
            { source_pool: chosen.slug },
            {
                preserveScroll: true,
                // Reset wizard state so the imported predictions seed the inputs on reload.
                preserveState: false,
                onSuccess: () => onOpenChange(false),
                onFinish: () => setProcessing(false),
            },
        );
    }

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{t('Import predictions')}</DialogTitle>
                    <DialogDescription>
                        {t(
                            'Copy your picks from another pool of this tournament. This replaces what you have entered for the windows that are open now.',
                        )}
                    </DialogDescription>
                </DialogHeader>

                {sources.length > 1 ? (
                    <ul className="flex flex-col gap-2">
                        {sources.map((source) => (
                            <li key={source.slug}>
                                <button
                                    type="button"
                                    onClick={() => setSelected(source.slug)}
                                    className={cn(
                                        'flex w-full flex-col items-start gap-0.5 rounded-2xl border px-4 py-3 text-left transition',
                                        selected === source.slug
                                            ? 'border-accent bg-accent/10'
                                            : 'border-border hover:border-accent/60',
                                    )}
                                >
                                    <span className="font-semibold text-foreground">
                                        {source.source} · {t(source.name)}
                                    </span>
                                    <span className="text-xs text-muted-foreground">
                                        {source.phase_labels.join(', ')} ·{' '}
                                        {source.predictions_count === 1
                                            ? t(':count pick', {
                                                  count: source.predictions_count,
                                              })
                                            : t(':count picks', {
                                                  count: source.predictions_count,
                                              })}
                                    </span>
                                </button>
                            </li>
                        ))}
                    </ul>
                ) : (
                    chosen && (
                        <div className="rounded-2xl border border-border px-4 py-3">
                            <p className="font-semibold text-foreground">
                                {chosen.source} · {t(chosen.name)}
                            </p>
                            <p className="text-xs text-muted-foreground">
                                {chosen.phase_labels.join(', ')} ·{' '}
                                {chosen.predictions_count === 1
                                    ? t(':count pick', {
                                          count: chosen.predictions_count,
                                      })
                                    : t(':count picks', {
                                          count: chosen.predictions_count,
                                      })}
                            </p>
                        </div>
                    )
                )}

                <DialogFooter>
                    <Button
                        type="button"
                        variant="outline"
                        onClick={() => onOpenChange(false)}
                        disabled={processing}
                    >
                        {t('Cancel')}
                    </Button>
                    <Button
                        type="button"
                        variant="gold"
                        onClick={submit}
                        disabled={processing || chosen === null}
                    >
                        {processing ? (
                            <Loader2 className="size-4 animate-spin" />
                        ) : (
                            <Download className="size-4" />
                        )}
                        {chosen
                            ? t('Import from :source', {
                                  source: chosen.source,
                              })
                            : t('Import')}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
