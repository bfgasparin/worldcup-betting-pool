import { Head, Link, router } from '@inertiajs/react';
import {
    CalendarDays,
    ChevronLeft,
    ChevronRight,
    Lock,
    Save,
} from 'lucide-react';
import { useMemo, useState } from 'react';
import { toast } from 'sonner';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { cn } from '@/lib/utils';
import games from '@/routes/games';
import type {
    KnockoutPredictionFixture,
    PredictBracketPhase,
    PredictGroup,
    PredictGroupFixture,
    PredictPageProps,
    StandingRow,
    TeamRef,
    ThirdRanking,
} from '@/types/games';
import type { BreadcrumbItem } from '@/types/navigation';

type ScorePair = { home: string; away: string };
type GroupScores = Record<number, ScorePair>;
type KnockoutPick = { home: string; away: string; advancing: number | null };
type KnockoutPicks = Record<number, KnockoutPick>;

const KNOCKOUT_STEPS: { title: string; phaseKeys: string[] }[] = [
    { title: 'Round of 32', phaseKeys: ['round_of_32'] },
    { title: 'Round of 16', phaseKeys: ['round_of_16'] },
    { title: 'Quarter-finals', phaseKeys: ['quarter_finals'] },
    { title: 'Semi-finals', phaseKeys: ['semi_finals'] },
    { title: 'Third Place & Final', phaseKeys: ['third_place', 'final'] },
];

const STEP_TITLES = [
    'Group Stage',
    ...KNOCKOUT_STEPS.map((step) => step.title),
];

function teamName(
    team: TeamRef | null,
    fallback: string | null = null,
): string {
    return team?.name ?? fallback ?? 'TBD';
}

function toScore(value: number | null): string {
    return value === null || value === undefined ? '' : String(value);
}

function buildGroupScores(groups: PredictGroup[]): GroupScores {
    const scores: GroupScores = {};

    for (const group of groups) {
        for (const fixture of group.fixtures) {
            scores[fixture.fixture_id] = {
                home: toScore(fixture.home_goals),
                away: toScore(fixture.away_goals),
            };
        }
    }

    return scores;
}

function buildPicks(bracket: PredictBracketPhase[]): KnockoutPicks {
    const picks: KnockoutPicks = {};

    for (const phase of bracket) {
        for (const fixture of phase.fixtures) {
            picks[fixture.fixture_id] = {
                home: toScore(fixture.home_goals),
                away: toScore(fixture.away_goals),
                advancing: fixture.advancing_team_id,
            };
        }
    }

    return picks;
}

function ScoreInput({
    value,
    onChange,
    disabled,
    label,
}: {
    value: string;
    onChange: (value: string) => void;
    disabled: boolean;
    label: string;
}) {
    return (
        <Input
            type="number"
            inputMode="numeric"
            min={0}
            max={99}
            aria-label={label}
            value={value}
            disabled={disabled}
            onChange={(event) =>
                onChange(event.target.value.replace(/[^0-9]/g, '').slice(0, 2))
            }
            className="h-9 w-12 px-0 text-center tabular-nums"
        />
    );
}

function GroupStandingsTable({ standings }: { standings: StandingRow[] }) {
    return (
        <table className="w-full border-t border-border/60 pt-2 text-xs">
            <thead>
                <tr className="text-muted-foreground">
                    <th className="w-5 text-left font-medium">#</th>
                    <th className="text-left font-medium">Team</th>
                    <th className="w-8 text-center font-medium">Pld</th>
                    <th className="w-8 text-center font-medium">GD</th>
                    <th className="w-8 text-center font-medium">Pts</th>
                </tr>
            </thead>
            <tbody>
                {standings.map((row) => (
                    <tr
                        key={row.team?.id ?? row.rank}
                        className={cn(
                            'border-t border-border/30',
                            row.rank <= 2 && 'font-semibold text-foreground',
                            row.rank > 2 && 'text-muted-foreground',
                        )}
                    >
                        <td className="py-0.5">{row.rank}</td>
                        <td className="truncate py-0.5">
                            {row.team?.name ?? '—'}
                        </td>
                        <td className="py-0.5 text-center tabular-nums">
                            {row.played}
                        </td>
                        <td className="py-0.5 text-center tabular-nums">
                            {row.goal_difference > 0
                                ? `+${row.goal_difference}`
                                : row.goal_difference}
                        </td>
                        <td className="py-0.5 text-center tabular-nums">
                            {row.points}
                        </td>
                    </tr>
                ))}
            </tbody>
        </table>
    );
}

