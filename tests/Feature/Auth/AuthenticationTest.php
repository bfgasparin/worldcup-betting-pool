<?php

namespace Tests\Feature\Auth;

use App\Actions\Auth\SendLoginCode;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered()
    {
        $response = $this->get(route('login'));

        $response->assertOk();
    }

    public function test_users_can_authenticate_using_a_valid_login_code()
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

        $this->assertAuthenticated();
        $response->assertRedirect(route('pools.index', absolute: false));
        $this->assertNull(Cache::get(SendLoginCode::cacheKey($user->email)));
    }

    public function test_users_can_not_authenticate_with_an_invalid_login_code()
    {
        $user = User::factory()->create();

        Cache::put(SendLoginCode::cacheKey($user->email), [
            'hash' => Hash::make('123456'),
            'attempts' => 0,
        ], now()->addMinutes(SendLoginCode::TTL_MINUTES));

        $this->post(route('login.store'), [
            'email' => $user->email,
            'code' => '000000',
        ]);

        $this->assertGuest();
    }

    public function test_users_can_logout()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('logout'));

        $response->assertRedirect(route('home'));

        $this->assertGuest();
    }

    public function test_pwa_users_are_redirected_to_login_on_logout()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('logout', ['pwa' => 1]));

        $response->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_login_verification_is_rate_limited()
    {
        $user = User::factory()->create();

        RateLimiter::increment(md5('login'.implode('|', [$user->email, '127.0.0.1'])), amount: 5);

        $response = $this->post(route('login.store'), [
            'email' => $user->email,
            'code' => '000000',
        ]);

        $response->assertTooManyRequests();
    }
}
