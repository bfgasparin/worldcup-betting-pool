import { Head, Link } from '@inertiajs/react';
import { ArrowRight, Radio } from 'lucide-react';
import { LiveBadge, LivePulse } from '@/components/live-badge';
import { useTranslation } from '@/hooks/use-translation';
import live from '@/routes/live';
import type { LiveTournamentSummary } from '@/types/live';

interface LiveIndexProps {
    tournaments: LiveTournamentSummary[];
}

export default function LiveIndex({ tournaments }: LiveIndexProps) {
    const { t } = useTranslation();

    return (
        <>
            <Head title={t('Live Center')} />
            <div className="relative min-h-full bg-background">
                <div className="w-full px-4 py-6 sm:px-6 sm:py-8 lg:px-8 xl:px-10">
                    <header className="hero relative mb-6 overflow-hidden rounded-3xl border border-border p-5 sm:mb-8 sm:p-8">
                        <div className="hero-lines" />
                        <div className="relative flex flex-col gap-3">
                            <span className="inline-flex w-fit items-center gap-2 text-xs font-bold tracking-[0.14em] text-muted-foreground uppercase">
                                <LivePulse />
                                {t('Live Center')}
                            </span>
                            <h1 className="text-3xl font-semibold tracking-tight text-balance text-foreground sm:text-5xl">
                                {t('Follow the action')}
                            </h1>
                            <span className="bg-gold-gradient mt-1 h-1 w-12 rounded-full" />
                            <p className="max-w-2xl text-sm text-muted-foreground sm:text-base">
                                {t(
                                    'Pick a tournament to watch live scores roll in and your standings shift in real time.',
                                )}
                            </p>
                        </div>
                    </header>

                    {tournaments.length > 0 ? (
                        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                            {tournaments.map((tournament) => (
                                <Link
                                    key={tournament.slug}
                                    href={live.show(tournament.slug)}
                                    className="group card-elevated flex flex-col gap-4 rounded-3xl border border-border p-6 ring-1 ring-red-500/15 transition-transform duration-200 hover:-translate-y-1"
                                >
                                    <div className="flex items-center justify-between">
                                        <LiveBadge />
                                        <span className="font-display text-sm font-semibold text-muted-foreground tabular-nums">
                                            {t(':count live', {
                                                count: tournament.live_match_count,
                                            })}
                                        </span>
                                    </div>
                                    <h2 className="text-2xl font-semibold tracking-tight text-balance text-foreground">
                                        {t(tournament.name)}
                                    </h2>
                                    <span className="mt-auto inline-flex items-center gap-2 font-display text-sm font-semibold text-primary transition-all group-hover:gap-3">
                                        {t('Watch live')}
                                        <ArrowRight className="size-4" />
                                    </span>
                                </Link>
                            ))}
                        </div>
                    ) : (
                        <div className="card-elevated flex flex-col items-center gap-3 rounded-3xl border border-border p-12 text-center">
                            <Radio className="size-9 text-muted-foreground" />
                            <p className="font-display text-lg font-semibold">
                                {t('Nothing live right now')}
                            </p>
                            <p className="max-w-md text-sm text-muted-foreground">
                                {t(
                                    'When a match in one of your pools kicks off, it’ll show up here so you can follow the scores and your projected standings.',
                                )}
                            </p>
                        </div>
                    )}
                </div>
            </div>
        </>
    );
}

LiveIndex.layout = {
    breadcrumbs: [{ title: 'Live', href: live.index() }],
};
