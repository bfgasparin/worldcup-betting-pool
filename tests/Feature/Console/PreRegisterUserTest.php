<?php

namespace Tests\Feature\Console;

use App\Models\Entry;
use App\Models\Pool;
use App\Models\User;
use Database\Seeders\WorldCup2026Seeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PreRegisterUserTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_a_user_with_no_email(): void
    {
        $this->artisan('user:pre-register', ['--name' => 'Ana Silva'])
            ->assertSuccessful();

        $user = User::where('name', 'Ana Silva')->firstOrFail();

        $this->assertSame('Ana Silva', $user->name);
        $this->assertNull($user->email);
        $this->assertNull($user->email_verified_at);
        $this->assertNull($user->password);
        // No --locale given: the user follows the device language until they choose one.
        $this->assertNull($user->locale);
    }

    public function test_it_stores_an_explicit_locale(): void
    {
        $this->artisan('user:pre-register', ['--name' => 'Ana Silva', '--locale' => 'pt_BR'])
            ->assertSuccessful();

        $this->assertSame('pt_BR', User::where('name', 'Ana Silva')->value('locale'));
    }

    public function test_it_rejects_an_unsupported_locale(): void
    {
        $this->artisan('user:pre-register', ['--name' => 'Ana Silva', '--locale' => 'fr'])
            ->assertFailed();

        $this->assertDatabaseCount('users', 0);
    }

    public function test_it_fails_when_the_name_is_missing(): void
    {
        $this->artisan('user:pre-register', [])->assertFailed();

        $this->assertDatabaseCount('users', 0);
    }

    public function test_it_pre_joins_the_user_to_a_pool_without_notifying_admins(): void
    {
        Notification::fake();
        $this->seed(WorldCup2026Seeder::class);

        $this->artisan('user:pre-register', [
            '--name' => 'Ana Silva',
            '--pool' => ['world-cup-2026-ffa'],
        ])->assertSuccessful();

        $user = User::where('name', 'Ana Silva')->firstOrFail();
        $pool = Pool::where('slug', 'world-cup-2026-ffa')->firstOrFail();

        $this->assertDatabaseHas('entries', ['pool_id' => $pool->id, 'user_id' => $user->id]);
        Notification::assertNothingSent();
    }

    public function test_it_pre_joins_the_user_to_multiple_pools(): void
    {
        Notification::fake();
        $this->seed(WorldCup2026Seeder::class);

        $this->artisan('user:pre-register', [
            '--name' => 'Ana Silva',
            '--pool' => ['world-cup-2026-ffa', 'world-cup-2026-brothers'],
        ])->assertSuccessful();

        $user = User::where('name', 'Ana Silva')->firstOrFail();

        $this->assertSame(2, Entry::where('user_id', $user->id)->count());
        Notification::assertNothingSent();
    }

    public function test_it_fails_and_creates_nothing_for_an_unknown_pool_slug(): void
    {
        $this->seed(WorldCup2026Seeder::class);

        $this->artisan('user:pre-register', [
            '--name' => 'Ana Silva',
            '--pool' => ['world-cup-2026-ffa', 'does-not-exist'],
        ])->assertFailed();

        $this->assertDatabaseMissing('users', ['name' => 'Ana Silva']);
        $this->assertSame(0, Entry::count());
    }

    public function test_it_fails_and_creates_nothing_when_a_pool_no_longer_accepts_predictions(): void
    {
        $this->seed(WorldCup2026Seeder::class);

        Pool::where('slug', 'world-cup-2026-ffa')->update(['predictions_lock_at' => now()->subDay()]);

        $this->artisan('user:pre-register', [
            '--name' => 'Ana Silva',
            '--pool' => ['world-cup-2026-ffa'],
        ])->assertFailed();

        $this->assertDatabaseMissing('users', ['name' => 'Ana Silva']);
        $this->assertSame(0, Entry::count());
    }
}
