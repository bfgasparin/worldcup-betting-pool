import { Head, Link, router } from '@inertiajs/react';
import {
    CalendarDays,
    Check,
    ChevronLeft,
    ChevronRight,
    CircleAlert,
    Download,
    Info,
    ListFilter,
    Loader2,
    Lock,
} from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
import { AllSetBanner } from '@/components/all-set-banner';
import { GroupBadge, TeamChip } from '@/components/fixtures';
import { Flag } from '@/components/flag';
import { ImportPredictionsDialog } from '@/components/import-predictions-dialog';
import { MatchdayChip, MatchdayStripe } from '@/components/matchday-marker';
import { PoolIdentity } from '@/components/pool-identity';
import { PredictionCompleteDialog } from '@/components/prediction-complete-dialog';
import { ScoreStepper } from '@/components/score-stepper';
import { StandingsTable } from '@/components/standings-table';
import { TieResolutionPanel } from '@/components/tie-resolution-panel';
import { Button } from '@/components/ui/button';
import { chipVariants } from '@/components/ui/chip';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { poolTitle } from '@/lib/pool-title';
import { scoringRules } from '@/lib/scoring';
import { cn } from '@/lib/utils';
import pools from '@/routes/pools';
import type { BreadcrumbItem } from '@/types/navigation';
import type {
    GroupTeam,
    KnockoutPredictionFixture,
    PredictBracketPhase,
    PredictGroup,
    PredictGroupFixture,
    PredictionWindowStatus,
    PredictPageProps,
    TeamRef,
    ThirdRanking,
} from '@/types/pools';

type ScorePair = { home: string; away: string };
type GroupScores = Record<number, ScorePair>;
type KnockoutPick = { home: string; away: string; advancing: number | null };
type KnockoutPicks = Record<number, KnockoutPick>;
type SaveStatusValue = 'idle' | 'saving' | 'saved' | 'error';

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

const AUTOSAVE_DELAY = 700;

/** The knockout phase keys that make up a wizard step (empty for the group step). */
function stepPhaseKeys(step: number): string[] {
    return step === 0 ? [] : KNOCKOUT_STEPS[step - 1].phaseKeys;
}

/**
 * Whether a wizard step currently accepts edits: the group step rides the pool-level lock; a
 * knockout step is editable while any of its rounds is open.
 */
function isStepEditable(
    step: number,
    groupCanEdit: boolean,
    phasesByKey: Record<string, PredictBracketPhase>,
): boolean {
    if (step === 0) {
        return groupCanEdit;
    }

    return stepPhaseKeys(step).some(
        (key) => phasesByKey[key]?.window === 'open',
    );
}

/** Whether a group fixture's scoreline is fully entered (reads the live local state). */
function isGroupScoreDone(score: ScorePair | undefined): boolean {
    return score !== undefined && score.home !== '' && score.away !== '';
}

/**
 * Whether a knockout pick is complete: the advancing team must be set. It is derived from a decisive
 * score, or chosen by the player on a draw (penalties), so a draw with a scoreline but no advancing
 * pick is NOT done — the player still has to say who goes through. Holds for upfront and phased.
 */
function isKnockoutPickDone(pick: KnockoutPick | undefined): boolean {
    return pick !== undefined && pick.advancing !== null;
}

/**
 * Whether a group still has an unresolved within-group tie to order — outstanding group-stage work
 * even when every fixture is scored (ties only surface on a complete group; upfront pools only).
 * Mirrors GroupCard's panel, which only renders clusters of more than one tied team.
 */
function groupTieUnresolved(group: PredictGroup): boolean {
    return group.tied_clusters.some(
        (cluster) => !cluster.resolved && cluster.team_ids.length > 1,
    );
}

/**
 * How many items in a step still need the player's attention — counting only ones they can actually
 * act on: the group step counts unpredicted fixtures plus unresolved ties (each blocks the bracket)
 * while it is editable; a knockout step counts only its OPEN rounds and only fixtures whose
 * participants are known (so an upfront bracket never points at an unresolved slot). A step spanning
 * two rounds (Third Place & Final) sums both.
 */
function stepRemaining(
    step: number,
    groups: PredictGroup[],
    phasesByKey: Record<string, PredictBracketPhase>,
    groupScores: GroupScores,
    picks: KnockoutPicks,
    canEdit: boolean,
): number {
    if (step === 0) {
        if (!canEdit) {
            return 0;
        }

        const unpredicted = groups
            .flatMap((group) => group.fixtures)
            .filter(
                (fixture) => !isGroupScoreDone(groupScores[fixture.fixture_id]),
            ).length;
        const ties = groups.filter(groupTieUnresolved).length;

        return unpredicted + ties;
    }

    return stepPhaseKeys(step)
        .map((key) => phasesByKey[key])
        .filter(
            (phase): phase is PredictBracketPhase => phase?.window === 'open',
        )
        .flatMap((phase) => phase.fixtures)
        .filter((fixture) => fixture.home !== null && fixture.away !== null)
        .filter((fixture) => !isKnockoutPickDone(picks[fixture.fixture_id]))
        .length;
}

/** The first editable step that still has unpredicted matches; falls back to the group step. */
function computeInitialStep(
    groups: PredictGroup[],
    phasesByKey: Record<string, PredictBracketPhase>,
    groupScores: GroupScores,
    picks: KnockoutPicks,
    canEdit: boolean,
): number {
    for (let step = 0; step < STEP_TITLES.length; step++) {
        if (
            isStepEditable(step, canEdit, phasesByKey) &&
            stepRemaining(
                step,
                groups,
                phasesByKey,
                groupScores,
                picks,
                canEdit,
            ) > 0
        ) {
            return step;
        }
    }

    return 0;
}

