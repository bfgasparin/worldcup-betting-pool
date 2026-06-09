<?php

namespace App\Console\Commands;

use App\Concerns\ResolvesLocaleOption;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

#[Signature('user:create
    {--email= : The user\'s email address (their login identity)}
    {--name= : Display name (defaults to the part before @ when omitted)}
    {--locale= : Preferred language for emails/UI (e.g. pt_BR); omit to follow the device language}
    {--admin : Also report admin status and print the ADMIN_EMAILS line to add}')]
#[Description('Create a new pre-registered, passwordless user so they can log in via an emailed code. Fails if the email already exists; it never modifies existing accounts.')]
class CreateUser extends Command
{
    use ResolvesLocaleOption;

    public function handle(): int
    {
        $email = trim((string) $this->option('email'));

        if (Validator::make(['email' => $email], ['email' => ['required', 'email']])->fails()) {
            $this->components->error('Provide a valid email with --email, e.g. `user:create --email=owner@domain.com`.');

            return self::FAILURE;
        }

        if (User::where('email', $email)->exists()) {
            $this->components->error("A user with the email {$email} already exists. This command only creates new accounts; it never modifies existing ones.");

            return self::FAILURE;
        }

        $name = trim((string) $this->option('name')) ?: str($email)->before('@')->toString();

        $locale = $this->resolveLocale();

        if ($locale === false) {
            return self::FAILURE;
        }

        // forceCreate bypasses the model's mass-assignment guard so email_verified_at (outside
        // #[Fillable]) is stamped, keeping new accounts in parity with factory/seeded users.
        $user = User::forceCreate([
            'name' => $name,
            'email' => $email,
            'email_verified_at' => now(),
            'locale' => $locale,
        ]);

        $this->components->info("User {$user->email} ({$user->name}) created.");

        $this->reportAdminStatus($user);

        return self::SUCCESS;
    }

    private function reportAdminStatus(User $user): void
    {
        if ($user->isAdmin()) {
            $this->components->info("{$user->email} is an admin (already present in ADMIN_EMAILS).");

            return;
        }

        if (! $this->option('admin')) {
            return;
        }

        /** @var list<string> $current */
        $current = config('admin.emails', []);
        $suggested = implode(',', array_values(array_unique([...$current, $user->email])));

        $this->newLine();
        $this->components->warn("{$user->email} is NOT an admin yet — admin is config-driven, not stored in the DB.");
        $this->line('  Set this in your environment (case-sensitive; must match the stored email exactly):');
        $this->newLine();
        $this->line("  ADMIN_EMAILS={$suggested}");
        $this->newLine();
        $this->line('  Laravel Cloud: add/update it under the environment\'s Variables, then redeploy so config is re-cached.');
    }
}
