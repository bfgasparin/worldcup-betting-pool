/**
 * Shared helpers for turning a pool's `scoring_config` JSON into a readable, points-sorted
 * legend. Used by both the prediction wizard and the pool-selection card so the labels and
 * ordering stay in one place.
 */

export const RULE_LABELS: Record<string, string> = {
    // group
    exact_score: 'Exact score',
    winner_and_one_team_exact_goals: 'Right winner + one team’s goals',
    correct_outcome_wrong_goals: 'Right result',
    one_team_exact_goals_wrong_outcome: 'One team’s goals',
    // knockout (upfront bracket)
    correct_team: 'Right team in the match',
    exact_matchup: 'Right team in the match', // legacy key
    team_reaches_phase: 'Right team in the match', // legacy key
    team_goal_count_bonus: 'Goal-count bonus',
    champion: 'Champion',
    // knockout (phased bracket)
    advancing_team: 'Right team through',
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
 * descending. Only plain numeric entries become rules — nested config (e.g. the phased bracket's
 * `round_multipliers` map) is skipped. An optional `multiplier` scales the scoreline-tier points
 * for a given knockout round, leaving flat bonuses (e.g. `advancing_team`) untouched.
 */
const FLAT_RULE_KEYS = new Set(['advancing_team']);

export function scoringRules(
    config: Record<string, Record<string, number>>,
    phase: string,
    multiplier: number = 1,
): ScoringRule[] {
    return Object.entries(config[phase] ?? {})
        .filter(([, points]) => typeof points === 'number')
        .map(([key, points]) => ({
            label: RULE_LABELS[key] ?? humanizeRuleKey(key),
            points: FLAT_RULE_KEYS.has(key) ? points : points * multiplier,
        }))
        .sort((a, b) => b.points - a.points);
}
