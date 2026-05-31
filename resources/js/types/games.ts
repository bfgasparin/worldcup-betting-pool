export type GameStatus =
    | 'draft'
    | 'open'
    | 'locked'
    | 'in_progress'
    | 'completed';

export interface GameSummary {
    slug: string;
    name: string;
    sport: string;
    status: GameStatus;
    starts_on: string | null;
    ends_on: string | null;
    groups_count?: number;
    fixtures_count?: number;
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
}

export interface GroupView {
    name: string;
    teams: GroupTeam[];
    fixtures: GroupFixture[];
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
}

export interface BracketPhase {
    phase_key: string;
    phase_name: string;
    sort_order: number;
    fixtures: BracketFixture[];
}

export interface GameDetail extends GameSummary {
    scoring_config: Record<string, Record<string, number>>;
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
}

export interface PredictGroup {
    name: string;
    teams: GroupTeam[];
    fixtures: PredictGroupFixture[];
    standings: StandingRow[];
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

export interface PredictBracketPhase {
    phase_key: string;
    phase_name: string;
    sort_order: number;
    fixtures: KnockoutPredictionFixture[];
}

export interface ThirdRanking {
    rank: number;
    team: TeamRef | null;
}

export interface PredictGameDetail {
    slug: string;
    name: string;
    sport: string;
    status: GameStatus;
    starts_on: string | null;
    ends_on: string | null;
    predictions_lock_at: string | null;
    can_edit: boolean;
}

export interface PredictPageProps {
    game: PredictGameDetail;
    groups: PredictGroup[];
    bracket: PredictBracketPhase[];
    thirds: ThirdRanking[] | null;
}