/** Shown in place of a step's cards when the "needs prediction" filter leaves nothing to fill. */
function StepClearNote() {
    return (
        <p className="card-elevated rounded-3xl px-5 py-10 text-center text-sm text-muted-foreground">
            Nothing left to predict here — you&apos;re all set for this step.
        </p>
    );
}

/**
 * The round-weight multiplier to apply to the scoreline tiers shown in the legend for a knockout
 * step (phased bracket). Group steps and pools without multipliers use ×1; a step spanning two
 * rounds (Third Place & Final) shows the larger one.
 */
function roundMultiplier(
    step: number,
    config: Record<string, Record<string, number>>,
): number {
    if (step === 0) {
        return 1;
    }

    const multipliers = (
        config.knockout as unknown as {
            round_multipliers?: Record<string, number>;
        }
    )?.round_multipliers;

    if (!multipliers) {
        return 1;
    }

    return Math.max(...stepPhaseKeys(step).map((key) => multipliers[key] ?? 1));
}

function teamName(
    team: TeamRef | null,
    fallback: string | null = null,
): string {
    return team?.name ?? fallback ?? 'TBD';
}

function teamShort(
    team: TeamRef | null,
    fallback: string | null = null,
): string {
    return team?.code ?? team?.name ?? fallback ?? 'TBD';
}

function toScore(value: number | null): string {
    return value === null || value === undefined ? '' : String(value);
}

/**
 * The winning team id for a decisive knockout score, or null when the score is a draw or
 * incomplete. A draw needs a manual pick (penalties decide), so it resolves to null here.
 */
