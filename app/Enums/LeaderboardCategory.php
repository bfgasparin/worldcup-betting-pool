<?php

namespace App\Enums;

use App\Models\LeaderboardStanding;
use App\Services\Scoring\KnockoutProgressionScorer;
use App\Services\Scoring\LeaderboardMetrics;

/**
 * The leaderboards a pool runs. Every entry holds one standing per case (see
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
            self::Overall => __('Overall'),
            self::MatchWinners => __('Match Winners'),
            self::GoalSniper => __('Goal Sniper'),
        };
    }

    /**
     * A one-line summary of what the board ranks by, shown above each board's table and as the
     * headline in the "How this pool works" dialog. The worked example lives in {@see howItScores()}.
     */
    public function description(): string
    {
        return match ($this) {
            self::Overall => __('Your total points across the whole tournament — the main, headline board.'),
            self::MatchWinners => __('How many match results you call right — the winner or a draw, whatever the score.'),
            self::GoalSniper => __('How many individual team goal counts you predict exactly.'),
        };
    }

    /**
     * A fuller, plain-language explanation of exactly how the board scores — with a worked example —
     * shown in the "How this pool works" dialog so a player knows precisely what counts. The
     * tie-break is rendered separately from {@see secondaryStatLabel()}, so it's left out here.
     */
    public function howItScores(): string
    {
        return match ($this) {
            self::Overall => __('Adds up the points from every prediction across the tournament — the more precise your call, the more it is worth (an exact scoreline scores far more than just naming the winner). It is the only board that pays out the prize pot. Example: you predict Brazil 2–1; a spot-on 2–1 banks the full points, while 3–1 still earns for calling the win.'),
            self::MatchWinners => __('Counts the matches where you called the result correctly — just who won, or that it was a draw, no matter the scoreline. In the knockouts, that is picking the team that actually goes through. Example: you predict Brazil 2–1 France but it finishes 3–0 Brazil — the score is wrong, yet you still nailed the winner, so it counts here.'),
            self::GoalSniper => __('Counts how many individual team goal tallies you predict exactly — each team in each match is judged on its own, so a game is worth 0, 1 or 2. Example: you predict Brazil 2–1 France but it ends 2–3 — you still nailed Brazil’s 2, so that is one hit, even though the result went the other way.'),
        };
    }

    /**
     * The label for the board's headline number (the value it ranks by).
     */
    public function primaryStatLabel(): string
    {
        return match ($this) {
            self::Overall => __('Points'),
            self::MatchWinners => __('Winners'),
            self::GoalSniper => __('Team goals'),
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
            self::MatchWinners => __('Team goals'),
            self::GoalSniper => __('Winners'),
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
     * Whether finishing top of this board wins a share of the prize pot. Only the Overall board
     * pays out; the others are bragging-rights side boards. Drives the "Prize board" UI signal.
     */
    public function awardsPrizes(): bool
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
