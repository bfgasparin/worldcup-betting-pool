<?php

namespace App\Http\Requests\Manage;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;

class UpdatePlayerRequest extends StorePlayerRequest
{
    /**
     * Manage permission plus the full lock: once a player has a login email the account belongs to
     * them, so admins can no longer edit it — only the player can, from their own settings.
     */
    public function authorize(): bool
    {
        return parent::authorize() && $this->target()->email === null;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        // commonRules() (not parent::rules()) so the email field — a one-way, lock-on-create action —
        // is never editable here; once an email exists, authorize() already blocks the request.
        return array_merge($this->commonRules(), [
            // Ignore the player's own row so re-saving an unchanged phone doesn't trip uniqueness.
            'phone' => $this->phoneRules($this->target()->id),
        ]);
    }

    private function target(): User
    {
        /** @var User $user */
        $user = $this->route('user');

        return $user;
    }
}