function GroupCard({
    group,
    scores,
    canEdit,
    onChange,
}: {
    group: PredictGroup;
    scores: GroupScores;
    canEdit: boolean;
    onChange: (fixtureId: number, side: 'home' | 'away', value: string) => void;
}) {
    return (
        <div className="card-elevated overflow-hidden rounded-2xl">
            <div className="bg-brand-gradient px-5 py-3">
                <h3 className="text-sm font-black tracking-wide text-primary-foreground uppercase">
                    Group {group.name}
                </h3>
            </div>
            <div className="flex flex-col gap-4 p-5">
                <div className="flex flex-col gap-2">
                    {group.fixtures.map((fixture: PredictGroupFixture) => {
                        const score = scores[fixture.fixture_id] ?? {
                            home: '',
                            away: '',
                        };

                        return (
                            <div
                                key={fixture.fixture_id}
                                className="grid grid-cols-[1fr_auto_1fr] items-center gap-2"
                            >
                                <span className="truncate text-right text-sm text-muted-foreground">
                                    {teamName(fixture.home)}
                                </span>
                                <div className="flex items-center gap-1">
                                    <ScoreInput
                                        value={score.home}
                                        disabled={!canEdit}
                                        label={`${teamName(fixture.home)} goals`}
                                        onChange={(value) =>
                                            onChange(
                                                fixture.fixture_id,
                                                'home',
                                                value,
                                            )
                                        }
                                    />
                                    <span className="text-muted-foreground">
                                        –
                                    </span>
                                    <ScoreInput
                                        value={score.away}
                                        disabled={!canEdit}
                                        label={`${teamName(fixture.away)} goals`}
                                        onChange={(value) =>
                                            onChange(
                                                fixture.fixture_id,
                                                'away',
                                                value,
                                            )
                                        }
                                    />
                                </div>
                                <span className="truncate text-sm text-muted-foreground">
                                    {teamName(fixture.away)}
                                </span>
                            </div>
                        );
                    })}
                </div>
                <GroupStandingsTable standings={group.standings} />
            </div>
        </div>
    );
}

function KnockoutCard({
    fixture,
    pick,
    canEdit,
    isFinal,
    onScore,
    onAdvance,
}: {
    fixture: KnockoutPredictionFixture;
    pick: KnockoutPick;
    canEdit: boolean;
    isFinal: boolean;
    onScore: (side: 'home' | 'away', value: string) => void;
    onAdvance: (teamId: number | null) => void;
}) {
    const resolved = fixture.home !== null && fixture.away !== null;

    return (
        <div
            className={cn(
                'flex w-64 flex-col gap-2 rounded-xl p-3.5 text-sm',
                isFinal
                    ? 'shadow-glow-accent border border-accent/40 bg-card'
                    : 'card-elevated',
            )}
        >
            <SlotRow
                label={teamName(fixture.home, fixture.home_label)}
                value={pick.home}
                disabled={!canEdit || !resolved}
                onChange={(value) => onScore('home', value)}
            />
            <div className="border-t border-border/50" />
            <SlotRow
                label={teamName(fixture.away, fixture.away_label)}
                value={pick.away}
                disabled={!canEdit || !resolved}
                onChange={(value) => onScore('away', value)}
            />

            {resolved ? (
                <div className="mt-1 flex flex-col gap-1">
                    <span className="text-[0.65rem] font-semibold tracking-wide text-muted-foreground uppercase">
                        Advances
                    </span>
                    <ToggleGroup
                        type="single"
                        variant="outline"
                        size="sm"
                        disabled={!canEdit}
                        value={pick.advancing ? String(pick.advancing) : ''}
                        onValueChange={(value) =>
                            onAdvance(value ? Number(value) : null)
                        }
                        className="w-full"
                    >
                        <ToggleGroupItem
                            value={String(fixture.home!.id)}
                            className="flex-1 text-xs"
                        >
                            {fixture.home!.code ?? fixture.home!.name}
                        </ToggleGroupItem>
                        <ToggleGroupItem
                            value={String(fixture.away!.id)}
                            className="flex-1 text-xs"
                        >
                            {fixture.away!.code ?? fixture.away!.name}
                        </ToggleGroupItem>
                    </ToggleGroup>
                </div>
            ) : (
                <p className="mt-1 text-xs text-muted-foreground italic">
                    Pick the earlier rounds to reveal these teams.
                </p>
            )}
        </div>
    );
}

