import { Head, router } from '@inertiajs/react';
import { ClipboardCheck, Trophy } from 'lucide-react';
import { useState } from 'react';
import { Flag } from '@/components/flag';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import games from '@/routes/games';
import type { TeamRef } from '@/types/games';
import type { BreadcrumbItem } from '@/types/navigation';

interface ReviewRowData {
    fixture_id: number;
    match_number: number;
    phase: string;
    is_knockout: boolean;
    status: string;
    has_ended: boolean;
    kicks_off_at: string | null;
    home: TeamRef | null;
    away: TeamRef | null;
    home_label: string | null;
    away_label: string | null;
    proposal: {
        home_goals: number | null;
        away_goals: number | null;
        winner_team_id: number | null;
        home_penalties: number | null;
        away_penalties: number | null;
        status: string;
    } | null;
}

interface ReviewPageProps {
    game: { slug: string; name: string };
    rows: ReviewRowData[];
}

function toNumberOrNull(value: string): number | null {
    return value === '' ? null : Number(value);
}

function ReviewRow({ row, slug }: { row: ReviewRowData; slug: string }) {
    const [home, setHome] = useState(
        row.proposal?.home_goals?.toString() ?? '',
    );
    const [away, setAway] = useState(
        row.proposal?.away_goals?.toString() ?? '',
    );
    const [winner, setWinner] = useState<string>(
        row.proposal?.winner_team_id?.toString() ?? '',
    );
    const [saving, setSaving] = useState(false);
    const [saved, setSaved] = useState(false);

    const save = (rejected: boolean) => {
        setSaving(true);
        setSaved(false);
        router.patch(
            games.scores.proposal({ tournament: slug, fixture: row.fixture_id })
                .url,
            {
                home_goals: toNumberOrNull(home),
                away_goals: toNumberOrNull(away),
                winner_team_id: winner === '' ? null : Number(winner),
                rejected,
            },
            {
                preserveScroll: true,
                onSuccess: () => setSaved(true),
                onFinish: () => setSaving(false),
            },
        );
    };

    const rejected = row.proposal?.status === 'rejected';

    // Mirror the prediction flow: who advances is derived from the score. A decisive result picks
    // the higher-scoring side (shown read-only); only a draw asks the admin to choose.
    const teamsKnown = row.home !== null && row.away !== null;
    const bothScored = home !== '' && away !== '';
    const isDraw = bothScored && Number(home) === Number(away);
    const decisiveWinnerId =
        bothScored && !isDraw && teamsKnown
            ? Number(home) > Number(away)
                ? row.home!.id
                : row.away!.id
            : null;
    const winnerTeam =
        decisiveWinnerId === row.home?.id
            ? row.home
            : decisiveWinnerId === row.away?.id
              ? row.away
              : null;

    const handleScore = (side: 'home' | 'away', value: string): void => {
        const nextHome = side === 'home' ? value : home;
        const nextAway = side === 'away' ? value : away;

        if (side === 'home') {
            setHome(value);
        } else {
            setAway(value);
        }

        if (!row.is_knockout || !teamsKnown) {
            return;
        }

        // A draw keeps any existing manual pick; a decisive or incomplete score derives it.
        if (nextHome === '' || nextAway === '') {
            setWinner('');
        } else if (Number(nextHome) > Number(nextAway)) {
            setWinner(String(row.home!.id));
        } else if (Number(nextAway) > Number(nextHome)) {
            setWinner(String(row.away!.id));
        }
    };

    const stateLabel = row.has_ended
        ? 'Full time'
        : row.status === 'finished'
          ? 'Finished'
          : row.status === 'live'
            ? 'Live'
            : 'Scheduled';

    return (
        <div className="grid grid-cols-1 items-center gap-3 border-b border-border px-4 py-3 last:border-0 sm:grid-cols-[120px_1fr_auto]">
            <div className="flex flex-col gap-1">
                <span className="font-display text-sm font-semibold">
                    Match {row.match_number}
                </span>
                <span className="text-[11px] font-medium text-muted-foreground">
                    {row.phase}
                </span>
                <span className="inline-flex w-fit items-center rounded-full bg-muted px-2 py-0.5 text-[10px] font-bold tracking-wide text-muted-foreground uppercase">
                    {stateLabel}
                </span>
            </div>

            <div className="flex flex-wrap items-center gap-2">
                <span className="flex min-w-0 items-center gap-1.5 text-sm font-semibold">
                    <Flag team={row.home} className="h-4 w-6" />
                    <span className="truncate">
                        {row.home?.name ?? row.home_label}
                    </span>
                </span>
                <Input
                    type="number"
                    min={0}
                    max={99}
                    value={home}
                    onChange={(event) =>
                        handleScore('home', event.target.value)
                    }
                    className="h-9 w-14 text-center"
                    aria-label={`${row.home?.name ?? 'Home'} goals`}
                />
                <span className="text-muted-foreground">–</span>
                <Input
                    type="number"
                    min={0}
                    max={99}
                    value={away}
                    onChange={(event) =>
                        handleScore('away', event.target.value)
                    }
                    className="h-9 w-14 text-center"
                    aria-label={`${row.away?.name ?? 'Away'} goals`}
                />
                <span className="flex min-w-0 items-center gap-1.5 text-sm font-semibold">
                    <Flag team={row.away} className="h-4 w-6" />
                    <span className="truncate">
                        {row.away?.name ?? row.away_label}
                    </span>
                </span>

                {row.is_knockout &&
                    (!teamsKnown ? (
                        <span className="text-xs text-muted-foreground italic">
                            Teams not set yet.
                        </span>
                    ) : !bothScored ? (
                        <span className="text-xs text-muted-foreground italic">
                            Enter the score to set who advances.
                        </span>
                    ) : isDraw ? (
                        <div className="flex items-center gap-2">
                            <span className="text-[0.65rem] font-semibold tracking-wide text-muted-foreground uppercase">
                                Advances
                            </span>
                            <ToggleGroup
                                type="single"
                                variant="outline"
                                size="sm"
                                value={winner}
                                onValueChange={(value) => setWinner(value)}
                                aria-label="Advancing team"
                            >
                                <ToggleGroupItem
                                    value={String(row.home!.id)}
                                    className="gap-1 text-xs"
                                >
                                    <Flag team={row.home} className="h-4 w-6" />
                                    {row.home!.code ?? row.home!.name}
                                </ToggleGroupItem>
                                <ToggleGroupItem
                                    value={String(row.away!.id)}
                                    className="gap-1 text-xs"
                                >
                                    <Flag team={row.away} className="h-4 w-6" />
                                    {row.away!.code ?? row.away!.name}
                                </ToggleGroupItem>
                            </ToggleGroup>
                        </div>
                    ) : (
                        <span className="inline-flex items-center gap-1.5 text-xs font-semibold text-pitch-deep dark:text-primary">
                            <span className="text-[0.65rem] font-semibold tracking-wide text-muted-foreground uppercase">
                                Advances
                            </span>
                            <Flag team={winnerTeam} className="h-4 w-6" />
                            {winnerTeam?.code ?? winnerTeam?.name}
                        </span>
                    ))}
            </div>

            <div className="flex items-center gap-2 justify-self-end">
                {saved && !rejected && (
                    <span className="text-xs font-semibold text-pitch-deep dark:text-primary">
                        Saved
                    </span>
                )}
                {rejected && (
                    <span className="text-xs font-semibold text-muted-foreground">
                        Skipped
                    </span>
                )}
                <Button
                    size="sm"
                    variant="outline"
                    disabled={saving}
                    onClick={() => save(false)}
                >
                    {saving ? 'Saving…' : 'Save'}
                </Button>
                <Button
                    size="sm"
                    variant="ghost"
                    disabled={saving}
                    onClick={() => save(true)}
                >
                    Skip
                </Button>
            </div>
        </div>
    );
}

