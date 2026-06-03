export type GameStatus = 'upcoming' | 'in_progress' | 'completed';

/** A Laravel length-aware paginator as serialized to Inertia. */
export interface Paginated<T> {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    prev_page_url: string | null;
    next_page_url: string | null;
}

export interface GameSummary {
    slug: string;
    name: string;
    /** The group/source that created the game, e.g. "FF&A". */
    source: string;
    /** The game's colour identity key (pitch|teal|gold|violet); null falls back to a positional colour. */
    accent?: string | null;
    /** Short human-readable scoring style, e.g. "Upfront Bracket". */
    scoring_label?: string;
    sport: string;
    status: GameStatus;
    starts_on: string | null;
    ends_on: string | null;
    groups_count?: number;
    fixtures_count?: number;
}

/**
 * A game as shown on the selection page: the summary plus how it scores, so players can tell
 * games over the same competition apart.
 */
export interface GameListItem extends GameSummary {
    scoring_strategy: string;
    scoring_label: string;
    scoring_description: string;
    scoring_config: Record<string, Record<string, number>>;
    /** The competition this game is played over. Sibling games share this identity. */
    tournament: { id: number; name: string };
    /** 0-based position among the tournament's games (by id) — drives the per-game kit colour. */
    accent_index: number;
    /** Size of this game's pool (number of entries) — distinct per game. */
    players_count: number;
}

export interface TeamRef {
    id: number;
    name: string;
    code: string | null;
    is_placeholder: boolean;
    flag_url: string;
}

export interface GroupTeam extends TeamRef {
    position: number;
}

export interface GroupFixture {
    match_number: number;
    home: TeamRef | null;
    away: TeamRef | null;
    home_goals: number | null;
    away_goals: number | null;
    kicks_off_at: string | null;
    venue: string | null;
    venue_timezone: string | null;
    /** The viewer's own predicted scoreline, if they've made one. */
    prediction: {
        home_goals: number;
        away_goals: number;
        /** Points earned once the official result is in (null while unscored). */
        points_awarded: number | null;
    } | null;
}

export interface GroupView {
    name: string;
    teams: GroupTeam[];
    fixtures: GroupFixture[];
    /** Official live group table, computed from real match results. */
    standings: StandingRow[];
    /**
     * The viewer's projected group table from their own predicted scores, or null when they
     * have predicted none of this group's fixtures.
     */
    predicted_standings: StandingRow[] | null;
}

export interface BracketFixture {
    match_number: number;
    bracket_slot: string | null;
    home: TeamRef | null;
    away: TeamRef | null;
    home_label: string | null;
    away_label: string | null;
    home_goals: number | null;
    away_goals: number | null;
    home_penalties: number | null;
    away_penalties: number | null;
    /** The official advancing team once the match is settled. */
    winner_team_id: number | null;
    kicks_off_at: string | null;
    venue: string | null;
    venue_timezone: string | null;
    /** The viewer's own pick for this knockout match, if they made one. */
    prediction: {
        home_goals: number | null;
        away_goals: number | null;
        advancing_team_id: number | null;
        points_awarded: number | null;
        /** The teams the viewer predicted for this match (upfront-bracket tournaments only). */
        predicted_home: TeamRef | null;
        predicted_away: TeamRef | null;
    } | null;
}

export interface BracketPhase {
    phase_key: string;
    phase_name: string;
    sort_order: number;
    fixtures: BracketFixture[];
}

/** How and when to fill in predictions for a game's scoring strategy. */
export interface HowToPlay {
    summary: string;
    steps: string[];
}

export interface GameDetail extends GameSummary {
    scoring_strategy: string;
    scoring_label: string;
    scoring_description: string;
    how_to_play: HowToPlay;
    scoring_config: Record<string, Record<string, number>>;
    /** When the group-stage predictions lock (ISO 8601): derived from the first group kickoff (minus the buffer) or an explicit override; null when there is no schedule to derive from. */
    predictions_lock_at: string | null;
    /** Lifecycle statuses this tournament may transition into (admin only). */
    allowed_transitions: GameStatus[];
    /** Whether the viewer may open the admin score-review screen. */
    can_review_scores: boolean;
    /** Board descriptors for the "How this game works" dialog. */
    leaderboards: BoardDescriptor[];
}

// --- Leaderboards ---

/** Movement on a leaderboard since the last approved results, or "new" on first appearance. */
export type RankMovement = 'up' | 'down' | 'same' | 'new';

/** The string key of each leaderboard (matches the backend LeaderboardCategory enum). */
export type LeaderboardCategoryKey =
    | 'overall'
    | 'match-winners'
    | 'goal-sniper';

/** A ranked row on the Overall pool preview (game page hero). */
export interface LeaderboardEntryRow {
    rank: number;
    name: string;
    initials: string;
    points: number | null;
    is_me: boolean;
    movement: RankMovement | null;
}

export interface PoolSummary {
    participants: number;
    has_scores: boolean;
    me: LeaderboardEntryRow | null;
    top: LeaderboardEntryRow[];
}

