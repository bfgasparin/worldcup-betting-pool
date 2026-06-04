<?php

namespace Tests\Feature\Settings;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProfileAvatarTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_avatar_can_be_uploaded_from_settings(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('profile.avatar'), ['avatar' => UploadedFile::fake()->image('me.jpg', 300, 300)])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('profile.edit'));

        $user->refresh();
        $this->assertNotNull($user->avatar_path);
        Storage::disk('public')->assertExists($user->avatar_path);
    }

    public function test_the_settings_avatar_must_be_an_image(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('profile.avatar'), ['avatar' => UploadedFile::fake()->create('resume.pdf', 100, 'application/pdf')])
            ->assertSessionHasErrors('avatar');
    }

    public function test_uploading_replaces_the_previous_avatar(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $oldPath = UploadedFile::fake()->image('old.jpg')->store("avatars/{$user->id}", 'public');
        $user->update(['avatar_path' => $oldPath]);

        $this->actingAs($user)
            ->post(route('profile.avatar'), ['avatar' => UploadedFile::fake()->image('new.jpg', 300, 300)])
            ->assertSessionHasNoErrors();

        Storage::disk('public')->assertMissing($oldPath);
        Storage::disk('public')->assertExists($user->refresh()->avatar_path);
    }

    public function test_the_avatar_can_be_removed(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $path = UploadedFile::fake()->image('me.jpg')->store("avatars/{$user->id}", 'public');
        $user->update(['avatar_path' => $path]);

        $this->actingAs($user)
            ->delete(route('profile.avatar.destroy'))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('profile.edit'));

        $this->assertNull($user->refresh()->avatar_path);
        Storage::disk('public')->assertMissing($path);
    }

    public function test_deleting_the_account_removes_the_avatar_file(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $path = UploadedFile::fake()->image('me.jpg')->store("avatars/{$user->id}", 'public');
        $user->update(['avatar_path' => $path]);

        $this->actingAs($user)
            ->delete(route('profile.destroy'))
            ->assertRedirect(route('home'));

        Storage::disk('public')->assertMissing($path);
    }
}
