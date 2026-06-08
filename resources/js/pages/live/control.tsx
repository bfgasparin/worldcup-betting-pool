import { Head, router, usePage } from '@inertiajs/react';
import { Minus, Plus, Radio } from 'lucide-react';
import { useState } from 'react';
import { Flag } from '@/components/flag';
import { LiveBadge } from '@/components/live-badge';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import live from '@/routes/live';
import type { LiveControlFixture } from '@/types/live';

interface LiveControlProps {
    tournament: { name: string; slug: string };
    fixtures: LiveControlFixture[];
}

function formatKickoff(iso: string | null, timezone: string | null): string {
    if (!iso) {
        return 'TBD';
    }

    return new Intl.DateTimeFormat(undefined, {
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
    return (
        <div className="flex items-center gap-2" aria-label={label}>
            <button
                type="button"
                onClick={() => onChange(Math.max(0, value - 1))}
                className="flex size-8 items-center justify-center rounded-full border border-border bg-secondary transition-colors hover:bg-muted"
                aria-label={`Decrease ${label}`}
            >
                <Minus className="size-4" />
            </button>
            <span className="w-6 text-center font-display text-xl font-bold tabular-nums">
                {value}
            </span>
            <button
                type="button"
                onClick={() => onChange(value + 1)}
                className="flex size-8 items-center justify-center rounded-full border border-border bg-secondary transition-colors hover:bg-muted"
                aria-label={`Increase ${label}`}
            >
                <Plus className="size-4" />
            </button>
        </div>
    );
}

function FixtureName({
    name,
    label,
    flagTeam,
}: {
    name: string | null;
    label: string | null;
    flagTeam: LiveControlFixture['home_team'];
}) {
    return (
        <span className="flex items-center gap-2">
            <Flag team={flagTeam} className="size-5" />
            <span className="truncate font-display font-semibold">
                {name ?? label ?? 'TBD'}
            </span>
        </span>
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
    const [home, setHome] = useState(fixture.live_home_goals ?? 0);
    const [away, setAway] = useState(fixture.live_away_goals ?? 0);

    const isLive = fixture.live_status === 'live';
    const isEnded = fixture.live_status === 'ended';

    const goLive = () =>
        router.post(
            live.control.goLive([slug, fixture.id]).url,
            {},
            { preserveScroll: true },
        );

    const saveScore = (nextHome: number, nextAway: number) => {
        setHome(nextHome);
        setAway(nextAway);
        router.patch(
            live.control.score([slug, fixture.id]).url,
            { home_goals: nextHome, away_goals: nextAway },
            { preserveScroll: true, preserveState: true },
        );
    };

    const endMatch = () =>
        router.post(
            live.control.end([slug, fixture.id]).url,
            {},
            { preserveScroll: true },
        );

    return (
        <div
            className={cn(
                'flex flex-col gap-3 border-b border-border p-4 last:border-0 sm:flex-row sm:items-center sm:justify-between',
                isLive && 'bg-red-500/[0.03]',
            )}
        >
            <div className="flex min-w-0 flex-1 flex-col gap-1">
                <div className="flex flex-wrap items-center gap-2">
                    {isLive && <LiveBadge />}
                    {isEnded && <LiveBadge label="Ended" tone="ft" />}
                    {fixture.is_knockout && (
                        <span className="font-display text-[0.65rem] font-bold tracking-[0.14em] text-muted-foreground uppercase">
                            Knockout
                        </span>
                    )}
                    <span className="text-xs text-muted-foreground">
                        {formatKickoff(fixture.kicks_off_at, timezone)}
                    </span>
                </div>
                <div className="grid grid-cols-1 gap-0.5 sm:max-w-xs">
                    <FixtureName
                        name={fixture.home_team?.name ?? null}
                        label={fixture.home_label}
                        flagTeam={fixture.home_team}
                    />
                    <FixtureName
                        name={fixture.away_team?.name ?? null}
                        label={fixture.away_label}
                        flagTeam={fixture.away_team}
                    />
                </div>
            </div>

            <div className="flex shrink-0 items-center gap-4">
                {isLive && (
                    <>
                        <div className="flex items-center gap-4">
                            <Stepper
                                value={home}
                                onChange={(next) => saveScore(next, away)}
                                label="home goals"
                            />
                            <span className="text-muted-foreground">:</span>
                            <Stepper
                                value={away}
                                onChange={(next) => saveScore(home, next)}
                                label="away goals"
                            />
                        </div>
                        <Button variant="outline" onClick={endMatch}>
                            End match
                        </Button>
                    </>
                )}

                {fixture.live_status === null && fixture.can_go_live && (
                    <Button onClick={goLive}>Go live</Button>
                )}

                {isEnded && (
                    <span className="text-sm text-muted-foreground">
                        Sent for approval
                    </span>
                )}
            </div>
        </div>
    );
}

export default function LiveControl({
    tournament,
    fixtures,
}: LiveControlProps) {
    const timezone = usePage().props.timezone;

    return (
        <>
            <Head title={`${tournament.name} · Live control`} />
            <div className="relative min-h-full bg-background">
                <div className="w-full px-4 py-6 sm:px-6 sm:py-8 lg:px-8 xl:px-10">
                    <header className="hero relative mb-6 overflow-hidden rounded-3xl border border-border p-5 sm:mb-8 sm:p-8">
                        <div className="hero-lines" />
                        <div className="relative flex flex-col gap-3">
                            <span className="inline-flex w-fit items-center gap-2 text-xs font-bold tracking-[0.14em] text-muted-foreground uppercase">
                                Live control
                            </span>
                            <h1 className="text-2xl font-semibold tracking-tight text-balance text-foreground sm:text-4xl">
                                {tournament.name}
                            </h1>
                            <span className="bg-gold-gradient mt-1 h-1 w-12 rounded-full" />
                            <p className="max-w-2xl text-sm text-muted-foreground">
                                Start matches as they kick off, keep the live
                                score, and end a match to send its final result
                                for approval. Live scores never touch the
                                official leaderboard.
                            </p>
                        </div>
                    </header>

                    {fixtures.length > 0 ? (
                        <div className="card-elevated overflow-hidden rounded-2xl border border-border">
                            {fixtures.map((fixture) => (
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
                            <p className="font-display text-lg font-semibold">
                                No matches to manage yet
                            </p>
                            <p className="max-w-md text-sm text-muted-foreground">
                                Fixtures appear here once they’re within
                                kick-off range or already live.
                            </p>
                        </div>
                    )}
                </div>
            </div>
        </>
    );
}

LiveControl.layout = {
    breadcrumbs: [{ title: 'Live', href: live.index() }],
};