/** A ranked row on any of the full leaderboards (Leaderboards page). */
export interface BoardRow {
    rank: number;
    name: string;
    initials: string;
    /** The board's headline value; null renders as "—" and suppresses podium styling. */
    primary_value: number | null;
    /** The board's tie-break value, or null for boards that don't show one (Overall). */
    secondary_value: number | null;
    is_me: boolean;
    movement: RankMovement | null;
}

export interface LeaderboardBoard {
    key: LeaderboardCategoryKey;
    label: string;
    description: string;
    primary_stat_label: string;
    secondary_stat_label: string | null;
    has_scores: boolean;
    rows: BoardRow[];
}

/** A board summary for the game page: who leads it, and where the viewer stands on it. */
export interface BoardSummary {
    key: LeaderboardCategoryKey;
    label: string;
    primary_stat_label: string;
    leader: {
        name: string;
        initials: string;
        primary_value: number | null;
    } | null;
    you: {
        rank: number;
        primary_value: number | null;
        movement: RankMovement | null;
    } | null;
}

/** A board's name + blurb (no rows), for the "How this game works" dialog. */
export interface BoardDescriptor {
    key: LeaderboardCategoryKey;
    label: string;
    description: string;
    primary_stat_label: string;
    secondary_stat_label: string | null;
}

export interface LeaderboardPageProps {
    game: GameSummary;
    boards: LeaderboardBoard[];
    active_board: LeaderboardCategoryKey | null;
}

// --- Prediction wizard ---

export interface StandingRow {
    rank: number;
    team: TeamRef | null;
    played: number;
    won: number;
    drawn: number;
    lost: number;
    goals_for: number;
    goals_against: number;
    goal_difference: number;
    points: number;
    form: string[];
}

export interface PredictGroupFixture {
    fixture_id: number;
    match_number: number;
    home: TeamRef | null;
    away: TeamRef | null;
    home_goals: number | null;
    away_goals: number | null;
    kicks_off_at: string | null;
    venue: string | null;
    venue_timezone: string | null;
}

export interface PredictGroup {
    name: string;
    teams: GroupTeam[];
    fixtures: PredictGroupFixture[];
    standings: StandingRow[];
    /**
     * Clusters of teams the ranking engine could not separate (level on every tiebreaker), each in
     * its current effective order with whether a saved ordering already resolves it. The player
     * drags these into order; empty when none. Only for upfront-bracket games, whose bracket is
     * derived from these standings.
     */
    tied_clusters: TiedCluster[];
}

/** A tied set the player/admin must order, plus whether a saved ordering already resolves it. */
export interface TiedCluster {
    team_ids: number[];
    resolved: boolean;
}

export interface KnockoutPredictionFixture {
    fixture_id: number;
    match_number: number;
    bracket_slot: string | null;
    phase_key: string;
    home: TeamRef | null;
    away: TeamRef | null;
    home_label: string | null;
    away_label: string | null;
    home_goals: number | null;
    away_goals: number | null;
    advancing_team_id: number | null;
}

/**
 * Whether a phase currently accepts predictions: `open` is editable now, `locked` has closed
 * (kicked off / past the lock), `pending` is a phased knockout round whose real teams aren't known
 * yet so it hasn't opened.
 */
export type PredictionWindowStatus = 'open' | 'locked' | 'pending';

export interface PredictBracketPhase {
    phase_key: string;
    phase_name: string;
    sort_order: number;
    /** This round's prediction window state. */
    window: PredictionWindowStatus;
    fixtures: KnockoutPredictionFixture[];
}

export interface ThirdRanking {
    rank: number;
    team: TeamRef | null;
}

export interface PredictGameDetail {
    slug: string;
    name: string;
    /** The group/source that created the game, e.g. "FF&A". */
    source: string;
    /** The game's colour identity key (pitch|teal|gold|violet); null falls back to a positional colour. */
    accent?: string | null;
    /** Short human-readable scoring style, e.g. "Upfront Bracket". */
    scoring_label?: string;
    sport: string;
    status: GameStatus;
    /** Which scoring strategy this game uses (e.g. 'upfront-bracket', 'phased-bracket'). */
    scoring_strategy: string;
    starts_on: string | null;
    ends_on: string | null;
    predictions_lock_at: string | null;
    /** Whether the group stage (and, for upfront games, the whole bracket) accepts edits. */
    can_edit: boolean;
    scoring_config: Record<string, Record<string, number>>;
}

export interface PredictPageProps {
    game: PredictGameDetail;
    groups: PredictGroup[];
    bracket: PredictBracketPhase[];
    thirds: ThirdRanking[] | null;
    /**
     * The third-placed teams whose tie straddles the qualifying cut and must be ordered before the
     * best-third slots can fill (effective order + whether a saved ordering resolves it); null when
     * there is no such tie.
     */
    thirds_tie: { teams: TeamRef[]; resolved: boolean } | null;
}
