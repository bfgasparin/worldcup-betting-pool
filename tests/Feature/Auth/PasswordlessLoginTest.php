<?php

namespace Tests\Feature\Auth;

use App\Actions\Auth\SendLoginCode;
use App\Actions\Auth\VerifyLoginCode;
use App\Models\User;
use App\Notifications\LoginCodeNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\SendQueuedNotifications;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class PasswordlessLoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_requesting_a_code_for_a_registered_email_stores_a_code_and_sends_notification()
    {
        Notification::fake();

        $user = User::factory()->create();

        $response = $this->post(route('login.code.send'), [
            'email' => $user->email,
        ]);

        $response->assertSessionHas('status');
        $response->assertSessionHasNoErrors();

        $this->assertNotNull(Cache::get(SendLoginCode::cacheKey($user->email)));

        Notification::assertSentTo($user, LoginCodeNotification::class);
    }

    public function test_requesting_a_code_pushes_the_notification_onto_the_queue()
    {
        // The login-code email must not block the web request on the SMTP send.
        Queue::fake();

        $user = User::factory()->create();

        $this->post(route('login.code.send'), ['email' => $user->email])
            ->assertSessionHas('status');

        Queue::assertPushed(SendQueuedNotifications::class, function (SendQueuedNotifications $job) use ($user) {
            return $job->notification instanceof LoginCodeNotification
                && $job->notifiables->contains(fn ($notifiable) => $notifiable->is($user));
        });
    }

    public function test_requesting_a_code_for_an_unknown_email_responds_the_same_and_sends_nothing()
    {
        Notification::fake();

        $response = $this->post(route('login.code.send'), [
            'email' => 'nobody@example.com',
        ]);

        $response->assertSessionHas('status');
        $response->assertSessionHasNoErrors();

        $this->assertNull(Cache::get(SendLoginCode::cacheKey('nobody@example.com')));

        Notification::assertNothingSent();
    }

    public function test_requesting_a_code_requires_a_valid_email()
    {
        $response = $this->post(route('login.code.send'), [
            'email' => 'not-an-email',
        ]);

        $response->assertSessionHasErrors('email');
    }

    public function test_a_valid_code_logs_the_user_in_and_redirects_to_the_pools_hub()
    {
        $user = User::factory()->create();

        Cache::put(SendLoginCode::cacheKey($user->email), [
            'hash' => Hash::make('123456'),
            'attempts' => 0,
        ], now()->addMinutes(SendLoginCode::TTL_MINUTES));

        $response = $this->post(route('login.store'), [
            'email' => $user->email,
            'code' => '123456',
        ]);

        $this->assertAuthenticatedAs($user);
        $response->assertRedirect(route('pools.index', absolute: false));
        $this->assertNull(Cache::get(SendLoginCode::cacheKey($user->email)));
    }

    public function test_a_wrong_code_fails_to_authenticate()
    {
        $user = User::factory()->create();

        Cache::put(SendLoginCode::cacheKey($user->email), [
            'hash' => Hash::make('123456'),
            'attempts' => 0,
        ], now()->addMinutes(SendLoginCode::TTL_MINUTES));

        $this->post(route('login.store'), [
            'email' => $user->email,
            'code' => '654321',
        ]);

        $this->assertGuest();
    }

    public function test_a_missing_or_expired_code_fails_to_authenticate()
    {
        $user = User::factory()->create();

        // No code is stored in the cache (expired or never requested).
        $this->post(route('login.store'), [
            'email' => $user->email,
            'code' => '123456',
        ]);

        $this->assertGuest();
    }

    public function test_exceeding_the_attempt_limit_invalidates_the_code()
    {
        $user = User::factory()->create();
        $verify = app(VerifyLoginCode::class);

        Cache::put(SendLoginCode::cacheKey($user->email), [
            'hash' => Hash::make('123456'),
            'attempts' => 0,
        ], now()->addMinutes(SendLoginCode::TTL_MINUTES));

        for ($i = 0; $i < VerifyLoginCode::MAX_ATTEMPTS; $i++) {
            $this->assertNull($verify($user->email, '000000'));
        }

        // The code should now be invalidated even when the correct code is given.
        $this->assertNull(Cache::get(SendLoginCode::cacheKey($user->email)));
        $this->assertNull($verify($user->email, '123456'));
    }

    public function test_sending_a_code_is_rate_limited()
    {
        $user = User::factory()->create();

        for ($i = 0; $i < 5; $i++) {
            $this->post(route('login.code.send'), ['email' => $user->email])
                ->assertRedirect();
        }

        $response = $this->post(route('login.code.send'), ['email' => $user->email]);

        $response->assertTooManyRequests();
    }
}
