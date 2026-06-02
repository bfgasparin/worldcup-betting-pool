<?php

namespace App\Enums;

use App\Models\LeaderboardStanding;
use App\Services\Scoring\KnockoutProgressionScorer;
use App\Services\Scoring\LeaderboardMetrics;

/**
 * The leaderboards a game runs. Every entry holds one standing per case (see
 * {@see LeaderboardStanding}); a board ranks by {@see valueFor()} descending, then
 * {@see tiebreakerFor()} descending, then entry id. Cases are declared in display order, so
 * {@see ordered()} (and `cases()`) drive the tab order.
 *
 * All boards span the whole tournament (group + knockout). For a knockout match — where the player
 * predicts the matchup, not a fixed pairing — "exact score", "match winner" and "team goals" are
 * defined per team in {@see KnockoutProgressionScorer}.
 */
enum LeaderboardCategory: string
{
    case Overall = 'overall';
    case MatchWinners = 'match-winners';
    case GoalSniper = 'goal-sniper';

    /**
     * Every category in display (tab) order.
     *
     * @return list<self>
     */
    public static function ordered(): array
    {
        return self::cases();
    }

    /**
     * The full board name, used as the page/tab title.
     */
    public function label(): string
    {
        return match ($this) {
            self::Overall => 'Overall',
            self::MatchWinners => 'Match Winners',
            self::GoalSniper => 'Goal Sniper',
        };
    }

    /**
     * A one-line explanation of how the board ranks, shown in the "How this game works" dialog and
     * above each board's table.
     */
    public function description(): string
    {
        return match ($this) {
            self::Overall => 'Total points across every match — the headline leaderboard.',
            self::MatchWinners => 'Most match results called correctly — group winners and draws, plus the teams you sent through in the knockouts.',
            self::GoalSniper => 'Most individual team goal counts predicted correctly, across every match.',
        };
    }

    /**
     * The label for the board's headline number (the value it ranks by).
     */
    public function primaryStatLabel(): string
    {
        return match ($this) {
            self::Overall => 'Points',
            self::MatchWinners => 'Winners',
            self::GoalSniper => 'Team goals',
        };
    }

    /**
     * The label for the board's tie-break number, or null when the board breaks ties on entry id
     * alone (Overall).
     */
    public function secondaryStatLabel(): ?string
    {
        return match ($this) {
            self::Overall => null,
            self::MatchWinners => 'Team goals',
            self::GoalSniper => 'Winners',
        };
    }

    /**
     * Whether movement/leader emails fire for this board. Only the Overall board notifies; the
     * others would flood inboxes on every approval.
     */
    public function notifies(): bool
    {
        return $this === self::Overall;
    }

    /**
     * The value this board ranks by, pulled from a recompute's aggregates.
     */
    public function valueFor(LeaderboardMetrics $metrics): int
    {
        return match ($this) {
            self::Overall => $metrics->points,
            self::MatchWinners => $metrics->correctOutcomes,
            self::GoalSniper => $metrics->teamGoalsHit,
        };
    }

    /**
     * The tie-break value for this board (0 when it breaks ties on entry id alone).
     */
    public function tiebreakerFor(LeaderboardMetrics $metrics): int
    {
        return match ($this) {
            self::Overall => 0,
            // Tied on winners? The sharper goal-reader ranks higher, and vice-versa.
            self::MatchWinners => $metrics->teamGoalsHit,
            self::GoalSniper => $metrics->correctOutcomes,
        };
    }
}
