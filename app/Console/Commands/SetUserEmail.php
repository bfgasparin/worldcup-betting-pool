<?php

namespace App\Console\Commands;

use App\Concerns\ProfileValidationRules;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

#[Signature('user:set-email
    {--phone= : Locate the account by phone}
    {--id= : Locate the account by user id}
    {--email= : The email to set (their login identity)}')]
#[Description('Set or update a pre-registered user\'s email so they can log in via emailed codes. Locate the account by --phone or --id. Stamps the email as verified. Fails if another account already uses the email.')]
class SetUserEmail extends Command
{
    use ProfileValidationRules;

    public function handle(): int
    {
        $phone = trim((string) $this->option('phone'));
        $id = trim((string) $this->option('id'));

        if (($phone === '') === ($id === '')) {
            $this->components->error('Provide exactly one of --phone or --id to locate the account.');

            return self::FAILURE;
        }

        $user = $phone !== ''
            ? User::where('phone', $phone)->first()
            : User::find($id);

        if ($user === null) {
            $this->components->error('No user matches the given '.($phone !== '' ? "phone {$phone}." : "id {$id}."));

            return self::FAILURE;
        }

        $email = trim((string) $this->option('email'));

        $validator = Validator::make(
            ['email' => $email],
            ['email' => $this->emailRules($user->id)],
        );

        if ($validator->fails()) {
            $this->components->error($validator->errors()->first());

            return self::FAILURE;
        }

        // The operator vouches for this address, so verify it immediately — matching the
        // user:create and seeder semantics for known accounts.
        $user->forceFill([
            'email' => $email,
            'email_verified_at' => now(),
        ])->save();

        $this->components->info("Set {$email} for {$user->name} (id {$user->id}). They can now request a login code.");

        return self::SUCCESS;
    }
}
