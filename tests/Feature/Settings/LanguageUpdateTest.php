<?php

namespace Tests\Feature\Settings;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class LanguageUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_language_page_renders_with_the_supported_locales_and_current_choice(): void
    {
        $user = User::factory()->create(['locale' => 'pt_BR']);

        $this->actingAs($user)
            ->get(route('language.edit'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('settings/language')
                ->where('current', 'pt_BR')
                ->where('supportedLocales.en', 'English')
                ->where('supportedLocales.pt_BR', 'Português (Brasil)')
            );
    }

    public function test_a_user_can_set_an_explicit_language(): void
    {
        $user = User::factory()->create(['locale' => null]);

        $this->actingAs($user)
            ->patch(route('language.update'), ['locale' => 'pt_BR'])
            ->assertRedirect(route('language.edit'));

        $this->assertSame('pt_BR', $user->refresh()->locale);
    }

    public function test_choosing_the_device_language_clears_the_preference(): void
    {
        $user = User::factory()->create(['locale' => 'pt_BR']);

        $this->actingAs($user)
            ->patch(route('language.update'), ['locale' => 'device'])
            ->assertRedirect(route('language.edit'));

        $this->assertNull($user->refresh()->locale);
    }

    public function test_an_unsupported_language_is_rejected(): void
    {
        $user = User::factory()->create(['locale' => 'pt_BR']);

        $this->actingAs($user)
            ->patch(route('language.update'), ['locale' => 'fr'])
            ->assertSessionHasErrors('locale');

        $this->assertSame('pt_BR', $user->refresh()->locale);
    }
}
