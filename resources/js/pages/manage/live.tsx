import { Head, router, usePage } from '@inertiajs/react';
import { Minus, Plus, Radio, Search } from 'lucide-react';
import { useMemo, useState } from 'react';
import { LiveBadge } from '@/components/live-badge';
import { TeamScoreRow } from '@/components/team-score-row';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { SegmentedTabs } from '@/components/ui/segmented-tabs';
import { useTranslation } from '@/hooks/use-translation';
import type { Translator } from '@/hooks/use-translation';
import { getActiveLocale } from '@/lib/locale';
import { cn } from '@/lib/utils';
import manage from '@/routes/manage';
import type { LiveControlFixture } from '@/types/live';

interface LiveControlProps {
    tournament: { name: string; slug: string };
    fixtures: LiveControlFixture[];
}

type LiveTab = 'liveReady' | 'upcoming' | 'ended' | 'all';
type LiveBucket = 'liveReady' | 'upcoming' | 'ended';

/**
 * Which filter bucket a fixture belongs to. A live match folds into "Live & ready" so it stays
 * visible (and manageable) in the default view — it only moves to "Ended" once the admin actually
 * ends it. No clock math here: `can_go_live`/`live_status` are computed server-side.
 */
function classifyFixture(fixture: LiveControlFixture): LiveBucket {
    if (fixture.live_status === 'ended') {
        return 'ended';
    }

    if (fixture.live_status === 'live' || fixture.can_go_live) {
        return 'liveReady';
    }

    return 'upcoming';
}

/** Case-insensitive substring match across both team names and both placeholder labels. */
function matchesQuery(fixture: LiveControlFixture, needle: string): boolean {
    return [
        fixture.home_team?.name,
        fixture.away_team?.name,
        fixture.home_label,
        fixture.away_label,
    ]
        .filter(Boolean)
        .join(' ')
        .toLowerCase()
        .includes(needle);
}

function formatKickoff(
    iso: string | null,
    timezone: string | null,
    t: Translator['t'],
): string {
    if (!iso) {
        return t('TBD');
    }

    return new Intl.DateTimeFormat(getActiveLocale(), {
        weekday: 'short',
        hour: '2-digit',
        minute: '2-digit',
        timeZone: timezone ?? undefined,
    }).format(new Date(iso));
}

function Stepper({
    value,
    onChange,
    label,
}: {
    value: number;
    onChange: (value: number) => void;
    label: string;
}) {
    const { t } = useTranslation();

    return (
        <div className="flex items-center gap-2" aria-label={label}>
            <button
                type="button"
                onClick={() => onChange(Math.max(0, value - 1))}
                className="press flex size-8 items-center justify-center rounded-full border border-border bg-secondary transition-colors hover:bg-muted"
                aria-label={t('Decrease :label', { label })}
            >
                <Minus className="size-4" />
            </button>
            <span className="w-6 text-center font-display text-xl font-bold tabular-nums">
                {value}
            </span>
            <button
                type="button"
                onClick={() => onChange(value + 1)}
                className="press flex size-8 items-center justify-center rounded-full border border-border bg-secondary transition-colors hover:bg-muted"
                aria-label={t('Increase :label', { label })}
            >
                <Plus className="size-4" />
            </button>
        </div>
    );
}

