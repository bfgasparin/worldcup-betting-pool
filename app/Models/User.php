<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Translation\HasLocalePreference;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Http\UploadedFile;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;

#[Fillable(['name', 'email', 'phone', 'avatar_path', 'locale'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements HasLocalePreference, PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, PasskeyAuthenticatable;

    /**
     * The accessors to append to the model's array form.
     *
     * @var list<string>
     */
    protected $appends = ['avatar'];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'onboarded_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * The user's preferred locale for notifications (e.g. the login code email). Null means no
     * explicit choice — the queued notification then renders in the app's default locale, since a
     * background job has no browser/Accept-Language to read. {@see HasLocalePreference}
     */
    public function preferredLocale(): ?string
    {
        return $this->locale;
    }

    /**
     * The public URL of the user's avatar, or null when none is set.
     */
    protected function avatar(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->avatar_path
            ? Storage::disk('public')->url($this->avatar_path)
            : null);
    }

    /**
     * Store a freshly uploaded avatar on the public disk, discarding any previous one.
     */
    public function storeAvatar(UploadedFile $file): void
    {
        $this->removeAvatar();

        $this->update([
            'avatar_path' => $file->store("avatars/{$this->id}", 'public'),
        ]);
    }

    /**
     * Delete the user's stored avatar file, if any, and clear the reference.
     */
    public function removeAvatar(): void
    {
        if (! $this->avatar_path) {
            return;
        }

        Storage::disk('public')->delete($this->avatar_path);

        $this->update(['avatar_path' => null]);
    }

    /**
     * Whether this user is an application administrator.
     */
    public function isAdmin(): bool
    {
        return in_array($this->email, config('admin.emails', []), true);
    }

    /**
     * Scope to the application's administrators — users whose email is in config('admin.emails').
     * The config-driven counterpart to {@see isAdmin()}; an empty admin list matches no one.
     *
     * @param  Builder<User>  $query
     */
    public function scopeAdmins(Builder $query): void
    {
        $query->whereIn('email', config('admin.emails', []));
    }

    /**
     * Whether the user has finished the first-login onboarding wizard.
     */
    public function isOnboarded(): bool
    {
        return $this->onboarded_at !== null;
    }
}
