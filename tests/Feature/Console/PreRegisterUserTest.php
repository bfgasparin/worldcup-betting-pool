<?php

namespace Tests\Feature\Console;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PreRegisterUserTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_a_phone_only_user_with_no_email(): void
    {
        $this->artisan('user:pre-register', ['--name' => 'Ana Silva', '--phone' => '+5511999999999'])
            ->assertSuccessful();

        $user = User::where('phone', '+5511999999999')->firstOrFail();

        $this->assertSame('Ana Silva', $user->name);
        $this->assertNull($user->email);
        $this->assertNull($user->email_verified_at);
        $this->assertNull($user->password);
    }

    public function test_it_fails_when_the_name_is_missing(): void
    {
        $this->artisan('user:pre-register', ['--phone' => '+5511999999999'])->assertFailed();

        $this->assertDatabaseCount('users', 0);
    }

    public function test_it_fails_when_the_phone_is_missing(): void
    {
        $this->artisan('user:pre-register', ['--name' => 'Ana Silva'])->assertFailed();

        $this->assertDatabaseCount('users', 0);
    }

    public function test_it_fails_when_the_phone_is_invalid(): void
    {
        $this->artisan('user:pre-register', ['--name' => 'Ana Silva', '--phone' => 'not-a-phone'])
            ->assertFailed();

        $this->assertDatabaseCount('users', 0);
    }

    public function test_it_rejects_a_duplicate_phone(): void
    {
        User::factory()->preRegistered()->create(['phone' => '+5511999999999']);

        $this->artisan('user:pre-register', ['--name' => 'Someone Else', '--phone' => '+5511999999999'])
            ->assertFailed();

        $this->assertDatabaseCount('users', 1);
    }
}
