<?php

namespace App\Console\Commands;

use App\Concerns\ProfileValidationRules;
use App\Models\Pool;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

#[Signature('user:pre-register
    {--name= : The user\'s display name (required)}
    {--phone= : The user\'s phone number (required; their import-matching identity)}
    {--pool=* : Slug of a pool to pre-join the (already-paid) user into; repeatable}')]
#[Description('Pre-register a passwordless user from a name + phone, with no email yet, and optionally pre-join them to one or more pools (for players who have already paid). The email is set later via user:set-email; until then they cannot log in. Fails if the phone already exists; it never modifies existing accounts. Pool pre-joins skip the admin notification the web join sends.')]
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

        /** @var array<int, string> $slugs */
        $slugs = array_values(array_filter(array_unique($this->option('pool'))));

        // Resolve and guard the pools before touching the database so a bad slug or a locked pool
        // leaves no orphaned user behind.
        $pools = Pool::whereIn('slug', $slugs)->get();

        $missing = array_diff($slugs, $pools->pluck('slug')->all());

        if ($missing !== []) {
            $this->components->error('Unknown pool '.(count($missing) === 1 ? 'slug' : 'slugs').': '.implode(', ', $missing).'.');

            return self::FAILURE;
        }

        foreach ($pools as $pool) {
            if (! $pool->acceptsPredictions()) {
                $this->components->error("Pool \"{$pool->name}\" is no longer accepting predictions.");

                return self::FAILURE;
            }
        }

        $user = DB::transaction(function () use ($name, $phone, $pools): User {
            // forceCreate bypasses the mass-assignment guard for parity with the user:create command;
            // no email or email_verified_at is set, so both stay NULL until user:set-email is run.
            $user = User::forceCreate([
                'name' => $name,
                'phone' => $phone,
            ]);

            // Pre-join, mirroring PoolController::join but deliberately WITHOUT the admin
            // notification: the organizer is doing this for an already-paid player, so there is
            // no buy-in to chase.
            foreach ($pools as $pool) {
                $pool->entries()->firstOrCreate(['user_id' => $user->id]);
            }

            return $user;
        });

        $joined = $pools->isNotEmpty() ? " Joined: {$pools->pluck('name')->implode(', ')}." : '';

        $this->components->info("Pre-registered {$user->name} ({$user->phone}).{$joined} No email yet — set one with `user:set-email`.");

        return self::SUCCESS;
    }
}
