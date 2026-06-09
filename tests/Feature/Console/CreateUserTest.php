<?php

namespace Tests\Feature\Console;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreateUserTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_a_passwordless_verified_user(): void
    {
        $this->artisan('user:create', ['--email' => 'owner@domain.com', '--name' => 'Owner'])
            ->assertSuccessful();

        $user = User::where('email', 'owner@domain.com')->firstOrFail();

        $this->assertSame('Owner', $user->name);
        $this->assertNotNull($user->email_verified_at);
        $this->assertNull($user->password);
        $this->assertNull($user->phone);
        $this->assertNull($user->locale);
    }

    public function test_it_stores_an_explicit_locale(): void
    {
        $this->artisan('user:create', ['--email' => 'owner@domain.com', '--locale' => 'pt_BR'])
            ->assertSuccessful();

        $this->assertSame('pt_BR', User::where('email', 'owner@domain.com')->value('locale'));
    }

    public function test_it_rejects_an_unsupported_locale(): void
    {
        $this->artisan('user:create', ['--email' => 'owner@domain.com', '--locale' => 'fr'])
            ->assertFailed();

        $this->assertDatabaseCount('users', 0);
    }

    public function test_it_defaults_the_name_to_the_email_local_part(): void
    {
        $this->artisan('user:create', ['--email' => 'jane.doe@domain.com'])
            ->assertSuccessful();

        $this->assertDatabaseHas('users', [
            'email' => 'jane.doe@domain.com',
            'name' => 'jane.doe',
        ]);
    }

    public function test_it_refuses_to_modify_an_existing_user(): void
    {
        User::factory()->create(['email' => 'owner@domain.com', 'name' => 'Original Name']);

        $this->artisan('user:create', ['--email' => 'owner@domain.com', '--name' => 'New Name'])
            ->assertFailed();

        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseHas('users', [
            'email' => 'owner@domain.com',
            'name' => 'Original Name',
        ]);
    }

    public function test_it_fails_when_the_email_is_missing(): void
    {
        $this->artisan('user:create')->assertFailed();

        $this->assertDatabaseCount('users', 0);
    }

    public function test_it_fails_when_the_email_is_invalid(): void
    {
        $this->artisan('user:create', ['--email' => 'not-an-email'])->assertFailed();

        $this->assertDatabaseCount('users', 0);
    }

    public function test_it_reports_when_the_user_is_already_an_admin(): void
    {
        config()->set('admin.emails', ['owner@domain.com']);

        $this->artisan('user:create', ['--email' => 'owner@domain.com', '--admin' => true])
            ->expectsOutputToContain('is an admin')
            ->assertSuccessful();
    }

    public function test_it_prints_the_admin_emails_line_when_not_yet_an_admin(): void
    {
        config()->set('admin.emails', []);

        $this->artisan('user:create', ['--email' => 'owner@domain.com', '--admin' => true])
            ->expectsOutputToContain('ADMIN_EMAILS=owner@domain.com')
            ->assertSuccessful();
    }
}