function deriveAdvancing(
    home: string,
    away: string,
    fixture: KnockoutPredictionFixture,
): number | null {
    if (
        home === '' ||
        away === '' ||
        fixture.home === null ||
        fixture.away === null
    ) {
        return null;
    }

    const homeGoals = Number(home);
    const awayGoals = Number(away);

    if (homeGoals === awayGoals) {
        return null;
    }

    return homeGoals > awayGoals ? fixture.home.id : fixture.away.id;
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

/**
 * Mirror the server's cascade invalidation on the client: clear only the local picks whose
 * advancing team is no longer one of the fixture's resolved teams. Everything else (valid,
 * possibly in-progress edits) is left untouched.
 */
function reconcilePicks(
    picks: KnockoutPicks,
    bracket: PredictBracketPhase[],
): KnockoutPicks {
    let changed = false;
    const next = { ...picks };

    for (const phase of bracket) {
        for (const fixture of phase.fixtures) {
            const pick = next[fixture.fixture_id];

            if (!pick || pick.advancing === null) {
                continue;
            }

            const resolvedIds = [fixture.home?.id, fixture.away?.id];

            if (!resolvedIds.includes(pick.advancing)) {
                next[fixture.fixture_id] = {
                    home: '',
                    away: '',
                    advancing: null,
                };
                changed = true;
            }
        }
    }

    return changed ? next : picks;
}

/**
 * Scoring rules for the current step, read from the pool's scoring_config. Every rule
 * configured for the current phase is shown, sorted by points descending. Rendered as subtle
 * pills on the dark header.
 */
function ScoringLegend({
    config,
    step,
}: {
    config: Record<string, Record<string, number>>;
    step: number;
}) {
    const rules = scoringRules(
        config,
        step === 0 ? 'group' : 'knockout',
        roundMultiplier(step, config),
    );

    return (
        <div className="flex flex-wrap gap-2">
            {rules.map((rule) => (
                <span
                    key={rule.label}
                    className="inline-flex items-center gap-1.5 rounded-full bg-muted px-3 py-1 text-xs font-semibold text-muted-foreground"
                >
                    {rule.label}
                    <b className="font-display text-primary">+{rule.points}</b>
                </span>
            ))}
        </div>
    );
}

function GroupCard({
    group,
    scores,
    canEdit,
    onChange,
    onCommit,
    orderingUrl,
    fixtureFilter,
}: {
    group: PredictGroup;
    scores: GroupScores;
    canEdit: boolean;
    onChange: (fixtureId: number, side: 'home' | 'away', value: string) => void;
    onCommit: () => void;
    orderingUrl: string;
    /** When set, only fixtures it keeps are shown (the "needs my prediction" filter). */
    fixtureFilter?: (fixture: PredictGroupFixture) => boolean;
}) {
    const fixtures = fixtureFilter
        ? group.fixtures.filter(fixtureFilter)
        : group.fixtures;
    const tiedClusters = group.tied_clusters
        .map((cluster) => ({
            teams: cluster.team_ids
                .map((id) => group.teams.find((team) => team.id === id))
                .filter((team): team is GroupTeam => Boolean(team)),
            resolved: cluster.resolved,
        }))
        .filter((cluster) => cluster.teams.length > 1);

    return (
        <div className="card-elevated rounded-3xl p-5">
            <div className="mb-3 flex items-center justify-between gap-2">
                <div className="flex min-w-0 items-center gap-2.5">
                    <GroupBadge name={group.name} />
                    <h3 className="font-display text-base font-semibold whitespace-nowrap">
                        Group {group.name}
                    </h3>
                </div>
                <span className="text-[11px] font-bold tracking-wide text-muted-foreground uppercase">
                    {fixtures.length} matches
                </span>
            </div>

            <div className="flex flex-wrap gap-1.5 border-b border-border pb-3">
                {group.teams.map((team) => (
                    <TeamChip key={team.id} team={team} />
                ))}
            </div>

            <div className="mt-3 flex flex-col gap-2.5">
                {fixtures.map((fixture: PredictGroupFixture) => {
                    const score = scores[fixture.fixture_id] ?? {
                        home: '',
                        away: '',
                    };

                    return (
                        <div
                            key={fixture.fixture_id}
                            className="relative flex items-center gap-2 pl-3"
                        >
                            <MatchdayStripe
                                matchdayKey={fixture.matchday_key}
                            />
                            <MatchdayChip matchdayKey={fixture.matchday_key} />
                            <div className="grid flex-1 grid-cols-[1fr_auto_1fr] items-center gap-2">
                                <span className="flex min-w-0 items-center justify-end gap-1.5 text-sm font-bold">
                                    <span className="truncate">
                                        {teamShort(fixture.home)}
                                    </span>
                                    <Flag
                                        team={fixture.home}
                                        className="h-4 w-6"
                                    />
                                </span>
                                <div className="flex items-center gap-1.5">
                                    <ScoreStepper
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
                                        onCommit={onCommit}
                                    />
                                    <span className="font-display text-muted-foreground">
                                        –
                                    </span>
                                    <ScoreStepper
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
                                        onCommit={onCommit}
                                    />
                                </div>
                                <span className="flex min-w-0 items-center gap-1.5 text-sm font-bold">
                                    <Flag
                                        team={fixture.away}
                                        className="h-4 w-6"
                                    />
                                    <span className="truncate">
                                        {teamShort(fixture.away)}
                                    </span>
                                </span>
                            </div>
                        </div>
                    );
                })}
            </div>

            <div className="mt-4 border-t border-border pt-2">
                <StandingsTable standings={group.standings} />
                {group.standings.some((row) => row.tied) && (
                    <div className="mt-2 flex items-start gap-2 rounded-xl border border-border bg-muted/40 px-3 py-2 text-[12px] text-muted-foreground">
                        <Info className="mt-0.5 size-3.5 shrink-0" />
                        <span>
                            The teams marked = are level on every tiebreaker, so
                            this projected order is only a guess — it may not
                            match the real standings, and it does not affect
                            this pool.
                        </span>
                    </div>
                )}
            </div>

            {tiedClusters.length > 0 && (
                <div className="mt-3">
                    <TieResolutionPanel
                        title="Resolve the tie"
                        description="These teams are level on every tiebreaker. Drag them into the order you want them to finish — your bracket can't fill until you do."
                        clusters={tiedClusters}
                        editable={canEdit}
                        url={orderingUrl}
                        payloadFor={(orderedTeamIds) => ({
                            scope: 'within-group',
                            group: group.name,
                            ordered_team_ids: orderedTeamIds,
                        })}
                    />
                </div>
            )}
        </div>
    );
}

function SlotRow({
    label,
    team,
    value,
    disabled,
    onChange,
    onCommit,
}: {
    label: string;
    team: TeamRef | null;
    value: string;
    disabled: boolean;
    onChange: (value: string) => void;
    onCommit: () => void;
}) {
    return (
        <div className="flex items-center justify-between gap-2">
            <span className="flex min-w-0 items-center gap-1.5 font-medium">
                {team && <Flag team={team} />}
                <span className="truncate">{label}</span>
            </span>
            <ScoreStepper
                value={value}
                disabled={disabled}
                label={`${label} goals`}
                onChange={onChange}
                onCommit={onCommit}
            />
        </div>
    );
}

function KnockoutCard({
    fixture,
    pick,
    canEdit,
    isFinal,
    onChange,
    onCommit,
}: {
    fixture: KnockoutPredictionFixture;
    pick: KnockoutPick;
    canEdit: boolean;
    isFinal: boolean;
    onChange: (patch: Partial<KnockoutPick>, immediate?: boolean) => void;
    onCommit: () => void;
}) {
    const resolved = fixture.home !== null && fixture.away !== null;
    const bothScored = pick.home !== '' && pick.away !== '';
    const isDraw = bothScored && Number(pick.home) === Number(pick.away);
    const decisiveWinnerId =
        bothScored && !isDraw
            ? deriveAdvancing(pick.home, pick.away, fixture)
            : null;
    const winnerTeam =
        decisiveWinnerId === fixture.home?.id
            ? fixture.home
            : decisiveWinnerId === fixture.away?.id
              ? fixture.away
              : null;

    const handleScore = (side: 'home' | 'away', value: string): void => {
        const next = { ...pick, [side]: value };

        // A draw keeps any existing manual pick; a decisive or incomplete score derives it.
        if (
            next.home !== '' &&
            next.away !== '' &&
            Number(next.home) === Number(next.away)
        ) {
            onChange({ [side]: value });
        } else {
            onChange({
                [side]: value,
                advancing: deriveAdvancing(next.home, next.away, fixture),
            });
        }
    };

    return (
        <div
            className={cn(
                'flex w-72 flex-col gap-2.5 rounded-2xl p-4 text-sm',
                isFinal
                    ? 'shadow-glow-accent border border-accent/40 bg-card'
                    : 'card-elevated',
            )}
        >
            <div className="mb-1 flex items-center justify-between gap-2">
                <span className="font-display text-xs font-semibold text-muted-foreground">
                    Match {fixture.match_number}
                </span>
                {isFinal && (
                    <span className="font-display text-[11px] font-bold tracking-wide text-amber uppercase">
                        Final
                    </span>
                )}
            </div>
            <SlotRow
                label={teamName(fixture.home, fixture.home_label)}
                team={fixture.home}
                value={pick.home}
                disabled={!canEdit || !resolved}
                onChange={(value) => handleScore('home', value)}
                onCommit={onCommit}
            />
            <div className="border-t border-border" />
            <SlotRow
                label={teamName(fixture.away, fixture.away_label)}
                team={fixture.away}
                value={pick.away}
                disabled={!canEdit || !resolved}
                onChange={(value) => handleScore('away', value)}
                onCommit={onCommit}
            />

            {!resolved ? (
                <p className="mt-1 text-xs text-muted-foreground italic">
                    Pick the earlier rounds to reveal these teams.
                </p>
            ) : !bothScored ? (
                <p className="mt-1 text-xs text-muted-foreground italic">
                    Enter the score to set who advances.
                </p>
            ) : isDraw ? (
                <div className="mt-1 flex flex-col gap-1">
                    <span className="text-[0.65rem] font-semibold tracking-wide text-muted-foreground uppercase">
                        Extra time / penalties — who advances?
                    </span>
                    <ToggleGroup
                        type="single"
                        variant="outline"
                        size="sm"
                        disabled={!canEdit}
                        value={pick.advancing ? String(pick.advancing) : ''}
                        onValueChange={(value) =>
                            onChange(
                                { advancing: value ? Number(value) : null },
                                true,
                            )
                        }
                        className="w-full"
                    >
                        <ToggleGroupItem
                            value={String(fixture.home!.id)}
                            className="flex-1 gap-1 text-xs"
                        >
                            <Flag team={fixture.home} />
                            {fixture.home!.code ?? fixture.home!.name}
                        </ToggleGroupItem>
                        <ToggleGroupItem
                            value={String(fixture.away!.id)}
                            className="flex-1 gap-1 text-xs"
                        >
                            <Flag team={fixture.away} />
                            {fixture.away!.code ?? fixture.away!.name}
                        </ToggleGroupItem>
                    </ToggleGroup>
                </div>
            ) : (
                <div className="mt-1 flex flex-col gap-1">
                    <span className="text-[0.65rem] font-semibold tracking-wide text-muted-foreground uppercase">
                        Advances
                    </span>
                    <span className="inline-flex items-center gap-1.5 text-xs font-semibold text-pitch-deep dark:text-primary">
                        <Flag team={winnerTeam} />
                        {winnerTeam?.code ?? winnerTeam?.name}
                    </span>
                </div>
            )}
        </div>
    );
}

function SaveStatus({ status }: { status: SaveStatusValue }) {
    if (status === 'idle') {
        return null;
    }

    if (status === 'saving') {
        return (
            <span className="inline-flex items-center gap-1.5 text-xs text-muted-foreground">
                <Loader2 className="size-3.5 animate-spin" /> Saving…
            </span>
        );
    }

    if (status === 'saved') {
        return (
            <span className="inline-flex items-center gap-1.5 text-xs text-muted-foreground">
                <Check className="size-3.5 text-primary" /> All changes saved
            </span>
        );
    }

    return (
        <span className="inline-flex items-center gap-1.5 text-xs text-destructive">
            <CircleAlert className="size-3.5" /> Couldn't save — check your
            connection
        </span>
    );
}

export default function Predict({
    pool,
    groups,
    bracket,
    thirds,
    thirds_tie: thirdsTie,
    import_sources: importSources,
    should_suggest_import: shouldSuggestImport,
    show_tie_note: showTieNote,
    completion,
}: PredictPageProps) {
    const canEdit = pool.can_edit;
    const isUpfront = pool.scoring_strategy === 'upfront-bracket';
    const [saveStatus, setSaveStatus] = useState<SaveStatusValue>('idle');
    const [importOpen, setImportOpen] = useState(false);
    // Celebrate the moment the last open-window prediction lands. A ref of the previous value tells
    // "just completed now" apart from "arrived already complete": arriving complete (a later visit)
    // shows only the calm banner, never the modal. Re-fires on false→true→false→true (an upfront
    // cascade can wipe downstream picks, a phased round can reopen) since the ref tracks each render.
    const [celebrateOpen, setCelebrateOpen] = useState(false);
    const wasCompleteRef = useRef(completion.is_complete);
    // A session-only dismissal of the nudge. The server prop drives whether it returns next visit,
    // so skipping just hides it now and it reappears until the user has a prediction (or imports).
    const [suggestionDismissed, setSuggestionDismissed] = useState(false);
    const [groupScores, setGroupScores] = useState<GroupScores>(() =>
        buildGroupScores(groups),
    );
    const [picks, setPicks] = useState<KnockoutPicks>(() =>
        buildPicks(bracket),
    );

    const phasesByKey = useMemo(() => {
        const map: Record<string, PredictBracketPhase> = {};

        for (const phase of bracket) {
            map[phase.phase_key] = phase;
        }

        return map;
    }, [bracket]);

    // Open the wizard where the work is: the first editable step with unpredicted matches (a
    // half-filled group, or a freshly-opened phased round). Computed once on mount; auto-saves keep
    // the component mounted (preserveState) so it isn't recomputed, and a fresh visit re-lands here.
    const [step, setStep] = useState(() =>
        computeInitialStep(groups, phasesByKey, groupScores, picks, canEdit),
    );
    // "Needs my prediction": hide matches already filled in, so only outstanding work shows.
    const [onlyRemaining, setOnlyRemaining] = useState(false);

    // Refs mirror the latest editable state and context so the debounced/queued auto-save
    // always reads current values, even from a timer scheduled in an earlier render.
    const groupScoresRef = useRef(groupScores);
    const picksRef = useRef(picks);
    const stepRef = useRef(step);
    const groupsRef = useRef(groups);
    const phasesByKeyRef = useRef(phasesByKey);
    const dirtyRef = useRef(false);
    const savingRef = useRef(false);
    const timerRef = useRef<ReturnType<typeof setTimeout> | null>(null);

    // When the server returns a fresh bracket (after a save), reflect any cascade
    // invalidation without clobbering valid in-progress edits.
    const [syncedBracket, setSyncedBracket] = useState(bracket);

    if (syncedBracket !== bracket) {
        setSyncedBracket(bracket);
        setPicks((previous) => reconcilePicks(previous, bracket));
    }

    // Keep the auto-save refs in step with the latest committed state and props, so a
    // queued/debounced flush always reads current values (refs are only touched outside
    // render). The setters below also write them synchronously for immediate saves.
    useEffect(() => {
        groupScoresRef.current = groupScores;
    }, [groupScores]);
    useEffect(() => {
        picksRef.current = picks;
    }, [picks]);
    useEffect(() => {
        stepRef.current = step;
    }, [step]);
    useEffect(() => {
        groupsRef.current = groups;
    }, [groups]);
    useEffect(() => {
        phasesByKeyRef.current = phasesByKey;
    }, [phasesByKey]);

    // Pop the celebration only on the incomplete→complete edge (after an auto-save returns fresh
    // props), so it fires when the work is finished but not on a revisit that arrives complete.
    useEffect(() => {
        if (completion.is_complete && !wasCompleteRef.current) {
            setCelebrateOpen(true);
        }

        wasCompleteRef.current = completion.is_complete;
    }, [completion.is_complete]);

    const isLastStep = step === STEP_TITLES.length - 1;
    const currentStepEditable = isStepEditable(step, canEdit, phasesByKey);
    // Whether anything is still predictable now, and whether any knockout round is waiting on
    // results to open — phased pools shouldn't show the "all locked" banner while rounds are pending.
    const anyOpen = canEdit || bracket.some((phase) => phase.window === 'open');
    const anyPending = bracket.some((phase) => phase.window === 'pending');

    // Outstanding matches per step (drives the nav badges) and the next step worth visiting.
    const stepRemainingCounts = useMemo(
        () =>
            STEP_TITLES.map((_, index) =>
                stepRemaining(
                    index,
                    groups,
                    phasesByKey,
                    groupScores,
                    picks,
                    canEdit,
                ),
            ),
        [groups, phasesByKey, groupScores, picks, canEdit],
    );
    const nextStep = useMemo(() => {
        const total = STEP_TITLES.length;

        // Search the other steps in wrap-around order; never points back at the current step.
        for (let offset = 1; offset < total; offset++) {
            const index = (step + offset) % total;

            if (
                isStepEditable(index, canEdit, phasesByKey) &&
                stepRemainingCounts[index] > 0
            ) {
                return index;
            }
        }

        return null;
    }, [step, canEdit, phasesByKey, stepRemainingCounts]);

    // Predicates for the "needs my prediction" filter: keep only matches still awaiting a pick (and,
    // for knockout, only ones the player can act on now — participants known).
    const groupFixtureRemaining = (fixture: PredictGroupFixture): boolean =>
        !isGroupScoreDone(groupScores[fixture.fixture_id]);
    const knockoutFixtureRemaining = (
        fixture: KnockoutPredictionFixture,
    ): boolean =>
        fixture.home !== null &&
        fixture.away !== null &&
        !isKnockoutPickDone(picks[fixture.fixture_id]);
    // A group is outstanding while it has matches left to score OR an unresolved tie to order — the
    // tie panel lives inside the card, so dropping a complete-but-tied group would strand it.
    const visiblePredictGroups = onlyRemaining
        ? groups.filter(
              (group) =>
                  group.fixtures.some(groupFixtureRemaining) ||
                  groupTieUnresolved(group),
          )
        : groups;
    // The straddling-thirds tie is its own panel below the grid; keep the "all set" note from
    // claiming the group step is done while it's still unresolved.
    const thirdsTieUnresolved =
        isUpfront &&
        thirdsTie !== null &&
        thirdsTie.teams.length > 0 &&
        !thirdsTie.resolved;

    const canImport = importSources.length > 0;
    const showImportSuggestion =
        shouldSuggestImport && canImport && !suggestionDismissed;

    function buildStepPayload(): {
        url: string;
        predictions: Array<Record<string, number | null>>;
    } {
        if (stepRef.current === 0) {
            const predictions = groupsRef.current
                .flatMap((group) => group.fixtures)
                .map((fixture) => ({
                    fixture,
                    score: groupScoresRef.current[fixture.fixture_id],
                }))
                .filter(
                    ({ score }) =>
                        score && score.home !== '' && score.away !== '',
                )
                .map(({ fixture, score }) => ({
                    fixture_id: fixture.fixture_id,
                    home_goals: Number(score.home),
                    away_goals: Number(score.away),
                }));

            return { url: pools.predict.group(pool.slug).url, predictions };
        }

        // Only submit rounds that are actually open — a step can span an open and a locked round
        // (e.g. the Final is open while the Third-place play-off has already kicked off).
        const phaseKeys = KNOCKOUT_STEPS[stepRef.current - 1].phaseKeys.filter(
            (key) => phasesByKeyRef.current[key]?.window === 'open',
        );
        const predictions = phaseKeys
            .flatMap((key) => phasesByKeyRef.current[key]?.fixtures ?? [])
            .map((fixture) => {
                const pick = picksRef.current[fixture.fixture_id] ?? {
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

        return { url: pools.predict.knockout(pool.slug).url, predictions };
    }

    function flush(): void {
        if (timerRef.current !== null) {
            clearTimeout(timerRef.current);
            timerRef.current = null;
        }

        if (
            !isStepEditable(stepRef.current, canEdit, phasesByKeyRef.current) ||
            savingRef.current
        ) {
            return;
        }

        const { url, predictions } = buildStepPayload();

        if (predictions.length === 0) {
            dirtyRef.current = false;

            return;
        }

        savingRef.current = true;
        dirtyRef.current = false;
        setSaveStatus('saving');

        router.put(
            url,
            { predictions },
            {
                preserveScroll: true,
                preserveState: true,
                onSuccess: () => setSaveStatus('saved'),
                onError: () => setSaveStatus('error'),
                onFinish: () => {
                    savingRef.current = false;

                    if (dirtyRef.current) {
                        scheduleFlush(0);
                    }
                },
            },
        );
    }

    function scheduleFlush(delay: number = AUTOSAVE_DELAY): void {
        if (!isStepEditable(stepRef.current, canEdit, phasesByKeyRef.current)) {
            return;
        }

        if (timerRef.current !== null) {
            clearTimeout(timerRef.current);
        }

        timerRef.current = setTimeout(() => {
            timerRef.current = null;
            flush();
        }, delay);
    }

    function markDirty(): void {
        if (!isStepEditable(stepRef.current, canEdit, phasesByKeyRef.current)) {
            return;
        }

        dirtyRef.current = true;
        scheduleFlush();
    }

    // Drop any queued auto-save before an import so a stale debounced PUT can't race the overwrite.
    function cancelPendingSave(): void {
        if (timerRef.current !== null) {
            clearTimeout(timerRef.current);
            timerRef.current = null;
        }

        dirtyRef.current = false;
    }

    useEffect(() => {
        return () => {
            if (timerRef.current !== null) {
                clearTimeout(timerRef.current);
            }
        };
    }, []);

    const updateGroupScore = (
        fixtureId: number,
        side: 'home' | 'away',
        value: string,
    ): void => {
        const next: GroupScores = {
            ...groupScoresRef.current,
            [fixtureId]: {
                ...(groupScoresRef.current[fixtureId] ?? {
                    home: '',
                    away: '',
                }),
                [side]: value,
            },
        };

        groupScoresRef.current = next;
        setGroupScores(next);
        markDirty();
    };

    const updatePick = (
        fixtureId: number,
        patch: Partial<KnockoutPick>,
        immediate = false,
    ): void => {
        const next: KnockoutPicks = {
            ...picksRef.current,
            [fixtureId]: {
                ...(picksRef.current[fixtureId] ?? {
                    home: '',
                    away: '',
                    advancing: null,
                }),
                ...patch,
            },
        };

        picksRef.current = next;
        setPicks(next);

        if (immediate) {
            flush();
        } else {
            markDirty();
        }
    };

    const goToStep = (target: number): void => {
        flush();
        setStep(target);
    };

    // Each tie panel saves its order to this endpoint (a discrete commit, separate from the
    // debounced score auto-save); the server re-cascades and returns the bracket with slots filled.
    const orderingUrl = pools.predict.ordering(pool.slug).url;

    const dates = pool.starts_on
        ? pool.ends_on
            ? `${pool.starts_on} – ${pool.ends_on}`
            : pool.starts_on
        : null;

    return (
        <>
            <Head title={poolTitle(pool.source, pool.name, 'Predict')} />
            <div className="flex h-full flex-1 flex-col gap-6 p-4 sm:p-6 lg:p-8">
                <header className="hero relative overflow-hidden rounded-3xl border border-border p-6 sm:p-8">
                    <div className="hero-lines" />
                    <div className="relative flex flex-col gap-3">
                        <div className="flex flex-wrap items-center gap-3">
                            <span className="inline-flex items-center rounded-full bg-muted px-2.5 py-0.5 text-xs font-semibold text-muted-foreground capitalize">
                                {pool.status.replace('_', ' ')}
                            </span>
                            <Link
                                href={pools.show(pool.slug)}
                                className="text-sm text-muted-foreground underline-offset-4 hover:text-foreground hover:underline"
                            >
                                ← Back to pool
                            </Link>
                            {canImport && (
                                <button
                                    type="button"
                                    onClick={() => setImportOpen(true)}
                                    className="inline-flex items-center gap-1.5 text-sm text-muted-foreground underline-offset-4 hover:text-foreground hover:underline"
                                >
                                    <Download className="size-4" />
                                    Import from another pool
                                </button>
                            )}
                        </div>
                        <h1 className="text-3xl font-semibold tracking-tight text-foreground sm:text-4xl">
                            My Predictions
                        </h1>
                        <PoolIdentity
                            source={pool.source}
                            name={pool.name}
                            scoringLabel={pool.scoring_label}
                            accent={pool.accent}
                        />
                        {dates && (
                            <span className="inline-flex items-center gap-2 text-sm text-muted-foreground">
                                <CalendarDays className="size-4" />
                                {dates}
                            </span>
                        )}
                        <div className="mt-1">
                            <ScoringLegend
                                config={pool.scoring_config}
                                step={step}
                            />
                        </div>
                    </div>
                </header>

                {!anyOpen && !anyPending && (
                    <div className="flex items-center gap-3 rounded-2xl border border-accent/40 bg-accent/10 px-4 py-3 text-sm text-amber-700 dark:text-amber-300">
                        <Lock className="size-4 shrink-0" />
                        <span>
                            Predictions are locked — the tournament has started,
                            so what is done is done. You can still review your
                            picks below.
                        </span>
                    </div>
                )}

                {completion.is_complete && (
                    <AllSetBanner windows={completion.open_windows} />
                )}

                {showImportSuggestion && (
                    <div className="flex flex-col gap-3 rounded-2xl border border-gold/40 bg-gold/10 px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
                        <div className="flex items-start gap-3 text-sm text-foreground">
                            <Download className="mt-0.5 size-4 shrink-0" />
                            <span>
                                You have already made these picks in{' '}
                                <strong>{importSources[0].source}</strong>.
                                Import them here instead of starting over?
                            </span>
                        </div>
                        <div className="flex shrink-0 gap-2">
                            <Button
                                type="button"
                                variant="gold"
                                size="sm"
                                onClick={() => setImportOpen(true)}
                            >
                                Import
                            </Button>
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                onClick={() => setSuggestionDismissed(true)}
                            >
                                Not now
                            </Button>
                        </div>
                    </div>
                )}

                <div className="flex flex-wrap items-center justify-between gap-3">
                    <ol className="flex flex-wrap gap-2">
                        {STEP_TITLES.map((title, index) => {
                            const remaining = stepRemainingCounts[index];
                            const showCount =
                                isStepEditable(index, canEdit, phasesByKey) &&
                                remaining > 0;

                            return (
                                <li key={title}>
                                    <button
                                        type="button"
                                        onClick={() => goToStep(index)}
                                        className={chipVariants({
                                            variant:
                                                index === step
                                                    ? 'active'
                                                    : 'default',
                                        })}
                                    >
                                        {index + 1}. {title}
                                        {showCount
                                            ? ` · ${remaining} left`
                                            : ''}
                                    </button>
                                </li>
                            );
                        })}
                    </ol>

                    {anyOpen && (
                        <div className="flex items-center gap-2">
                            <Button
                                type="button"
                                variant={onlyRemaining ? 'default' : 'outline'}
                                size="sm"
                                aria-pressed={onlyRemaining}
                                onClick={() =>
                                    setOnlyRemaining((value) => !value)
                                }
                            >
                                <ListFilter className="size-4" />
                                Needs prediction
                            </Button>
                            {nextStep !== null && (
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    onClick={() => goToStep(nextStep)}
                                >
                                    Next to predict
                                    <ChevronRight className="size-4" />
                                </Button>
                            )}
                        </div>
                    )}
                </div>

                <section className="flex flex-1 flex-col gap-4">
                    {step === 0 ? (
                        <>
                            {showTieNote && (
                                <div className="flex items-start gap-3 rounded-2xl border border-border bg-muted/40 px-4 py-3 text-sm text-muted-foreground">
                                    <Info className="mt-0.5 size-4 shrink-0" />
                                    <span>
                                        Some groups finish level on points. Ties
                                        do not affect this pool — your knockout
                                        predictions follow the real match-ups,
                                        so there is nothing to resolve here.
                                    </span>
                                </div>
                            )}
                            {onlyRemaining &&
                            stepRemainingCounts[0] === 0 &&
                            !thirdsTieUnresolved ? (
                                <StepClearNote />
                            ) : (
                                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                                    {visiblePredictGroups.map((group) => (
                                        <GroupCard
                                            key={group.name}
                                            group={group}
                                            scores={groupScores}
                                            canEdit={canEdit}
                                            onChange={updateGroupScore}
                                            onCommit={flush}
                                            orderingUrl={orderingUrl}
                                            fixtureFilter={
                                                // A group kept only for its tie shows all its
                                                // (scored) fixtures for context, not an empty card.
                                                onlyRemaining &&
                                                group.fixtures.some(
                                                    groupFixtureRemaining,
                                                )
                                                    ? groupFixtureRemaining
                                                    : undefined
                                            }
                                        />
                                    ))}
                                </div>
                            )}
                            {isUpfront &&
                                thirdsTie &&
                                thirdsTie.teams.length > 0 && (
                                    <TieResolutionPanel
                                        title="Tied for the last third-place spots"
                                        description="These third-placed teams are level. Drag them into order — the ones you rank highest take the final qualifying spots."
                                        clusters={[
                                            {
                                                teams: thirdsTie.teams,
                                                resolved: thirdsTie.resolved,
                                            },
                                        ]}
                                        editable={canEdit}
                                        url={orderingUrl}
                                        payloadFor={(orderedTeamIds) => ({
                                            scope: 'thirds',
                                            ordered_team_ids: orderedTeamIds,
                                        })}
                                    />
                                )}
                        </>
                    ) : (
                        <>
                            {step === 1 && isUpfront && (
                                <ThirdsPanel thirds={thirds} />
                            )}
                            {onlyRemaining &&
                            stepRemainingCounts[step] === 0 ? (
                                <StepClearNote />
                            ) : (
                                <KnockoutStep
                                    phaseKeys={
                                        KNOCKOUT_STEPS[step - 1].phaseKeys
                                    }
                                    phasesByKey={phasesByKey}
                                    picks={picks}
                                    fixtureFilter={
                                        onlyRemaining
                                            ? knockoutFixtureRemaining
                                            : undefined
                                    }
                                    onChange={(fixtureId, patch, immediate) =>
                                        updatePick(fixtureId, patch, immediate)
                                    }
                                    onCommit={flush}
                                />
                            )}
                        </>
                    )}
                </section>

                <footer className="sticky bottom-0 flex items-center justify-between gap-3 border-t border-border bg-background/80 py-4 backdrop-blur">
                    <Button
                        type="button"
                        variant="outline"
                        disabled={step === 0}
                        onClick={() => goToStep(Math.max(0, step - 1))}
                    >
                        <ChevronLeft className="size-4" /> Back
                    </Button>

                    {currentStepEditable && <SaveStatus status={saveStatus} />}

                    {isLastStep ? (
                        <Button variant="gold" asChild>
                            <Link href={pools.show(pool.slug)}>Finish</Link>
                        </Button>
                    ) : (
                        <Button
                            type="button"
                            variant={canEdit ? 'default' : 'outline'}
                            onClick={() => goToStep(step + 1)}
                        >
                            Next <ChevronRight className="size-4" />
                        </Button>
                    )}
                </footer>
            </div>

            {canImport && (
                <ImportPredictionsDialog
                    poolSlug={pool.slug}
                    sources={importSources}
                    open={importOpen}
                    onOpenChange={setImportOpen}
                    onBeforeImport={cancelPendingSave}
                />
            )}

            <PredictionCompleteDialog
                open={celebrateOpen}
                onOpenChange={setCelebrateOpen}
                poolSlug={pool.slug}
                windows={completion.open_windows}
            />
        </>
    );
}

function ThirdsPanel({ thirds }: { thirds: ThirdRanking[] | null }) {
    return (
        <div className="card-elevated flex flex-col gap-2 rounded-2xl p-4">
            <h3 className="font-display text-xs font-bold tracking-wide text-primary uppercase">
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
                            <Flag team={entry.team} />
                            {entry.team?.name ?? 'TBD'}
                        </li>
                    ))}
                </ol>
            )}
        </div>
    );
}

