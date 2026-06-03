import { Head, router } from '@inertiajs/react';
import { AlertTriangle, CalendarClock } from 'lucide-react';
import { useState } from 'react';
import { Flag } from '@/components/flag';
import { GameIdentity } from '@/components/game-identity';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useDisplayTimeZone } from '@/hooks/use-timezone';
import { toZonedInputValue } from '@/lib/datetime';
import { gameTitle } from '@/lib/game-title';
import games from '@/routes/games';
import type { ScheduleFixtureRow, VenueOption } from '@/types/games';
import type { BreadcrumbItem } from '@/types/navigation';

interface SchedulePageProps {
    game: {
        slug: string;
        name: string;
        source: string;
        accent?: string | null;
        scoring_label?: string;
    };
    venues: VenueOption[];
    rows: ScheduleFixtureRow[];
}

const STATUS_LABELS: Record<ScheduleFixtureRow['status'], string> = {
    scheduled: 'Scheduled',
    live: 'Live',
    finished: 'Finished',
};

/** Render an instant in a given timezone, e.g. "Jun 11, 16:00". */
function formatLocal(iso: string, timeZone: string): string {
    return new Intl.DateTimeFormat('en-US', {
        timeZone,
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
        hour12: false,
    }).format(new Date(iso));
}

function RescheduleRow({
    row,
    venues,
    gameSlug,
    viewerTz,
}: {
    row: ScheduleFixtureRow;
    venues: VenueOption[];
    gameSlug: string;
    viewerTz: string;
}) {
    const isFinished = row.status === 'finished';
    const [venue, setVenue] = useState(row.venue ?? venues[0]?.name ?? '');
    const [kickoff, setKickoff] = useState(
        row.kicks_off_at ? toZonedInputValue(row.kicks_off_at, viewerTz) : '',
    );
    const [saving, setSaving] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [confirmOpen, setConfirmOpen] = useState(false);

    const submit = () => {
        setSaving(true);
        setError(null);
        // `kickoff` is a wall-clock value in the admin's own timezone; the server re-reads it in
        // that zone (from the shared timezone cookie) and stores UTC — no client-side shifting.
        router.patch(
            games.fixtures.reschedule({ game: gameSlug, fixture: row.id }).url,
            { kicks_off_at: kickoff, venue },
            {
                preserveScroll: true,
                onError: (errors) =>
                    setError(
                        errors.kicks_off_at ??
                            errors.venue ??
                            'Could not reschedule this fixture.',
                    ),
                onFinish: () => setSaving(false),
            },
        );
    };

    const onReschedule = () => {
        if (row.governs_prediction_lock) {
            setConfirmOpen(true);

            return;
        }

        submit();
    };

    return (
        <div className="grid grid-cols-1 gap-3 border-b border-border px-4 py-3 last:border-0 lg:grid-cols-[150px_1fr_auto] lg:items-center">
            <div className="flex flex-col gap-1">
                <span className="font-display text-sm font-semibold">
                    Match {row.match_number}
                </span>
                <span className="text-[11px] font-medium text-muted-foreground">
                    {row.phase}
                </span>
                <span className="inline-flex w-fit items-center rounded-full bg-muted px-2 py-0.5 text-[10px] font-bold tracking-wide text-muted-foreground uppercase">
                    {STATUS_LABELS[row.status]}
                </span>
            </div>

            <div className="flex flex-col gap-1">
                <div className="flex flex-wrap items-center gap-2 text-sm font-semibold">
                    <span className="inline-flex min-w-0 items-center gap-1.5">
                        <Flag team={row.home} className="h-4 w-6" />
                        <span className="truncate">
                            {row.home?.name ?? row.home_label}
                        </span>
                    </span>
                    <span className="text-muted-foreground">v</span>
                    <span className="inline-flex min-w-0 items-center gap-1.5">
                        <Flag team={row.away} className="h-4 w-6" />
                        <span className="truncate">
                            {row.away?.name ?? row.away_label}
                        </span>
                    </span>
                </div>
                {row.kicks_off_at && (
                    <span className="text-[11px] text-muted-foreground">
                        Currently {formatLocal(row.kicks_off_at, viewerTz)}
                        {row.venue ? ` · ${row.venue}` : ''}
                    </span>
                )}
                {row.governs_prediction_lock && (
                    <span className="inline-flex w-fit items-center gap-1 text-[11px] font-medium text-amber">
                        <AlertTriangle className="size-3" />
                        Sets the prediction deadline
                    </span>
                )}
            </div>

            {isFinished ? (
                <span className="text-xs text-muted-foreground italic lg:justify-self-end">
                    Finished — can&apos;t be rescheduled.
                </span>
            ) : (
                <div className="flex flex-col gap-1 lg:items-end">
                    <div className="flex flex-wrap items-center gap-2">
                        <Select value={venue} onValueChange={setVenue}>
                            <SelectTrigger size="sm" className="w-44">
                                <SelectValue placeholder="Venue" />
                            </SelectTrigger>
                            <SelectContent>
                                {venues.map((option) => (
                                    <SelectItem
                                        key={option.name}
                                        value={option.name}
                                    >
                                        {option.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <Input
                            type="datetime-local"
                            value={kickoff}
                            onChange={(event) => setKickoff(event.target.value)}
                            className="h-9 w-52"
                            aria-label="Kickoff (your local time)"
                        />
                        <Button
                            size="sm"
                            variant="outline"
                            disabled={saving || kickoff === '' || venue === ''}
                            onClick={onReschedule}
                        >
                            {saving ? 'Saving…' : 'Reschedule'}
                        </Button>
                    </div>
                    <span className="text-[11px] text-muted-foreground">
                        Your local time ({viewerTz})
                    </span>
                    {error && (
                        <span className="text-xs text-destructive">
                            {error}
                        </span>
                    )}
                </div>
            )}

            <Dialog open={confirmOpen} onOpenChange={setConfirmOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Move the prediction deadline?</DialogTitle>
                        <DialogDescription>
                            This match currently sets the prediction deadline
                            for its round. Rescheduling it will move that
                            deadline and may re-open or lock predictions.
                            Continue?
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button
                            variant="ghost"
                            onClick={() => setConfirmOpen(false)}
                        >
                            Cancel
                        </Button>
                        <Button
                            onClick={() => {
                                setConfirmOpen(false);
                                submit();
                            }}
                        >
                            Reschedule anyway
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </div>
    );
}

export default function ScheduleIndex({
    game,
    venues,
    rows,
}: SchedulePageProps) {
    const viewerTz = useDisplayTimeZone();

    // Group fixtures by phase, preserving the match-number order the server sent.
    const phases: { name: string; rows: ScheduleFixtureRow[] }[] = [];

    for (const row of rows) {
        const last = phases[phases.length - 1];

        if (last && last.name === row.phase) {
            last.rows.push(row);
        } else {
            phases.push({ name: row.phase, rows: [row] });
        }
    }

    return (
        <>
            <Head
                title={gameTitle(game.source, game.name, 'Manage schedule')}
            />
            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <header className="hero relative overflow-hidden rounded-3xl border border-border p-8">
                    <div className="hero-lines" />
                    <div className="relative flex flex-col gap-3">
                        <span className="inline-flex items-center gap-2 text-xs font-bold tracking-[0.14em] text-muted-foreground uppercase">
                            <CalendarClock className="size-4 text-primary" />
                            Manage schedule
                        </span>
                        <h1 className="text-3xl font-semibold tracking-tight text-foreground sm:text-4xl">
                            {game.name}
                        </h1>
                        <GameIdentity
                            source={game.source}
                            scoringLabel={game.scoring_label}
                            accent={game.accent}
                        />
                        <p className="max-w-xl text-sm text-muted-foreground">
                            Move a delayed match to a new kickoff and venue.
                            Only matches that haven&apos;t finished can be
                            rescheduled; doing so clears any unpublished
                            proposed result.
                        </p>
                    </div>
                </header>

                {phases.map((phase) => (
                    <section key={phase.name} className="flex flex-col gap-2">
                        <h2 className="font-display text-sm font-semibold tracking-wide text-muted-foreground uppercase">
                            {phase.name}
                        </h2>
                        <div className="overflow-hidden rounded-3xl border border-border bg-card shadow-[var(--sh-sm)]">
                            {phase.rows.map((row) => (
                                <RescheduleRow
                                    key={row.id}
                                    row={row}
                                    venues={venues}
                                    gameSlug={game.slug}
                                    viewerTz={viewerTz}
                                />
                            ))}
                        </div>
                    </section>
                ))}
            </div>
        </>
    );
}

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Games', href: games.index() }];

ScheduleIndex.layout = { breadcrumbs };
