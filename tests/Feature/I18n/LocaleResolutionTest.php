<?php

namespace Tests\Feature\I18n;

use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SetLocale;
use App\Models\User;
use App\Support\LocaleResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * The per-request locale resolution wired by {@see SetLocale} +
 * {@see LocaleResolver}: an explicit user preference wins, else the browser's
 * Accept-Language device language, else the English fallback. Asserted through the `locale` prop
 * that {@see HandleInertiaRequests} shares on every response.
 */
class LocaleResolutionTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_guest_with_no_language_header_falls_back_to_english(): void
    {
        $this->get('/')->assertInertia(fn (Assert $page) => $page->where('locale', 'en'));
    }

    public function test_the_device_language_is_taken_from_accept_language(): void
    {
        $this->get('/', ['Accept-Language' => 'pt-BR,pt;q=0.9,en;q=0.8'])
            ->assertInertia(fn (Assert $page) => $page->where('locale', 'pt_BR'));
    }

    public function test_a_base_language_header_matches_a_supported_regional_locale(): void
    {
        // "pt" (no region) resolves to the only supported pt_* locale.
        $this->get('/', ['Accept-Language' => 'pt'])
            ->assertInertia(fn (Assert $page) => $page->where('locale', 'pt_BR'));
    }

    public function test_an_unsupported_device_language_falls_back_to_english(): void
    {
        $this->get('/', ['Accept-Language' => 'fr-FR,fr;q=0.9'])
            ->assertInertia(fn (Assert $page) => $page->where('locale', 'en'));
    }

    public function test_an_explicit_user_preference_overrides_the_device_language(): void
    {
        $user = User::factory()->create(['locale' => 'pt_BR']);

        $this->actingAs($user)
            ->get(route('language.edit'), ['Accept-Language' => 'en-US'])
            ->assertInertia(fn (Assert $page) => $page->where('locale', 'pt_BR'));
    }

    public function test_a_user_without_a_preference_uses_the_device_language(): void
    {
        $user = User::factory()->create(['locale' => null]);

        $this->actingAs($user)
            ->get(route('language.edit'), ['Accept-Language' => 'pt-BR'])
            ->assertInertia(fn (Assert $page) => $page->where('locale', 'pt_BR'));
    }
}
