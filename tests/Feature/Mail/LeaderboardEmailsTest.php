<?php

namespace Tests\Feature\Mail;

use App\Enums\GameAccent;
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
        $accent = GameAccent::Teal;
        $data = [
            'gameName' => 'World Cup 2026',
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
            'url' => 'https://ffa.test/games/world-cup-2026/leaderboard',
        ];

        $html = view('emails.top-of-leaderboard', $data)->render();

        $this->assertStringContainsString('1st', $html);
        $this->assertStringContainsString('200 pts', $html);
        $this->assertStringContainsString('Sam', $html);
        $this->assertStringContainsString('35 pts', $html);
        $this->assertStringContainsString('Aisha', $html);
        $this->assertStringContainsString('Overall leaderboard', $html);
        // The source leads the hero and footer so the email's game is unmistakable
        // (Blade escapes the ampersand in "FF&A").
        $this->assertStringContainsString('Game by FF&amp;A', $html);
        $this->assertStringContainsString("FF&amp;A's World Cup 2026", $html);
        // The game's accent tints the hero eyebrow (a contrast-safe ink) and the brand bar.
        $this->assertStringContainsString($accent->eyebrowInk(), $html);
        $this->assertStringContainsString($accent->gradientCss(), $html);
        $this->assertStringContainsString('https://ffa.test/games/world-cup-2026/leaderboard', $html);

        $text = view('emails.top-of-leaderboard-text', $data)->render();

        $this->assertStringContainsString('1st', $text);
        $this->assertStringContainsString('Overall leaderboard', $text);
        $this->assertStringContainsString("FF&A's World Cup 2026", $text);
        $this->assertStringContainsString('https://ffa.test/games/world-cup-2026/leaderboard', $text);
    }

    public function test_the_rank_change_email_renders_a_climb(): void
    {
        $accent = GameAccent::Teal;
        $data = [
            'gameName' => 'World Cup 2026',
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
            'url' => 'https://ffa.test/games/world-cup-2026/leaderboard',
        ];

        $html = view('emails.rank-change', $data)->render();

        $this->assertStringContainsString('climbed', $html);
        $this->assertStringContainsString('4th', $html);
        $this->assertStringContainsString('Aisha', $html);
        $this->assertStringContainsString('35 pts', $html);
        $this->assertStringContainsString('Overall leaderboard', $html);
        $this->assertStringContainsString('Game by FF&amp;A', $html);
        $this->assertStringContainsString($accent->eyebrowInk(), $html);
        $this->assertStringContainsString('https://ffa.test/games/world-cup-2026/leaderboard', $html);

        $text = view('emails.rank-change-text', $data)->render();
        $this->assertStringContainsString('4th', $text);
        $this->assertStringContainsString('Overall leaderboard', $text);
        $this->assertStringContainsString("FF&A's World Cup 2026", $text);
        $this->assertStringContainsString('https://ffa.test/games/world-cup-2026/leaderboard', $text);
    }

    public function test_the_rank_change_email_renders_a_drop(): void
    {
        $accent = GameAccent::Teal;
        $html = view('emails.rank-change', [
            'gameName' => 'World Cup 2026',
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
            'url' => 'https://ffa.test/games/world-cup-2026/leaderboard',
        ])->render();

        $this->assertStringContainsString('slipped', $html);
        $this->assertStringContainsString('6th', $html);
    }

    public function test_the_notification_subjects(): void
    {
        $user = User::factory()->make(['name' => 'Sam']);

        $milestone = (new TopOfLeaderboardNotification('World Cup 2026', 'world-cup-2026', 'FF&A', GameAccent::Pitch, 200, 12, 'Aisha', 35))
            ->toMail($user);
        $this->assertStringContainsString("top of FF&A's World Cup 2026", $milestone->subject);

        $climb = (new LeaderboardRankChangedNotification('World Cup 2026', 'world-cup-2026', 'FF&A', GameAccent::Pitch, 'up', 4, 6, 12, 120, 'Aisha', 35))
            ->toMail($user);
        $this->assertStringContainsString("climbed to 4th in FF&A's World Cup 2026", $climb->subject);

        $drop = (new LeaderboardRankChangedNotification('World Cup 2026', 'world-cup-2026', 'FF&A', GameAccent::Pitch, 'down', 6, 4, 12, 80, 'Aisha', 20))
            ->toMail($user);
        $this->assertStringContainsString("slipped to 6th in FF&A's World Cup 2026", $drop->subject);
    }
}
