<?php

namespace Tests\Feature\Manage;

use App\Models\Entry;
use App\Models\Pool;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class PlayerControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    private function admin(): User
    {
        $admin = User::factory()->create();
        config()->set('admin.emails', [$admin->email]);

        return $admin;
    }

    /** A pool still inside its prediction window (factory default lock is +1 week..+1 month). */
    private function joinablePool(): Pool
    {
        return Pool::factory()->create();
    }

    /** A pre-registered player: name + phone, no email yet. */
    private function preRegistered(): User
    {
        return User::factory()->preRegistered()->create();
    }

    public function test_non_admins_cannot_access_any_player_endpoint(): void
    {
        $target = $this->preRegistered();

        $this->actingAs(User::factory()->create())
            ->get(route('manage.players.index'))
            ->assertForbidden();

        $this->actingAs(User::factory()->create())
            ->post(route('manage.players.store'))
            ->assertForbidden();

        $this->actingAs(User::factory()->create())
            ->get(route('manage.players.edit', $target))
            ->assertForbidden();

        $this->actingAs(User::factory()->create())
            ->patch(route('manage.players.update', $target))
            ->assertForbidden();

        $this->actingAs(User::factory()->create())
            ->patch(route('manage.players.email', $target))
            ->assertForbidden();
    }

    public function test_admin_pre_registers_a_player_and_joins_pools_without_notifying(): void
    {
        Notification::fake();

        $pool = $this->joinablePool();

        $this->actingAs($this->admin())
            ->post(route('manage.players.store'), [
                'name' => 'Alpha Tester',
                'phone' => '+5511999998888',
                'locale' => 'pt_BR',
                'pools' => [$pool->id],
            ])
            ->assertRedirect(route('manage.players.index'));

        $this->assertDatabaseHas('users', [
            'name' => 'Alpha Tester',
            'phone' => '+5511999998888',
            'locale' => 'pt_BR',
            'email' => null,
            'email_verified_at' => null,
        ]);

        $player = User::where('phone', '+5511999998888')->firstOrFail();

        $this->assertDatabaseHas('entries', [
            'pool_id' => $pool->id,
            'user_id' => $player->id,
        ]);

        // Pre-registering an already-paid player must never spam admins with a join notice.
        Notification::assertNothingSent();
    }

    public function test_pre_register_validates_name_and_phone_and_locale_and_pools(): void
    {
        $admin = $this->admin();
        $closedPool = Pool::factory()->create(['predictions_lock_at' => now()->subDay()]);

        // Name and phone are required.
        $this->actingAs($admin)
            ->post(route('manage.players.store'), ['name' => '', 'phone' => ''])
            ->assertSessionHasErrors(['name', 'phone']);

        // Phone must look like a phone number.
        $this->actingAs($admin)
            ->post(route('manage.players.store'), ['name' => 'Bad Phone', 'phone' => 'abc'])
            ->assertSessionHasErrors(['phone']);

        // Phone is unique.
        $existing = User::factory()->create(['phone' => '+5511900000000']);
        $this->actingAs($admin)
            ->post(route('manage.players.store'), ['name' => 'Dupe', 'phone' => $existing->phone])
            ->assertSessionHasErrors(['phone']);

        // Locale must be supported.
        $this->actingAs($admin)
            ->post(route('manage.players.store'), ['name' => 'Fr', 'phone' => '+5511911111111', 'locale' => 'fr_FR'])
            ->assertSessionHasErrors(['locale']);

        // A pool that no longer accepts predictions is rejected.
        $this->actingAs($admin)
            ->post(route('manage.players.store'), [
                'name' => 'Late',
                'phone' => '+5511922222222',
                'pools' => [$closedPool->id],
            ])
            ->assertSessionHasErrors(['pools']);

        $this->assertDatabaseMissing('users', ['name' => 'Late']);
    }

    public function test_admin_edits_an_unlocked_player_and_adds_pools_idempotently(): void
    {
        $admin = $this->admin();
        $player = $this->preRegistered();
        $pool = $this->joinablePool();

        $this->actingAs($admin)
            ->patch(route('manage.players.update', $player), [
                'name' => 'Renamed Player',
                'phone' => '+5511933333333',
                'locale' => 'pt_BR',
                'pools' => [$pool->id],
            ])
            ->assertRedirect(route('manage.players.edit', $player));

        $this->assertDatabaseHas('users', [
            'id' => $player->id,
            'name' => 'Renamed Player',
            'phone' => '+5511933333333',
            'locale' => 'pt_BR',
        ]);

        // Re-adding the same pool must not create a second entry (add-only, idempotent).
        $this->actingAs($admin)
            ->patch(route('manage.players.update', $player), [
                'name' => 'Renamed Player',
                'phone' => '+5511933333333',
                'pools' => [$pool->id],
            ]);

        $this->assertEquals(1, Entry::where('user_id', $player->id)->where('pool_id', $pool->id)->count());
    }

    public function test_admin_sets_a_login_email_and_marks_it_verified(): void
    {
        $admin = $this->admin();
        $player = $this->preRegistered();

        $this->actingAs($admin)
            ->patch(route('manage.players.email', $player), ['email' => 'tester@example.com'])
            ->assertRedirect(route('manage.players.edit', $player));

        $player->refresh();

        $this->assertSame('tester@example.com', $player->email);
        $this->assertNotNull($player->email_verified_at);
    }

    public function test_admin_can_pre_register_a_player_with_an_email_which_locks_the_account(): void
    {
        $admin = $this->admin();
        $pool = $this->joinablePool();

        $this->actingAs($admin)
            ->post(route('manage.players.store'), [
                'name' => 'Has Email',
                'phone' => '+5511955556666',
                'email' => 'has.email@example.com',
                'pools' => [$pool->id],
            ])
            ->assertRedirect(route('manage.players.index'));

        $player = User::where('email', 'has.email@example.com')->firstOrFail();

        // An email given at creation is vouched for (verified) and joins still happen.
        $this->assertNotNull($player->email_verified_at);
        $this->assertDatabaseHas('entries', ['user_id' => $player->id, 'pool_id' => $pool->id]);

        // Same rule as setting the email later: the account is immediately locked to admin edits.
        $this->actingAs($admin)
            ->patch(route('manage.players.update', $player), [
                'name' => 'Nope',
                'phone' => '+5511955550000',
            ])
            ->assertForbidden();

        $this->actingAs($admin)
            ->patch(route('manage.players.email', $player), ['email' => 'other@example.com'])
            ->assertForbidden();
    }

    public function test_pre_register_rejects_an_invalid_or_duplicate_email(): void
    {
        $admin = $this->admin();

        // Malformed and incomplete addresses (e.g. no domain extension) must be rejected.
        foreach (['not-an-email', 'test@@example', 'test@example'] as $bad) {
            $this->actingAs($admin)
                ->post(route('manage.players.store'), [
                    'name' => 'Bad Email',
                    'phone' => '+5511955551111',
                    'email' => $bad,
                ])
                ->assertSessionHasErrors(['email']);

            $this->assertDatabaseMissing('users', ['phone' => '+5511955551111']);
        }

        $existing = User::factory()->create(['email' => 'taken@example.com']);

        $this->actingAs($admin)
            ->post(route('manage.players.store'), [
                'name' => 'Dup Email',
                'phone' => '+5511955552222',
                'email' => $existing->email,
            ])
            ->assertSessionHasErrors(['email']);
    }

    public function test_set_email_rejects_invalid_or_duplicate_addresses(): void
    {
        $admin = $this->admin();
        $existing = User::factory()->create(['email' => 'taken@example.com']);

        foreach (['test@@example', 'test@example', $existing->email] as $bad) {
            $player = $this->preRegistered();

            $this->actingAs($admin)
                ->patch(route('manage.players.email', $player), ['email' => $bad])
                ->assertSessionHasErrors(['email']);

            $player->refresh();
            $this->assertNull($player->email, "Expected [{$bad}] to be rejected, but it was saved.");
        }
    }

    public function test_pre_register_without_an_email_stays_pending_and_can_log_in_later(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->post(route('manage.players.store'), [
                'name' => 'No Email',
                'phone' => '+5511955553333',
            ])
            ->assertRedirect(route('manage.players.index'));

        $player = User::where('phone', '+5511955553333')->firstOrFail();

        // No email → still editable; the set-login-email flow remains available for them.
        $this->assertNull($player->email);
        $this->assertNull($player->email_verified_at);

        $this->actingAs($admin)
            ->patch(route('manage.players.email', $player), ['email' => 'late@example.com'])
            ->assertRedirect(route('manage.players.edit', $player));
    }

    public function test_a_player_with_an_email_is_fully_locked_from_admin_edits(): void
    {
        $admin = $this->admin();
        $locked = User::factory()->create(['name' => 'Owns Account']);
        $pool = $this->joinablePool();

        $this->actingAs($admin)
            ->patch(route('manage.players.update', $locked), [
                'name' => 'Hacked Name',
                'phone' => '+5511944444444',
            ])
            ->assertForbidden();

        $this->actingAs($admin)
            ->patch(route('manage.players.email', $locked), ['email' => 'new@example.com'])
            ->assertForbidden();

        $this->actingAs($admin)
            ->patch(route('manage.players.update', $locked), [
                'name' => 'Owns Account',
                'phone' => $locked->phone,
                'pools' => [$pool->id],
            ])
            ->assertForbidden();

        $locked->refresh();
        $this->assertSame('Owns Account', $locked->name);
        $this->assertDatabaseMissing('entries', ['user_id' => $locked->id, 'pool_id' => $pool->id]);
    }

    public function test_index_lists_players_with_status_and_joinable_pools(): void
    {
        $admin = $this->admin();
        $this->preRegistered();
        $this->joinablePool();

        $this->actingAs($admin)
            ->get(route('manage.players.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('manage/players')
                ->has('players.data')
                ->has('pools')
                ->has('filters'));
    }

    public function test_edit_exposes_the_locked_flag(): void
    {
        $admin = $this->admin();
        $unlocked = $this->preRegistered();
        $locked = User::factory()->create();

        $this->actingAs($admin)
            ->get(route('manage.players.edit', $unlocked))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('manage/player-edit')
                ->where('player.locked', false));

        $this->actingAs($admin)
            ->get(route('manage.players.edit', $locked))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('manage/player-edit')
                ->where('player.locked', true));
    }
}
