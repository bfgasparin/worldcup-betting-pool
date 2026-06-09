<?php

namespace Tests\Unit\Models;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class UserTest extends TestCase
{
    use RefreshDatabase;

    public function test_avatar_accessor_is_null_when_no_avatar_path_is_set(): void
    {
        $user = new User(['avatar_path' => null]);

        $this->assertNull($user->avatar);
    }

    public function test_avatar_accessor_resolves_the_public_disk_url(): void
    {
        // The real public disk (the test default) does not provide temporary URLs, so the
        // accessor falls back to a plain public URL — the local-development path.
        $user = new User(['avatar_path' => 'avatars/1/photo.jpg']);

        $this->assertSame(
            Storage::disk('public')->url('avatars/1/photo.jpg'),
            $user->avatar,
        );
    }

    public function test_avatar_accessor_returns_a_temporary_url_when_the_disk_supports_them(): void
    {
        Storage::fake('public');

        // The private production bucket exposes signed temporary URLs; emulate that on the
        // faked default disk so the accessor takes its temporaryUrl() branch.
        Storage::disk('public')->buildTemporaryUrlsUsing(
            fn (string $path, $expiration): string => "https://signed.example/{$path}",
        );

        $user = new User(['avatar_path' => 'avatars/1/photo.jpg']);

        $this->assertSame('https://signed.example/avatars/1/photo.jpg', $user->avatar);
    }

    public function test_avatar_is_appended_to_the_array_form(): void
    {
        $user = User::factory()->create(['avatar_path' => null]);

        $this->assertArrayHasKey('avatar', $user->toArray());
    }

    public function test_is_onboarded_reflects_the_onboarded_at_timestamp(): void
    {
        $this->assertTrue(User::factory()->create()->isOnboarded());
        $this->assertFalse(User::factory()->notOnboarded()->create()->isOnboarded());
    }
}
