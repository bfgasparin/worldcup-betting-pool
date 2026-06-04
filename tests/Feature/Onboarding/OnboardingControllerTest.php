<?php

namespace Tests\Feature\Onboarding;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class OnboardingControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_wizard_renders_with_passkey_state(): void
    {
        $user = User::factory()->notOnboarded()->create();

        $this->actingAs($user)
            ->get(route('onboarding.show'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('onboarding/wizard')
                ->where('hasPasskeys', false)
            );
    }

    public function test_the_name_can_be_confirmed_or_corrected(): void
    {
        $user = User::factory()->notOnboarded()->create(['name' => 'Admin Typed']);

        $this->actingAs($user)
            ->patch(route('onboarding.name'), ['name' => 'Real Name'])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertSame('Real Name', $user->refresh()->name);
    }

    public function test_the_name_is_required(): void
    {
        $user = User::factory()->notOnboarded()->create();

        $this->actingAs($user)
            ->patch(route('onboarding.name'), ['name' => ''])
            ->assertSessionHasErrors('name');
    }

    public function test_an_avatar_can_be_uploaded(): void
    {
        Storage::fake('public');
        $user = User::factory()->notOnboarded()->create();

        $this->actingAs($user)
            ->post(route('onboarding.avatar'), ['avatar' => UploadedFile::fake()->image('me.jpg', 300, 300)])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $user->refresh();
        $this->assertNotNull($user->avatar_path);
        Storage::disk('public')->assertExists($user->avatar_path);
    }

    public function test_the_avatar_must_be_an_image(): void
    {
        Storage::fake('public');
        $user = User::factory()->notOnboarded()->create();

        $this->actingAs($user)
            ->post(route('onboarding.avatar'), ['avatar' => UploadedFile::fake()->create('resume.pdf', 100, 'application/pdf')])
            ->assertSessionHasErrors('avatar');

        $this->assertNull($user->refresh()->avatar_path);
    }

    public function test_the_avatar_rejects_oversized_files(): void
    {
        Storage::fake('public');
        $user = User::factory()->notOnboarded()->create();

        $this->actingAs($user)
            ->post(route('onboarding.avatar'), ['avatar' => UploadedFile::fake()->image('huge.jpg')->size(5000)])
            ->assertSessionHasErrors('avatar');
    }

    public function test_uploading_a_new_avatar_replaces_the_old_one(): void
    {
        Storage::fake('public');
        $user = User::factory()->notOnboarded()->create();
        $oldPath = UploadedFile::fake()->image('old.jpg')->store("avatars/{$user->id}", 'public');
        $user->update(['avatar_path' => $oldPath]);

        $this->actingAs($user)
            ->post(route('onboarding.avatar'), ['avatar' => UploadedFile::fake()->image('new.jpg', 300, 300)])
            ->assertSessionHasNoErrors();

        $user->refresh();
        $this->assertNotSame($oldPath, $user->avatar_path);
        Storage::disk('public')->assertMissing($oldPath);
        Storage::disk('public')->assertExists($user->avatar_path);
    }

    public function test_completing_the_wizard_marks_the_user_onboarded(): void
    {
        $user = User::factory()->notOnboarded()->create();

        $this->actingAs($user)
            ->post(route('onboarding.complete'))
            ->assertRedirect(route('games.index'));

        $this->assertNotNull($user->refresh()->onboarded_at);
    }

    public function test_completing_without_filling_any_step_still_finishes(): void
    {
        $user = User::factory()->notOnboarded()->create();

        $this->actingAs($user)
            ->post(route('onboarding.complete'))
            ->assertRedirect(route('games.index'));

        $user->refresh();
        $this->assertTrue($user->isOnboarded());
        $this->assertNull($user->avatar_path);
    }
}
