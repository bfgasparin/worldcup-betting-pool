import { Head, Link, router } from '@inertiajs/react';
import { ArrowRight, Check, Upload } from 'lucide-react';
import { useState } from 'react';
import { PlayerPicker } from '@/components/player-picker';
import type { PlayerOption } from '@/components/player-picker';
import { PoolIdentity } from '@/components/pool-identity';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { useTranslation } from '@/hooks/use-translation';
import { resolveAccent } from '@/lib/accents';
import { cn } from '@/lib/utils';
import manage from '@/routes/manage';
import type { BreadcrumbItem } from '@/types/navigation';

interface PoolOption {
    id: number;
    name: string;
    source: string;
    slug: string;
    accent: string | null;
    scoring_label: string;
}

interface BackfillProps {
    tournament: { name: string; slug: string };
    pools: PoolOption[];
    users: PlayerOption[];
}

const FIELD =
    'w-full rounded-xl border border-input bg-background px-3 py-2.5 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50';

export default function Backfill({ tournament, pools, users }: BackfillProps) {
    const { t } = useTranslation();
    const [poolId, setPoolId] = useState<number | null>(
        pools.length === 1 ? pools[0].id : null,
    );
    const [userId, setUserId] = useState<number | null>(null);
    const [json, setJson] = useState('');
    const [submitting, setSubmitting] = useState(false);
    const [errors, setErrors] = useState<Record<string, string>>({});

    const submit = () => {
        setSubmitting(true);
        setErrors({});
        router.post(
            manage.backfill.preview(tournament.slug).url,
            { pool_id: poolId, user_id: userId, json },
            {
                onError: (formErrors) => setErrors(formErrors),
                onFinish: () => setSubmitting(false),
            },
        );
    };

    const canSubmit = poolId !== null && userId !== null && json.trim() !== '';

    return (
        <>
            <Head
                title={`${t('Backfill predictions')} · ${t(tournament.name)}`}
            />
            <div className="flex h-full flex-1 flex-col gap-6 p-4 sm:p-6 lg:p-8">
                <header className="hero relative overflow-hidden rounded-3xl border border-border p-8">
                    <div className="hero-lines" />
                    <div className="relative flex flex-col gap-3">
                        <span className="inline-flex items-center gap-2 text-xs font-bold tracking-[0.14em] text-muted-foreground uppercase">
                            <Upload className="size-4 text-primary" />
                            {t('Backfill predictions')}
                        </span>
                        <h1 className="text-3xl font-semibold tracking-tight text-foreground sm:text-4xl">
                            {t(tournament.name)}
                        </h1>
                        <p className="max-w-xl text-sm text-muted-foreground">
                            {t(
                                'Paste a player’s predictions as JSON to enter them on their behalf. You’ll review and correct every value before anything is saved, then the pool is re-scored.',
                            )}
                        </p>
                    </div>
                </header>

                <div className="flex flex-col gap-5 rounded-3xl border border-border bg-card p-5 shadow-[var(--sh-sm)] sm:p-6">
                    {pools.length === 0 ? (
                        <p className="text-sm text-muted-foreground">
                            {t(
                                'This tournament has no upfront-bracket pools to backfill.',
                            )}
                        </p>
                    ) : (
                        <>
                            <div className="flex flex-col gap-2">
                                <Label>{t('Pool')}</Label>
                                <div className="flex flex-col gap-2">
                                    {pools.map((pool) => {
                                        const accent = resolveAccent(
                                            pool.accent,
                                        );
                                        const isSelected = poolId === pool.id;

                                        return (
                                            <button
                                                key={pool.id}
                                                type="button"
                                                onClick={() =>
                                                    setPoolId(pool.id)
                                                }
                                                aria-pressed={isSelected}
                                                className={cn(
                                                    'press flex w-full items-center gap-3 rounded-2xl border px-4 py-3 text-left transition-colors',
                                                    isSelected
                                                        ? cn(
                                                              'bg-primary/[0.06]',
                                                              accent.ringClass,
                                                          )
                                                        : 'border-border hover:border-primary/40',
                                                )}
                                            >
                                                <PoolIdentity
                                                    source={pool.source}
                                                    name={pool.name}
                                                    scoringLabel={
                                                        pool.scoring_label
                                                    }
                                                    accent={pool.accent}
                                                    variant="compact"
                                                    className="min-w-0 flex-1"
                                                />
                                                <span
                                                    className={cn(
                                                        'grid size-5 shrink-0 place-items-center rounded-full border',
                                                        isSelected
                                                            ? 'border-primary bg-primary text-white'
                                                            : 'border-border',
                                                    )}
                                                >
                                                    {isSelected && (
                                                        <Check className="size-3.5" />
                                                    )}
                                                </span>
                                            </button>
                                        );
                                    })}
                                </div>
                                {errors.pool_id && (
                                    <p className="text-xs font-medium text-destructive">
                                        {errors.pool_id}
                                    </p>
                                )}
                            </div>

                            <div className="flex flex-col gap-2">
                                <Label htmlFor="player">{t('Player')}</Label>
                                <PlayerPicker
                                    id="player"
                                    players={users}
                                    value={userId}
                                    onSelect={setUserId}
                                    invalid={Boolean(errors.user_id)}
                                />
                                {errors.user_id && (
                                    <p className="text-xs font-medium text-destructive">
                                        {errors.user_id}
                                    </p>
                                )}
                            </div>

                            <div className="flex flex-col gap-2">
                                <Label htmlFor="json">
                                    {t('Predictions JSON')}
                                </Label>
                                <textarea
                                    id="json"
                                    className={cn(FIELD, 'min-h-64 font-mono')}
                                    spellCheck={false}
                                    value={json}
                                    onChange={(event) =>
                                        setJson(event.target.value)
                                    }
                                    placeholder='{ "matches": [ { "match_number": 1, "home_team": "MEX", "away_team": "RSA", "home_goals": 2, "away_goals": 0 } ] }'
                                />
                                {(errors.json || errors.payload) && (
                                    <p className="text-xs font-medium text-destructive">
                                        {errors.json ?? errors.payload}
                                    </p>
                                )}
                            </div>

                            <div className="flex items-center justify-end gap-2">
                                <Button variant="ghost" asChild>
                                    <Link href={manage.index().url}>
                                        {t('Cancel')}
                                    </Link>
                                </Button>
                                <Button
                                    onClick={submit}
                                    disabled={!canSubmit || submitting}
                                >
                                    {submitting
                                        ? t('Analysing…')
                                        : t('Review import')}
                                    <ArrowRight className="size-4" />
                                </Button>
                            </div>
                        </>
                    )}
                </div>
            </div>
        </>
    );
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Manage', href: manage.index() },
];

Backfill.layout = { breadcrumbs };
