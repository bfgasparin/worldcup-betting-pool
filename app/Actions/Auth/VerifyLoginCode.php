<?php

namespace App\Actions\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;

class VerifyLoginCode
{
    /**
     * The number of failed attempts allowed before a login code is invalidated.
     */
    public const int MAX_ATTEMPTS = 5;

    /**
     * Verify the given email and code pair.
     *
     * Returns the matching user on success, or null on any failure (unknown
     * email, missing/expired code, mismatch, or too many attempts). The cached
     * code is cleared on success and after exceeding the attempt limit.
     */
    public function __invoke(?string $email, ?string $code): ?User
    {
        if (blank($email) || blank($code)) {
            return null;
        }

        $key = SendLoginCode::cacheKey($email);
        $entry = Cache::get($key);

        if ($entry === null) {
            return null;
        }

        if (! Hash::check($code, $entry['hash'])) {
            $entry['attempts']++;

            if ($entry['attempts'] >= self::MAX_ATTEMPTS) {
                Cache::forget($key);
            } else {
                Cache::put($key, $entry, now()->addMinutes(SendLoginCode::TTL_MINUTES));
            }

            return null;
        }

        $user = User::where('email', $email)->first();

        if ($user === null) {
            return null;
        }

        Cache::forget($key);

        return $user;
    }
}
