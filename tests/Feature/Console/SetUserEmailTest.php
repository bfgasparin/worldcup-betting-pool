<?php

namespace Tests\Feature\Console;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SetUserEmailTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_sets_the_email_and_stamps_verified_when_located_by_phone(): void
    {
        $user = User::factory()->preRegistered()->create(['phone' => '+5511999999999']);

        $this->artisan('user:set-email', ['--phone' => '+5511999999999', '--email' => 'ana@example.com'])
            ->assertSuccessful();

        $user->refresh();

        $this->assertSame('ana@example.com', $user->email);
        $this->assertNotNull($user->email_verified_at);
    }

    public function test_it_sets_the_email_when_located_by_id(): void
    {
        $user = User::factory()->preRegistered()->create();

        $this->artisan('user:set-email', ['--id' => $user->id, '--email' => 'ana@example.com'])
            ->assertSuccessful();

        $this->assertSame('ana@example.com', $user->refresh()->email);
    }

    public function test_it_rejects_an_email_already_used_by_another_user(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);
        $preRegistered = User::factory()->preRegistered()->create(['phone' => '+5511999999999']);

        $this->artisan('user:set-email', ['--phone' => '+5511999999999', '--email' => 'taken@example.com'])
            ->assertFailed();

        $this->assertNull($preRegistered->refresh()->email);
    }

    public function test_it_is_idempotent_when_setting_the_users_existing_email(): void
    {
        $user = User::factory()->create(['email' => 'keep@example.com']);

        $this->artisan('user:set-email', ['--id' => $user->id, '--email' => 'keep@example.com'])
            ->assertSuccessful();

        $this->assertSame('keep@example.com', $user->refresh()->email);
    }

    public function test_it_fails_when_no_account_matches(): void
    {
        $this->artisan('user:set-email', ['--phone' => '+5500000000000', '--email' => 'ana@example.com'])
            ->assertFailed();
    }

    public function test_it_fails_when_neither_phone_nor_id_is_given(): void
    {
        $this->artisan('user:set-email', ['--email' => 'ana@example.com'])->assertFailed();
    }

    public function test_it_fails_when_both_phone_and_id_are_given(): void
    {
        $user = User::factory()->preRegistered()->create(['phone' => '+5511999999999']);

        $this->artisan('user:set-email', [
            '--phone' => '+5511999999999',
            '--id' => $user->id,
            '--email' => 'ana@example.com',
        ])->assertFailed();
    }

    public function test_it_fails_on_an_invalid_email(): void
    {
        $user = User::factory()->preRegistered()->create(['phone' => '+5511999999999']);

        $this->artisan('user:set-email', ['--phone' => '+5511999999999', '--email' => 'not-an-email'])
            ->assertFailed();

        $this->assertNull($user->refresh()->email);
    }
}