function LiveControlRow({
    fixture,
    slug,
    timezone,
}: {
    fixture: LiveControlFixture;
    slug: string;
    timezone: string | null;
}) {
    const { t } = useTranslation();
    const [home, setHome] = useState(fixture.live_home_goals ?? 0);
    const [away, setAway] = useState(fixture.live_away_goals ?? 0);

    const isLive = fixture.live_status === 'live';
    const isEnded = fixture.live_status === 'ended';

    const goLive = () =>
        router.post(
            manage.live.goLive([slug, fixture.id]).url,
            {},
            { preserveScroll: true },
        );

    const saveScore = (nextHome: number, nextAway: number) => {
        setHome(nextHome);
        setAway(nextAway);
        router.patch(
            manage.live.score([slug, fixture.id]).url,
            { home_goals: nextHome, away_goals: nextAway },
            { preserveScroll: true, preserveState: true },
        );
    };

    const endMatch = () =>
        router.post(
            manage.live.end([slug, fixture.id]).url,
            {},
            { preserveScroll: true },
        );

    // The score control for one team, beside its name: a stepper while live, the final number once
    // ended, a muted dash before kick-off.
    const scoreControl = (
        value: number,
        onChange: (next: number) => void,
        label: string,
    ) => {
        if (isLive) {
            return <Stepper value={value} onChange={onChange} label={label} />;
        }

        if (isEnded) {
            return (
                <span className="font-display text-lg font-semibold tabular-nums">
                    {value}
                </span>
            );
        }

        return <span className="text-base text-muted-foreground">—</span>;
    };

    return (
        <div
            className={cn(
                'flex flex-col gap-3 border-b border-border p-4 last:border-0',
                isLive && 'bg-red-500/[0.03]',
            )}
        >
            <div className="flex flex-wrap items-center gap-2">
                {isLive && <LiveBadge />}
                {isEnded && <LiveBadge label={t('Ended')} tone="ft" />}
                {fixture.is_knockout && (
                    <span className="font-display text-[0.65rem] font-bold tracking-[0.14em] text-muted-foreground uppercase">
                        {t('Knockout')}
                    </span>
                )}
                <span className="text-xs text-muted-foreground">
                    {formatKickoff(fixture.kicks_off_at, timezone, t)}
                </span>
            </div>

            <div>
                <TeamScoreRow
                    team={fixture.home_team}
                    label={fixture.home_label}
                >
                    {scoreControl(
                        home,
                        (next) => saveScore(next, away),
                        t('home goals'),
                    )}
                </TeamScoreRow>
                <TeamScoreRow
                    team={fixture.away_team}
                    label={fixture.away_label}
                >
                    {scoreControl(
                        away,
                        (next) => saveScore(home, next),
                        t('away goals'),
                    )}
                </TeamScoreRow>
            </div>

            {isLive && (
                <Dialog>
                    <DialogTrigger asChild>
                        <Button
                            variant="outline"
                            className="w-full sm:w-auto sm:self-end"
                        >
                            {t('End match')}
                        </Button>
                    </DialogTrigger>
                    <DialogContent>
                        <DialogHeader>
                            <DialogTitle>{t('End this match?')}</DialogTitle>
                            <DialogDescription>
                                {t(
                                    "This closes the live scoreboard and sends the current score for approval. Double-check the score first — you can't reopen the match here.",
                                )}
                            </DialogDescription>
                        </DialogHeader>
                        <DialogFooter className="gap-2">
                            <DialogClose asChild>
                                <Button variant="secondary">
                                    {t('Cancel')}
                                </Button>
                            </DialogClose>
                            <DialogClose asChild>
                                <Button onClick={endMatch}>
                                    {t('End match')}
                                </Button>
                            </DialogClose>
                        </DialogFooter>
                    </DialogContent>
                </Dialog>
            )}

            {fixture.live_status === null && fixture.can_go_live && (
                <Button
                    onClick={goLive}
                    className="w-full sm:w-auto sm:self-end"
                >
                    {t('Go live')}
                </Button>
            )}

            {isEnded && (
                <span className="text-sm text-muted-foreground">
                    {t('Final score sent for approval.')}
                </span>
            )}
        </div>
    );
}