function SlotRow({
    label,
    value,
    disabled,
    onChange,
}: {
    label: string;
    value: string;
    disabled: boolean;
    onChange: (value: string) => void;
}) {
    return (
        <div className="flex items-center justify-between gap-2">
            <span className="truncate font-medium">{label}</span>
            <ScoreInput
                value={value}
                disabled={disabled}
                label={`${label} goals`}
                onChange={onChange}
            />
        </div>
    );
}

export default function Predict({
    game,
    groups,
    bracket,
    thirds,
}: PredictPageProps) {
    const canEdit = game.can_edit;
    const [step, setStep] = useState(0);
    const [saving, setSaving] = useState(false);
    const [groupScores, setGroupScores] = useState<GroupScores>(() =>
        buildGroupScores(groups),
    );
    const [picks, setPicks] = useState<KnockoutPicks>(() =>
        buildPicks(bracket),
    );

    // The server is authoritative: whenever it returns fresh props (after a save), Inertia
    // replaces the groups/bracket arrays, so resync the editable state during render to
    // reflect any cascade-invalidated picks. Local typing keeps the same prop identities,
    // so in-progress edits are never clobbered mid-step.
    const [synced, setSynced] = useState({ groups, bracket });

    if (synced.groups !== groups || synced.bracket !== bracket) {
        setSynced({ groups, bracket });
        setGroupScores(buildGroupScores(groups));
        setPicks(buildPicks(bracket));
    }

    const phasesByKey = useMemo(() => {
        const map: Record<string, PredictBracketPhase> = {};

        for (const phase of bracket) {
            map[phase.phase_key] = phase;
        }

        return map;
    }, [bracket]);

    const isLastStep = step === STEP_TITLES.length - 1;

    const persist = (
        url: string,
        predictions: Array<Record<string, number | null>>,
        onDone?: () => void,
    ): void => {
        setSaving(true);
        router.put(
            url,
            { predictions },
            {
                preserveScroll: true,
                preserveState: true,
                onSuccess: () => {
                    toast.success('Predictions saved');
                    onDone?.();
                },
                onError: () => toast.error('Could not save your predictions'),
                onFinish: () => setSaving(false),
            },
        );
    };

    const saveGroupStage = (next?: number): void => {
        const predictions = groups
            .flatMap((group) => group.fixtures)
            .map((fixture) => ({
                fixture,
                score: groupScores[fixture.fixture_id],
            }))
            .filter(
                ({ score }) => score && score.home !== '' && score.away !== '',
            )
            .map(({ fixture, score }) => ({
                fixture_id: fixture.fixture_id,
                home_goals: Number(score.home),
                away_goals: Number(score.away),
            }));

        persist(
            games.predict.group(game.slug).url,
            predictions,
            () => next !== undefined && setStep(next),
        );
    };

    const saveKnockout = (phaseKeys: string[], next?: number): void => {
        const predictions = phaseKeys
            .flatMap((key) => phasesByKey[key]?.fixtures ?? [])
            .map((fixture) => {
                const pick = picks[fixture.fixture_id] ?? {
                    home: '',
                    away: '',
                    advancing: null,
                };

                return {
                    fixture_id: fixture.fixture_id,
                    home_goals: pick.home === '' ? null : Number(pick.home),
                    away_goals: pick.away === '' ? null : Number(pick.away),
                    advancing_team_id: pick.advancing,
                };
            });

        persist(
            games.predict.knockout(game.slug).url,
            predictions,
            () => next !== undefined && setStep(next),
        );
    };

    const handleForward = (): void => {
        if (!canEdit) {
            if (!isLastStep) {
                setStep(step + 1);
            }

            return;
        }

        if (step === 0) {
            saveGroupStage(1);

            return;
        }

        const knockoutStep = KNOCKOUT_STEPS[step - 1];
        saveKnockout(knockoutStep.phaseKeys, isLastStep ? undefined : step + 1);
    };

    const setGroupScore = (
        fixtureId: number,
        side: 'home' | 'away',
        value: string,
    ): void => {
        setGroupScores((previous) => ({
            ...previous,
            [fixtureId]: {
                ...(previous[fixtureId] ?? { home: '', away: '' }),
                [side]: value,
            },
        }));
    };

    const setPick = (fixtureId: number, patch: Partial<KnockoutPick>): void => {
        setPicks((previous) => ({
            ...previous,
            [fixtureId]: {
                ...(previous[fixtureId] ?? {
                    home: '',
                    away: '',
                    advancing: null,
                }),
                ...patch,
            },
        }));
    };

    const dates = game.starts_on
        ? game.ends_on
            ? `${game.starts_on} – ${game.ends_on}`
            : game.starts_on
        : null;

    return (
        <>
            <Head title={`Predict — ${game.name}`} />
            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <header className="bg-pitch relative overflow-hidden rounded-2xl border border-primary/20 p-6">
                    <div className="pointer-events-none absolute -top-16 -right-10 size-56 rounded-full bg-accent/20 blur-3xl" />
                    <div className="relative flex flex-col gap-3">
                        <div className="flex flex-wrap items-center gap-3">
                            <Badge className="bg-brand-gradient border-0 text-primary-foreground capitalize shadow">
                                {game.status.replace('_', ' ')}
                            </Badge>
                            <Link
                                href={games.show(game.slug)}
                                className="text-sm text-muted-foreground underline-offset-4 hover:underline"
                            >
                                ← Back to tournament
                            </Link>
                        </div>
                        <h1 className="text-gradient-brand text-3xl font-black tracking-tight sm:text-4xl">
                            My Predictions
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            {game.name}
                        </p>
                        {dates && (
                            <span className="inline-flex items-center gap-2 text-sm text-muted-foreground">
                                <CalendarDays className="size-4" />
                                {dates}
                            </span>
                        )}
                    </div>
                </header>

                {!canEdit && (
                    <div className="flex items-center gap-3 rounded-xl border border-amber-500/40 bg-amber-500/10 px-4 py-3 text-sm text-amber-700 dark:text-amber-400">
                        <Lock className="size-4 shrink-0" />
                        <span>
                            Predictions are locked — the tournament has started,
                            so what is done is done. You can still review your
                            picks below.
                        </span>
                    </div>
                )}

                <ol className="flex flex-wrap gap-2">
                    {STEP_TITLES.map((title, index) => (
                        <li key={title}>
                            <button
                                type="button"
                                onClick={() => setStep(index)}
                                className={cn(
                                    'rounded-full px-3 py-1.5 text-xs font-semibold transition',
                                    index === step
                                        ? 'bg-brand-gradient text-primary-foreground shadow'
                                        : 'bg-secondary text-secondary-foreground hover:bg-secondary/70',
                                )}
                            >
                                {index + 1}. {title}
                            </button>
                        </li>
                    ))}
                </ol>

                <section className="flex flex-1 flex-col gap-4">
                    {step === 0 ? (
                        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                            {groups.map((group) => (
                                <GroupCard
                                    key={group.name}
                                    group={group}
                                    scores={groupScores}
                                    canEdit={canEdit}
                                    onChange={setGroupScore}
                                />
                            ))}
                        </div>
                    ) : (
                        <>
                            {step === 1 && <ThirdsPanel thirds={thirds} />}
                            <KnockoutStep
                                phaseKeys={KNOCKOUT_STEPS[step - 1].phaseKeys}
                                phasesByKey={phasesByKey}
                                picks={picks}
                                canEdit={canEdit}
                                onScore={(fixtureId, side, value) =>
                                    setPick(fixtureId, { [side]: value })
                                }
                                onAdvance={(fixtureId, teamId) =>
                                    setPick(fixtureId, { advancing: teamId })
                                }
                            />
                        </>
                    )}
                </section>

                <footer className="sticky bottom-0 flex items-center justify-between gap-3 border-t border-border/60 bg-background/80 py-4 backdrop-blur">
                    <Button
                        type="button"
                        variant="outline"
                        disabled={step === 0}
                        onClick={() => setStep(Math.max(0, step - 1))}
                    >
                        <ChevronLeft className="size-4" /> Back
                    </Button>

                    {canEdit ? (
                        <Button
                            type="button"
                            disabled={saving}
                            onClick={handleForward}
                        >
                            {isLastStep ? (
                                <>
                                    <Save className="size-4" /> Save predictions
                                </>
                            ) : (
                                <>
                                    Save &amp; continue{' '}
                                    <ChevronRight className="size-4" />
                                </>
                            )}
                        </Button>
                    ) : (
                        !isLastStep && (
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setStep(step + 1)}
                            >
                                Next <ChevronRight className="size-4" />
                            </Button>
                        )
                    )}
                </footer>
            </div>
        </>
    );
}

