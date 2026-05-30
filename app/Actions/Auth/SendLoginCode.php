<?php

namespace App\Actions\Auth;

use App\Models\User;
use App\Notifications\LoginCodeNotification;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class SendLoginCode
{
    /**
     * The number of minutes a login code remains valid.
     */
    public const int TTL_MINUTES = 10;

    /**
     * The number of digits in a generated login code.
     */
    public const int CODE_LENGTH = 6;

    /**
     * Generate a login code for the given user, cache its hash, and email it.
     *
     * Returns the generated plain-text code (primarily for testing).
     */
    public function __invoke(User $user): string
    {
        $code = $this->generateCode();

        Cache::put($this->cacheKey($user->email), [
            'hash' => Hash::make($code),
            'attempts' => 0,
        ], now()->addMinutes(self::TTL_MINUTES));

        $user->notify(new LoginCodeNotification($code));

        return $code;
    }

    /**
     * Build the cache key used to store a login code for the given email.
     */
    public static function cacheKey(string $email): string
    {
        return 'login-code:'.Str::lower($email);
    }

    /**
     * Generate a zero-padded numeric login code.
     */
    private function generateCode(): string
    {
        $max = (10 ** self::CODE_LENGTH) - 1;

        return str_pad((string) random_int(0, $max), self::CODE_LENGTH, '0', STR_PAD_LEFT);
    }
}
