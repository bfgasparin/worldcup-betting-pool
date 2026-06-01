<?php

namespace Tests\Feature\Auth;

use App\Actions\Auth\SendLoginCode;
use App\Models\User;
use App\Notifications\LoginCodeNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginCodeNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_mail_message_keeps_the_code_out_of_the_subject(): void
    {
        $user = User::factory()->make(['email' => 'player@example.com']);

        $mail = (new LoginCodeNotification('482915'))->toMail($user);

        $this->assertStringNotContainsString('482915', $mail->subject);
        $this->assertStringContainsString(config('app.name'), $mail->subject);
    }

    public function test_the_mail_message_renders_through_the_branded_views(): void
    {
        $user = User::factory()->make(['email' => 'player@example.com']);

        $mail = (new LoginCodeNotification('482915'))->toMail($user);

        $this->assertSame(['emails.login-code', 'emails.login-code-text'], $mail->view);
        $this->assertSame('482915', $mail->viewData['code']);
        $this->assertSame(SendLoginCode::TTL_MINUTES, $mail->viewData['expiresInMinutes']);
        $this->assertSame('player@example.com', $mail->viewData['email']);
    }

    public function test_the_html_email_renders_the_code_with_brand_identity(): void
    {
        $html = view('emails.login-code', [
            'code' => '482915',
            'expiresInMinutes' => SendLoginCode::TTL_MINUTES,
            'email' => 'player@example.com',
        ])->render();

        $this->assertStringContainsString('482915', $html);
        $this->assertStringContainsString((string) SendLoginCode::TTL_MINUTES, $html);
        $this->assertStringContainsString('Your login code', $html);
        $this->assertStringContainsString('Secure sign-in', $html);
        $this->assertStringContainsString('player@example.com', $html);
        // Brand markers from the reusable shell: pitch-green brand bar + gold wordmark ampersand.
        $this->assertStringContainsString('#0FA968', strtoupper($html));
        $this->assertStringContainsString('#FFC23C', strtoupper($html));
    }

    public function test_the_plain_text_email_includes_the_code(): void
    {
        $text = view('emails.login-code-text', [
            'code' => '482915',
            'expiresInMinutes' => SendLoginCode::TTL_MINUTES,
            'email' => 'player@example.com',
        ])->render();

        $this->assertStringContainsString('482915', $text);
        $this->assertStringContainsString('FF&A', $text);
        $this->assertStringContainsString((string) SendLoginCode::TTL_MINUTES, $text);
    }
}