function ThirdsPanel({ thirds }: { thirds: ThirdRanking[] | null }) {
    return (
        <div className="card-elevated flex flex-col gap-2 rounded-xl p-4">
            <h3 className="text-xs font-bold tracking-wide text-primary uppercase">
                Best third-placed teams
            </h3>
            {thirds === null ? (
                <p className="text-sm text-muted-foreground">
                    Predict every group fully to see which eight third-placed
                    teams qualify for the Round of 32.
                </p>
            ) : (
                <ol className="flex flex-wrap gap-2">
                    {thirds.map((entry) => (
                        <li
                            key={entry.rank}
                            className="inline-flex items-center gap-1.5 rounded-full bg-secondary px-3 py-1 text-xs font-medium text-secondary-foreground"
                        >
                            <span className="text-muted-foreground">
                                {entry.rank}.
                            </span>
                            {entry.team?.name ?? 'TBD'}
                        </li>
                    ))}
                </ol>
            )}
        </div>
    );
}

function KnockoutStep({
    phaseKeys,
    phasesByKey,
    picks,
    canEdit,
    onScore,
    onAdvance,
}: {
    phaseKeys: string[];
    phasesByKey: Record<string, PredictBracketPhase>;
    picks: KnockoutPicks;
    canEdit: boolean;
    onScore: (fixtureId: number, side: 'home' | 'away', value: string) => void;
    onAdvance: (fixtureId: number, teamId: number | null) => void;
}) {
    return (
        <div className="flex flex-col gap-8">
            {phaseKeys.map((key) => {
                const phase = phasesByKey[key];

                if (!phase) {
                    return null;
                }

                return (
                    <div key={key} className="flex flex-col gap-3">
                        <h2 className="text-xs font-bold tracking-wide text-primary uppercase">
                            {phase.phase_name}
                        </h2>
                        <div className="flex flex-wrap gap-3">
                            {phase.fixtures.map((fixture) => (
                                <KnockoutCard
                                    key={fixture.fixture_id}
                                    fixture={fixture}
                                    pick={
                                        picks[fixture.fixture_id] ?? {
                                            home: '',
                                            away: '',
                                            advancing: null,
                                        }
                                    }
                                    canEdit={canEdit}
                                    isFinal={fixture.phase_key === 'final'}
                                    onScore={(side, value) =>
                                        onScore(fixture.fixture_id, side, value)
                                    }
                                    onAdvance={(teamId) =>
                                        onAdvance(fixture.fixture_id, teamId)
                                    }
                                />
                            ))}
                        </div>
                    </div>
                );
            })}
        </div>
    );
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Tournaments', href: games.index() },
];

Predict.layout = { breadcrumbs };
