<?php

namespace App\Console\Commands;

use App\Concerns\ProfileValidationRules;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

#[Signature('user:pre-register
    {--name= : The user\'s display name (required)}
    {--phone= : The user\'s phone number (required; their import-matching identity)}')]
#[Description('Pre-register a passwordless user from a name + phone, with no email yet. The email is set later via user:set-email; until then they cannot log in. Fails if the phone already exists; it never modifies existing accounts.')]
class PreRegisterUser extends Command
{
    use ProfileValidationRules;

    public function handle(): int
    {
        $name = trim((string) $this->option('name'));
        $phone = trim((string) $this->option('phone'));

        $validator = Validator::make(
            ['name' => $name, 'phone' => $phone],
            ['name' => $this->nameRules(), 'phone' => $this->phoneRules()],
        );

        if ($validator->fails()) {
            $this->components->error($validator->errors()->first());

            return self::FAILURE;
        }

        // forceCreate bypasses the mass-assignment guard for parity with the user:create command;
        // no email or email_verified_at is set, so both stay NULL until user:set-email is run.
        $user = User::forceCreate([
            'name' => $name,
            'phone' => $phone,
        ]);

        $this->components->info("Pre-registered {$user->name} ({$user->phone}). No email yet — set one with `user:set-email`.");

        return self::SUCCESS;
    }
}
