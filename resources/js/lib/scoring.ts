/**
 * Shared helpers for turning a game's `scoring_config` JSON into a readable, points-sorted
 * legend. Used by both the prediction wizard and the game-selection card so the labels and
 * ordering stay in one place.
 */

export const RULE_LABELS: Record<string, string> = {
    // group
    exact_score: 'Exact score',
    winner_and_one_team_exact_goals: 'Right winner + one team’s goals',
    correct_outcome_wrong_goals: 'Right result',
    one_team_exact_goals_wrong_outcome: 'One team’s goals',
    // knockout
    correct_team: 'Right team in the match',
    exact_matchup: 'Right team in the match', // legacy key
    team_reaches_phase: 'Right team in the match', // legacy key
    team_goal_count_bonus: 'Goal-count bonus',
    champion: 'Champion',
};

/** Turn an unmapped snake_case scoring key into a readable label as a fallback. */
export function humanizeRuleKey(key: string): string {
    const spaced = key.replace(/_/g, ' ');

    return spaced.charAt(0).toUpperCase() + spaced.slice(1);
}

export interface ScoringRule {
    label: string;
    points: number;
}

/**
 * The configured rules for one phase ('group' | 'knockout'), labelled and sorted by points
 * descending. Rules without a point value are dropped.
 */
export function scoringRules(
    config: Record<string, Record<string, number>>,
    phase: string,
): ScoringRule[] {
    return Object.entries(config[phase] ?? {})
        .map(([key, points]) => ({
            label: RULE_LABELS[key] ?? humanizeRuleKey(key),
            points,
        }))
        .filter((rule) => rule.points != null)
        .sort((a, b) => b.points - a.points);
}
