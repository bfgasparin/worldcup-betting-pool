<?php

namespace Tests\Feature\Mail;

use App\Enums\PoolAccent;
use App\Models\User;
use App\Notifications\LeaderboardRankChangedNotification;
use App\Notifications\TopOfLeaderboardNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeaderboardEmailsTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_top_of_leaderboard_email_renders_the_milestone(): void
    {
        $accent = PoolAccent::Teal;
        $data = [
            'poolName' => 'World Cup 2026',
            'source' => 'FF&A',
            'accentGradient' => $accent->gradientCss(),
            'accentSolid' => $accent->solidHex(),
            'accentInk' => $accent->eyebrowInk(),
            'leaderboardLabel' => 'Overall',
            'points' => 200,
            'totalEntries' => 12,
            'runnerUpName' => 'Aisha',
            'leadOverRunnerUp' => 35,
            'userName' => 'Sam',
            'url' => 'https://ffa.test/pools/world-cup-2026/leaderboard',
        ];

        $html = view('emails.top-of-leaderboard', $data)->render();

        $this->assertStringContainsString('1st', $html);
        $this->assertStringContainsString('200 pts', $html);
        $this->assertStringContainsString('Sam', $html);
        $this->assertStringContainsString('35 pts', $html);
        $this->assertStringContainsString('Aisha', $html);
        $this->assertStringContainsString('Overall leaderboard', $html);
        // The source leads the hero and footer so the email's pool is unmistakable
        // (Blade escapes the ampersand in "FF&A").
        $this->assertStringContainsString('Pool by FF&amp;A', $html);
        $this->assertStringContainsString("FF&amp;A's World Cup 2026", $html);
        // The pool's accent tints the hero eyebrow (a contrast-safe ink) and the brand bar.
        $this->assertStringContainsString($accent->eyebrowInk(), $html);
        $this->assertStringContainsString($accent->gradientCss(), $html);
        $this->assertStringContainsString('https://ffa.test/pools/world-cup-2026/leaderboard', $html);
        // Copy must stay strategy-neutral — "keep predicting" is wrong for upfront-bracket pools,
        // where the whole bracket is already locked in (apostrophe-free fragment dodges Blade escaping).
        $this->assertStringContainsString('plenty of football still to play', $html);
        $this->assertStringNotContainsString('keep predicting', $html);

        $text = view('emails.top-of-leaderboard-text', $data)->render();

        $this->assertStringContainsString('1st', $text);
        $this->assertStringContainsString('Overall leaderboard', $text);
        $this->assertStringContainsString("FF&A's World Cup 2026", $text);
        $this->assertStringContainsString('https://ffa.test/pools/world-cup-2026/leaderboard', $text);
        $this->assertStringContainsString('plenty of football still to play', $text);
        $this->assertStringNotContainsString('keep predicting', $text);

        // No runner-up: the "You've taken the lead" branch carries the same neutral copy.
        $soloHtml = view('emails.top-of-leaderboard', [...$data, 'runnerUpName' => null, 'leadOverRunnerUp' => null])->render();
        $this->assertStringContainsString('taken the lead', $soloHtml);
        $this->assertStringContainsString('plenty of football still to play', $soloHtml);
        $this->assertStringNotContainsString('keep predicting', $soloHtml);
    }

    public function test_the_rank_change_email_renders_a_climb(): void
    {
        $accent = PoolAccent::Teal;
        $data = [
            'poolName' => 'World Cup 2026',
            'source' => 'FF&A',
            'accentGradient' => $accent->gradientCss(),
            'accentSolid' => $accent->solidHex(),
            'accentInk' => $accent->eyebrowInk(),
            'leaderboardLabel' => 'Overall',
            'direction' => 'up',
            'rank' => 4,
            'previousRank' => 6,
            'delta' => 2,
            'totalEntries' => 12,
            'points' => 120,
            'aheadName' => 'Aisha',
            'pointsBehind' => 35,
            'userName' => 'Sam',
            'url' => 'https://ffa.test/pools/world-cup-2026/leaderboard',
        ];

        $html = view('emails.rank-change', $data)->render();

        $this->assertStringContainsString('climbed', $html);
        $this->assertStringContainsString('4th', $html);
        $this->assertStringContainsString('Aisha', $html);
        $this->assertStringContainsString('35 pts', $html);
        $this->assertStringContainsString('Overall leaderboard', $html);
        $this->assertStringContainsString('Pool by FF&amp;A', $html);
        $this->assertStringContainsString($accent->eyebrowInk(), $html);
        $this->assertStringContainsString('https://ffa.test/pools/world-cup-2026/leaderboard', $html);
        // The footer must stay strategy-neutral — "next matchday" is wrong for upfront-bracket pools.
        $this->assertStringContainsString('plenty of football still to come', $html);
        $this->assertStringNotContainsString('matchday', $html);

        $text = view('emails.rank-change-text', $data)->render();
        $this->assertStringContainsString('4th', $text);
        $this->assertStringContainsString('Overall leaderboard', $text);
        $this->assertStringContainsString("FF&A's World Cup 2026", $text);
        $this->assertStringContainsString('https://ffa.test/pools/world-cup-2026/leaderboard', $text);
        $this->assertStringContainsString('plenty of football still to come', $text);
        $this->assertStringNotContainsString('matchday', $text);
    }

    public function test_the_rank_change_email_renders_a_drop(): void
    {
        $accent = PoolAccent::Teal;
        $html = view('emails.rank-change', [
            'poolName' => 'World Cup 2026',
            'source' => 'FF&A',
            'accentGradient' => $accent->gradientCss(),
            'accentSolid' => $accent->solidHex(),
            'accentInk' => $accent->eyebrowInk(),
            'leaderboardLabel' => 'Overall',
            'direction' => 'down',
            'rank' => 6,
            'previousRank' => 4,
            'delta' => 2,
            'totalEntries' => 12,
            'points' => 80,
            'aheadName' => 'Aisha',
            'pointsBehind' => 20,
            'userName' => 'Sam',
            'url' => 'https://ffa.test/pools/world-cup-2026/leaderboard',
        ])->render();

        $this->assertStringContainsString('slipped', $html);
        $this->assertStringContainsString('6th', $html);
        $this->assertStringContainsString('plenty of football still to come', $html);
        $this->assertStringNotContainsString('matchday', $html);
    }

    public function test_the_rank_change_body_copy_is_strategy_neutral_when_no_one_is_ahead(): void
    {
        $accent = PoolAccent::Teal;
        $data = [
            'poolName' => 'World Cup 2026',
            'source' => 'FF&A',
            'accentGradient' => $accent->gradientCss(),
            'accentSolid' => $accent->solidHex(),
            'accentInk' => $accent->eyebrowInk(),
            'leaderboardLabel' => 'Overall',
            'direction' => 'up',
            'rank' => 1,
            'previousRank' => 3,
            'delta' => 2,
            'totalEntries' => 12,
            'points' => 200,
            // No player ahead, so the email falls back to the generic body line (not the "X pts behind" one).
            'aheadName' => null,
            'pointsBehind' => null,
            'userName' => 'Sam',
            'url' => 'https://ffa.test/pools/world-cup-2026/leaderboard',
        ];

        // A climber at the top: the body must not nudge toward predicting "the next matchday".
        $up = view('emails.rank-change', $data)->render();
        $this->assertStringContainsString('plenty of football still to play', $up);
        $this->assertStringNotContainsString('matchday', $up);

        // The drop variant of the same branch.
        $down = view('emails.rank-change', [...$data, 'direction' => 'down', 'rank' => 6, 'previousRank' => 4])->render();
        $this->assertStringContainsString('still time to climb back', $down);
        $this->assertStringNotContainsString('matchday', $down);
    }

    public function test_the_notification_subjects(): void
    {
        $user = User::factory()->make(['name' => 'Sam']);

        $milestone = (new TopOfLeaderboardNotification('World Cup 2026', 'world-cup-2026', 'FF&A', PoolAccent::Pitch, 200, 12, 'Aisha', 35))
            ->toMail($user);
        $this->assertStringContainsString("top of FF&A's World Cup 2026", $milestone->subject);

        $climb = (new LeaderboardRankChangedNotification('World Cup 2026', 'world-cup-2026', 'FF&A', PoolAccent::Pitch, 'up', 4, 6, 12, 120, 'Aisha', 35))
            ->toMail($user);
        $this->assertStringContainsString("climbed to 4th in FF&A's World Cup 2026", $climb->subject);

        $drop = (new LeaderboardRankChangedNotification('World Cup 2026', 'world-cup-2026', 'FF&A', PoolAccent::Pitch, 'down', 6, 4, 12, 80, 'Aisha', 20))
            ->toMail($user);
        $this->assertStringContainsString("slipped to 6th in FF&A's World Cup 2026", $drop->subject);
    }
}