function PhaseWindowBadge({ window }: { window: PredictionWindowStatus }) {
    if (window === 'open') {
        return null;
    }

    const label = window === 'pending' ? 'Opens later' : 'Locked';

    return (
        <span className="inline-flex items-center gap-1 rounded-full bg-muted px-2.5 py-0.5 text-[11px] font-semibold text-muted-foreground">
            <Lock className="size-3" />
            {label}
        </span>
    );
}

function KnockoutStep({
    phaseKeys,
    phasesByKey,
    picks,
    fixtureFilter,
    onChange,
    onCommit,
}: {
    phaseKeys: string[];
    phasesByKey: Record<string, PredictBracketPhase>;
    picks: KnockoutPicks;
    /** When set, only fixtures it keeps are shown (the "needs my prediction" filter). */
    fixtureFilter?: (fixture: KnockoutPredictionFixture) => boolean;
    onChange: (
        fixtureId: number,
        patch: Partial<KnockoutPick>,
        immediate?: boolean,
    ) => void;
    onCommit: () => void;
}) {
    return (
        <div className="flex flex-col gap-8">
            {phaseKeys.map((key) => {
                const phase = phasesByKey[key];

                if (!phase) {
                    return null;
                }

                const phaseEditable = phase.window === 'open';
                const fixtures = fixtureFilter
                    ? phase.fixtures.filter(fixtureFilter)
                    : phase.fixtures;

                // Under an active filter a round with nothing left drops out entirely.
                if (fixtureFilter && fixtures.length === 0) {
                    return null;
                }

                return (
                    <div key={key} className="flex flex-col gap-3">
                        <div className="flex items-center justify-between gap-2">
                            <h2 className="font-display text-xs font-bold tracking-wide text-primary uppercase">
                                {phase.phase_name}
                            </h2>
                            <PhaseWindowBadge window={phase.window} />
                        </div>
                        {phase.window === 'pending' ? (
                            <p className="text-sm text-muted-foreground italic">
                                This round opens once the previous round&apos;s
                                results are in.
                            </p>
                        ) : (
                            <div className="flex flex-wrap gap-3">
                                {fixtures.map((fixture) => (
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
                                        canEdit={phaseEditable}
                                        isFinal={fixture.phase_key === 'final'}
                                        onChange={(patch, immediate) =>
                                            onChange(
                                                fixture.fixture_id,
                                                patch,
                                                immediate,
                                            )
                                        }
                                        onCommit={onCommit}
                                    />
                                ))}
                            </div>
                        )}
                    </div>
                );
            })}
        </div>
    );
}

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Pools', href: pools.index() }];

Predict.layout = { breadcrumbs };
