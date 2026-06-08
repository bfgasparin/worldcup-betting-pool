import type { RankMovement, TeamRef } from '@/types/pools';

/** The live status of a fixture's scoreboard, mirroring App\Enums\LiveStatus. */
export type LiveStatus = 'scheduled' | 'live' | 'ended';

/** A match currently being followed live, with its live scoreline. */
export interface LiveFixture {
    id: number;
    home_team: TeamRef | null;
    away_team: TeamRef | null;
    home_label: string | null;
    away_label: string | null;
    home_goals: number | null;
    away_goals: number | null;
    status: Extract<LiveStatus, 'live' | 'ended'>;
    is_knockout: boolean;
    kicks_off_at: string | null;
    started_at: string | null;
}

/** One entry's projected standing on a board — viewer-agnostic; the client marks "you". */
export interface ProjectedRow {
    entry_id: number;
    user_id: number;
    name: string;
    initials: string;
    avatar: string | null;
    rank: number;
    primary_value: number;
    secondary_value: number | null;
    /** Primary-stat units gained from the current live scores vs. the banked official value. */
    live_gain: number;
    /** The entry's current official rank on this board, the baseline movement is measured against. */
    official_rank: number | null;
    movement: RankMovement | null;
    movement_delta: number | null;
    /** The projected payout if the standings froze now (paid pools, prize places only). */
    projected_prize: number | null;
    /** Winner-dependent bonus held while a live knockout is undecided — "+X if it holds". */
    pending_bonus: number;
}

/** A board's labels, shared across the pools shown on the live page. */
export interface LiveBoardDescriptor {
    key: string;
    label: string;
    description: string;
    primary_stat_label: string;
    secondary_stat_label: string | null;
    awards_prizes: boolean;
}

/** A joined pool's live projection: identity plus its ranked rows per board. */
export interface LivePool {
    slug: string;
    name: string;
    source: string;
    accent: string | null;
    scoring_strategy: string;
    scoring_label: string;
    is_paid: boolean;
    currency: string;
    boards: Record<string, ProjectedRow[]>;
}

/** A tournament with live matches, on the Live Center landing picker. */
export interface LiveTournamentSummary {
    name: string;
    slug: string;
    live_match_count: number;
}

/** A fixture on the admin live console — eligible to start, or already live/ended. */
export interface LiveControlFixture {
    id: number;
    home_team: TeamRef | null;
    away_team: TeamRef | null;
    home_label: string | null;
    away_label: string | null;
    kicks_off_at: string | null;
    is_knockout: boolean;
    can_go_live: boolean;
    live_status: LiveStatus | null;
    live_home_goals: number | null;
    live_away_goals: number | null;
}