export default function LiveControl({
    tournament,
    fixtures,
}: LiveControlProps) {
    const { t } = useTranslation();
    const timezone = usePage().props.timezone;

    const [tab, setTab] = useState<LiveTab>('liveReady');
    const [query, setQuery] = useState('');

    // Counts come from the full set, so they stay put while the admin types a search.
    const counts = useMemo(() => {
        const tally = {
            liveReady: 0,
            upcoming: 0,
            ended: 0,
            all: fixtures.length,
        };

        for (const fixture of fixtures) {
            tally[classifyFixture(fixture)] += 1;
        }

        return tally;
    }, [fixtures]);

    const visible = useMemo(() => {
        const needle = query.trim().toLowerCase();

        return fixtures.filter((fixture) => {
            const inTab = tab === 'all' || classifyFixture(fixture) === tab;

            return inTab && (needle === '' || matchesQuery(fixture, needle));
        });
    }, [fixtures, tab, query]);

    return (
        <>
            <Head title={`${t(tournament.name)} · ${t('Live control')}`} />
            <div className="relative min-h-full bg-background">
                <div className="w-full px-4 py-6 sm:px-6 sm:py-8 lg:px-8 xl:px-10">
                    <header className="hero relative mb-6 overflow-hidden rounded-3xl border border-border p-5 sm:mb-8 sm:p-8">
                        <div className="hero-lines" />
                        <div className="relative flex flex-col gap-3">
                            <span className="inline-flex w-fit items-center gap-2 text-xs font-bold tracking-[0.14em] text-muted-foreground uppercase">
                                {t('Live control')}
                            </span>
                            <h1 className="text-2xl font-semibold tracking-tight text-balance text-foreground sm:text-4xl">
                                {t(tournament.name)}
                            </h1>
                            <span className="bg-gold-gradient mt-1 h-1 w-12 rounded-full" />
                            <p className="max-w-2xl text-sm text-muted-foreground">
                                {t(
                                    'Start matches as they kick off, keep the live score, and end a match to send its final result for approval. Live scores never touch the official leaderboard.',
                                )}
                            </p>
                        </div>
                    </header>

                    {fixtures.length === 0 ? (
                        <div className="card-elevated flex flex-col items-center gap-3 rounded-3xl border border-border p-12 text-center">
                            <Radio className="size-9 text-muted-foreground" />
                            <p className="font-display text-lg font-semibold">
                                {t('No matches to manage yet')}
                            </p>
                            <p className="max-w-md text-sm text-muted-foreground">
                                {t(
                                    'Fixtures appear here once they’re within kick-off range or already live.',
                                )}
                            </p>
                        </div>
                    ) : (
                        <>
                            <div className="mb-5 flex flex-col gap-3">
                                <SegmentedTabs<LiveTab>
                                    aria-label={t('Filter matches')}
                                    value={tab}
                                    onChange={setTab}
                                    items={[
                                        {
                                            value: 'liveReady',
                                            label: t('Live & ready'),
                                            count: counts.liveReady,
                                        },
                                        {
                                            value: 'upcoming',
                                            label: t('Upcoming'),
                                            count: counts.upcoming,
                                        },
                                        {
                                            value: 'ended',
                                            label: t('Ended'),
                                            count: counts.ended,
                                        },
                                        {
                                            value: 'all',
                                            label: t('All'),
                                            count: counts.all,
                                        },
                                    ]}
                                />
                                <div className="relative sm:max-w-sm">
                                    <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                                    <Input
                                        type="search"
                                        value={query}
                                        onChange={(event) =>
                                            setQuery(event.target.value)
                                        }
                                        placeholder={t('Search by team')}
                                        className="pl-9"
                                    />
                                </div>
                            </div>

                            {visible.length > 0 ? (
                                <div className="card-elevated overflow-hidden rounded-2xl border border-border">
                                    {visible.map((fixture) => (
                                        <LiveControlRow
                                            key={fixture.id}
                                            fixture={fixture}
                                            slug={tournament.slug}
                                            timezone={timezone}
                                        />
                                    ))}
                                </div>
                            ) : (
                                <div className="card-elevated flex flex-col items-center gap-3 rounded-3xl border border-border p-12 text-center">
                                    <Radio className="size-9 text-muted-foreground" />
                                    <p className="max-w-md font-display text-base font-semibold text-muted-foreground">
                                        {query.trim() !== ''
                                            ? t(
                                                  'No match matches your search. Try the All tab.',
                                              )
                                            : t(
                                                  'No matches in this view — check Upcoming or All.',
                                              )}
                                    </p>
                                </div>
                            )}
                        </>
                    )}
                </div>
            </div>
        </>
    );
}

LiveControl.layout = {
    breadcrumbs: [{ title: 'Manage', href: manage.index() }],
};