export default function ScoreReview({ game, rows }: ReviewPageProps) {
    const [approving, setApproving] = useState(false);
    const [errors, setErrors] = useState<Record<string, string>>({});

    const approve = () => {
        setApproving(true);
        router.post(
            games.scores.approve(game.slug).url,
            {},
            {
                onError: (formErrors) => setErrors(formErrors),
                onFinish: () => setApproving(false),
            },
        );
    };

    return (
        <>
            <Head title={`Review scores — ${game.name}`} />
            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <header className="hero relative overflow-hidden rounded-3xl border border-border p-8">
                    <div className="hero-lines" />
                    <div className="relative flex flex-wrap items-end justify-between gap-4">
                        <div className="flex flex-col gap-3">
                            <span className="inline-flex items-center gap-2 text-xs font-bold tracking-[0.14em] text-muted-foreground uppercase">
                                <ClipboardCheck className="size-4 text-primary" />
                                Score review
                            </span>
                            <h1 className="text-3xl font-semibold tracking-tight text-foreground sm:text-4xl">
                                {game.name}
                            </h1>
                            <p className="max-w-xl text-sm text-muted-foreground">
                                Enter or correct each final score, set the
                                advancing team for knockout matches, then
                                approve to publish results and update everyone's
                                points.
                            </p>
                        </div>
                        <Button onClick={approve} disabled={approving}>
                            <Trophy className="size-4" />
                            {approving ? 'Approving…' : 'Approve & publish'}
                        </Button>
                    </div>
                </header>

                {(errors.proposals || errors.batch) && (
                    <div className="rounded-2xl border border-destructive/30 bg-destructive/[0.06] p-4 text-sm font-medium text-destructive">
                        {errors.proposals ?? errors.batch}
                    </div>
                )}

                {rows.length > 0 ? (
                    <div className="overflow-hidden rounded-3xl border border-border bg-card shadow-[var(--sh-sm)]">
                        {rows.map((row) => (
                            <ReviewRow
                                key={row.fixture_id}
                                row={row}
                                slug={game.slug}
                            />
                        ))}
                    </div>
                ) : (
                    <div className="flex min-h-44 flex-col items-center justify-center gap-2 rounded-3xl border border-dashed border-border p-8 text-center">
                        <ClipboardCheck className="size-6 text-muted-foreground" />
                        <p className="font-display font-semibold">
                            Nothing to review
                        </p>
                        <p className="max-w-sm text-sm text-muted-foreground">
                            No finished matches are waiting for a score right
                            now. Matches appear here once they have ended.
                        </p>
                    </div>
                )}
            </div>
        </>
    );
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Tournaments', href: games.index() },
];

ScoreReview.layout = { breadcrumbs };
