<?php

namespace Tests\Feature\Onboarding;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OnboardingGateTest extends TestCase
{
    use RefreshDatabase;

    public function test_not_onboarded_users_are_redirected_to_the_wizard(): void
    {
        $user = User::factory()->notOnboarded()->create();

        $this->actingAs($user)
            ->get(route('profile.edit'))
            ->assertRedirect(route('onboarding.show'));
    }

    public function test_onboarded_users_are_not_gated(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('profile.edit'))
            ->assertOk();
    }

    public function test_the_onboarding_routes_are_exempt_from_the_gate(): void
    {
        $user = User::factory()->notOnboarded()->create();

        // A wizard endpoint must not bounce a not-onboarded user back to the wizard.
        $this->actingAs($user)
            ->post(route('onboarding.complete'))
            ->assertRedirect(route('pools.index'));
    }

    public function test_logout_is_never_gated(): void
    {
        $user = User::factory()->notOnboarded()->create();

        $response = $this->actingAs($user)->post(route('logout'));

        $this->assertGuest();
        $this->assertNotSame(route('onboarding.show'), $response->headers->get('Location'));
    }

    public function test_passkey_registration_endpoints_are_exempt(): void
    {
        $user = User::factory()->notOnboarded()->create();

        $response = $this->actingAs($user)->get('/user/passkeys/options');

        $this->assertNotSame(route('onboarding.show'), $response->headers->get('Location'));
    }

    public function test_guests_are_redirected_to_login_not_onboarding(): void
    {
        $this->get(route('profile.edit'))->assertRedirect(route('login'));
    }

    public function test_inertia_visits_do_not_bypass_the_gate(): void
    {
        $user = User::factory()->notOnboarded()->create();

        $response = $this->actingAs($user)
            ->withHeader('X-Inertia', 'true')
            ->get(route('profile.edit'));

        // The gated page is never served over an Inertia XHR visit: the user is bounced
        // (redirect) or forced to hard-reload (409), both of which re-hit the gate.
        $this->assertNotSame(200, $response->status());
    }
}
