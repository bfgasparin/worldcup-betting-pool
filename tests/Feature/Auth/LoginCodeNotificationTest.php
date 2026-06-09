<?php

namespace Tests\Feature\Auth;

use App\Actions\Auth\SendLoginCode;
use App\Models\User;
use App\Notifications\LoginCodeNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Translation\HasLocalePreference;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\App;
use Tests\TestCase;

class LoginCodeNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_notification_is_queued_so_it_does_not_block_the_request(): void
    {
        // Queueing keeps the SMTP round-trip off the web request and gives the
        // anti-enumeration endpoint a uniform response time for any email.
        $this->assertInstanceOf(ShouldQueue::class, new LoginCodeNotification('482915'));
    }

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

    public function test_the_email_follows_the_recipients_preferred_locale(): void
    {
        $user = User::factory()->make(['locale' => 'pt_BR', 'email' => 'player@example.com']);

        // The User exposes a preferred locale, so Laravel sets it for the notification send.
        $this->assertInstanceOf(HasLocalePreference::class, $user);
        $this->assertSame('pt_BR', $user->preferredLocale());

        // Reproduce that wrap, then assert the rendered email is in Portuguese.
        App::setLocale($user->preferredLocale());
        $mail = (new LoginCodeNotification('482915'))->toMail($user);
        $html = view($mail->view[0], $mail->viewData)->render();

        $this->assertStringContainsString('Código de acesso', $mail->subject);
        $this->assertStringContainsString('Seu código de acesso', $html);

        // A user with no preference falls back to the English default.
        App::setLocale('en');
        $enMail = (new LoginCodeNotification('482915'))->toMail(User::factory()->make(['locale' => null]));
        $enHtml = view($enMail->view[0], $enMail->viewData)->render();
        $this->assertStringContainsString('Your login code', $enHtml);
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
        // Brand markers from the reusable shell: pitch-green brand bar + gold wordmark accent.
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
        $this->assertStringContainsString('Brothers Bets', $text);
        $this->assertStringContainsString((string) SendLoginCode::TTL_MINUTES, $text);
    }
}
