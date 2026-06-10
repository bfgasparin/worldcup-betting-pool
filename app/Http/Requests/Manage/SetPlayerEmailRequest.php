<?php

namespace App\Http\Requests\Manage;

use App\Concerns\ProfileValidationRules;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class SetPlayerEmailRequest extends FormRequest
{
    use ProfileValidationRules;

    /**
     * Manage permission, and only while the account is still email-less: setting the email is a
     * one-way door that hands the account to the player, so it can only happen once.
     */
    public function authorize(): bool
    {
        return ($this->user()?->can('manage-tournament') ?? false) && $this->target()->email === null;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'email' => $this->emailRules($this->target()->id),
        ];
    }

    private function target(): User
    {
        /** @var User $user */
        $user = $this->route('user');

        return $user;
    }
}
